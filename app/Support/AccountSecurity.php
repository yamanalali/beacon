<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountSecurity
{
    public const SESSION_USER = 'security.user_id';

    public const SESSION_STAMP = 'security.auth_stamp';

    /** No secret value is exposed: only an application-keyed fingerprint enters a session. */
    public static function stamp(User $user): string
    {
        $fields = ['security_stamp', 'password', 'role', 'is_active', 'access_permissions', 'mfi_institution_id', 'two_factor_secret', 'two_factor_confirmed_at'];
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $user->getRawOriginal($field);
        }

        $values['is_active'] = $user->isActive();
        $values['mfi_institution_id'] = $user->mfi_institution_id === null ? null : (int) $user->mfi_institution_id;
        $values['access_permissions'] = $user->access_permissions;
        if (is_array($values['access_permissions'])) {
            ksort($values['access_permissions']);
        }

        return hash_hmac('sha256', json_encode([$user->id, $values], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public static function canonicalEmail(string $email): ?string
    {
        $matches = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->limit(2)->pluck('email');

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public static function bind(Request $request, User $user, bool $rotate = false): void
    {
        if ($rotate) {
            $request->session()->regenerate(true);
            $request->session()->regenerateToken();
        }
        $request->session()->put([self::SESSION_USER => $user->id, self::SESSION_STAMP => self::stamp($user)]);
    }

    /** Lock administrators in a stable order before changing another account. Call inside a transaction. */
    public static function administrationPair(User $actor, User $target): array
    {
        $users = User::query()->where(fn ($q) => $q->where('role', UserRole::BeaconAdmin->value)->orWhereIn('id', [$actor->id, $target->id]))
            ->orderBy('id')->lockForUpdate()->get();
        $actor = $users->firstWhere('id', $actor->id);
        $target = $users->firstWhere('id', $target->id);
        abort_unless($actor && $target && $actor->isActive() && $actor->role === UserRole::BeaconAdmin && ! $actor->is($target), 403);

        return [$actor, $target, $users->filter(fn ($user) => $user->isActive() && $user->role === UserRole::BeaconAdmin)->count()];
    }

    public static function revokeDatabaseSessions(User $user, ?string $except = null): void
    {
        $connection = DB::connection(config('session.connection'));
        $table = config('session.table', 'sessions');
        if (Schema::connection(config('session.connection'))->hasTable($table)) {
            $query = $connection->table($table)->where('user_id', $user->id);
            if ($except !== null) {
                $query->where('id', '!=', $except);
            }
            $query->delete();
        }
    }
}
