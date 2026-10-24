<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\OfflinePolicy;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class OfflineSupport
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Beacon-Operation-Key');
        if ($key && ! $request->isMethod('GET')) {
            abort_unless($request->user() && $request->user()->isActive(), 401);
            abort_unless(Str::isUuid($key), 422, 'معرّف العملية غير صالح.');
            abort_unless(in_array($request->route()?->getName(), OfflinePolicy::WRITES, true), 422, 'هذا الإجراء يحتاج اتصالاً مباشراً.');
            abort_unless(hash_equals(OfflinePolicy::scope($request->user()), (string) $request->header('X-Beacon-Offline-Scope')), 403);
            $response = DB::transaction(function () use ($request, $next, $key): Response {
                // Serialize concurrent submissions from multiple tabs/devices. The receipt and
                // business writes commit together, including the "response was lost" case.
                User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $hash = OfflinePolicy::fingerprint($request);
                $existing = DB::table('offline_operations')->where('user_id', $request->user()->id)->where('operation_key', $key)->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->fingerprint, $hash), 409, 'سبق إرسال هذه العملية بقيم مختلفة.');

                    return $request->header('X-Beacon-Offline-Replay') === '1'
                        ? response()->json(['synced' => true, 'operation_key' => $key, 'url' => $existing->result_url, 'versions' => json_decode($existing->versions, true)])
                        : redirect($existing->result_url ?: '/offline');
                }
                foreach ($request->route()->parameters() as $model) {
                    if ($model instanceof Model) {
                        $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
                        $model->refresh();
                    }
                }
                if ($request->header('X-Beacon-Offline-Replay') === '1') {
                    $expected = json_decode((string) $request->input('_beacon_versions', $request->header('X-Beacon-Offline-Versions', '{}')), true);
                    abort_unless(is_array($expected), 409, 'أعد فتح السجل وراجع التغيير قبل إرساله.');
                    // Creation appends new records; editing existing records must match the
                    // state seen on the device. Server policies/validation still run below.
                    $creates = ['enterprises.store', 'enterprises.transactions.store', 'enterprises.documents.store', 'enterprises.advisory.store', 'enterprises.follow-ups.store', 'enterprises.readiness.store', 'enterprises.referrals.store'];
                    if (! in_array($request->route()->getName(), $creates, true)) {
                        foreach (OfflinePolicy::versions($request) as $resource => $version) {
                            abort_unless(isset($expected[$resource]) && hash_equals($version, (string) $expected[$resource]), 409, 'تغير هذا السجل على الخادم. افتحه وراجع البيانات قبل إعادة الإرسال.');
                        }
                    }
                }
                $result = $next($request);
                if ($result->getStatusCode() < 400) {
                    $url = $result->headers->get('Location');
                    foreach ($request->route()->parameters() as $model) {
                        if ($model instanceof Model) {
                            $model->refresh();
                        }
                    }
                    $versions = OfflinePolicy::versions($request, true);
                    DB::table('offline_operations')->insert(['user_id' => $request->user()->id, 'operation_key' => $key, 'fingerprint' => $hash, 'result_url' => $url, 'versions' => json_encode($versions, JSON_THROW_ON_ERROR), 'created_at' => now()]);
                    if ($request->header('X-Beacon-Offline-Replay') === '1') {
                        return response()->json(['synced' => true, 'operation_key' => $key, 'url' => $url, 'versions' => $versions]);
                    }
                }

                return $result;
            });
        } else {
            $response = $next($request);
        }
        if ($request->user() && $request->user()->isActive() && $response->getStatusCode() < 400) {
            $response->headers->set('X-Beacon-Offline-Scope', OfflinePolicy::scope($request->user()));
            if ($request->isMethod('GET')) {
                $response->headers->set('X-Beacon-Authorization', OfflinePolicy::authorization($request->user()));
                $response->headers->set('X-Beacon-Offline-Versions', json_encode(OfflinePolicy::versions($request), JSON_THROW_ON_ERROR));
                $response->headers->set('X-Beacon-Offline-Writes', implode(',', OfflinePolicy::WRITES));
            }
        }

        return $response;
    }
}
