<?php

namespace App\Http\Controllers\Operations;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Section 53 "session management": every user can see and end their own other sessions. */
class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        $sessions = $this->usesDatabase()
            ? DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get()
                ->map(fn ($s) => [
                    'is_current' => $s->id === $request->session()->getId(),
                    'ip_address' => $s->ip_address,
                    'user_agent' => mb_substr((string) $s->user_agent, 0, 200),
                    'last_activity' => date('Y-m-d H:i', (int) $s->last_activity),
                ])
            : collect();

        return Inertia::render('Operations/Sessions', [
            'sessions' => $sessions->values(),
            'supported' => $this->usesDatabase(),
        ]);
    }

    public function destroyOthers(Request $request, RecordAuditLog $audit): RedirectResponse
    {
        abort_unless($this->usesDatabase(), 422, 'إدارة الجلسات تتطلب مخزن جلسات قاعدة البيانات.');
        $user = $request->user();
        $count = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
        $audit->record($user, 'security.sessions_revoked', $user, ['count' => $count]);

        return redirect()->route('account.sessions')->with('success', 'تم إنهاء الجلسات الأخرى.');
    }

    private function usesDatabase(): bool
    {
        return config('session.driver') === 'database';
    }
}
