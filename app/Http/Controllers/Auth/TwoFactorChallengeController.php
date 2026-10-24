<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountSecurity;
use App\Support\TwoFactor\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public const SESSION_USER = 'mfa.login.user_id';

    public const SESSION_STAMP = 'mfa.login.security_stamp';

    public const SESSION_EXPIRES = 'mfa.login.expires_at';

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly RecordAuditLog $audit,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $response = DB::transaction(function () use ($request): RedirectResponse|ValidationException {
            $user = $this->pendingUser($request, true);

            if ($user === null) {
                return redirect()->route('login');
            }

            $limitKey = 'mfa-challenge:'.$user->id;
            if (RateLimiter::tooManyAttempts($limitKey, 10)) {
                return ValidationException::withMessages(['code' => 'محاولات كثيرة. انتظر دقيقة ثم حاول مجدداً.']);
            }

            $request->validate([
                'code' => ['nullable', 'string', 'max:32', 'required_without:recovery_code'],
                'recovery_code' => ['nullable', 'string', 'max:64'],
            ]);

            if ($request->filled('recovery_code')) {
                if (! $this->twoFactor->useRecoveryCode($user, $request->string('recovery_code')->toString())) {
                    RateLimiter::hit($limitKey, 60);
                    $this->audit->record($user, 'mfa.challenge_failed', $user, ['method' => 'recovery_code']);

                    return ValidationException::withMessages(['recovery_code' => 'رمز الاسترداد غير صحيح.']);
                }

                $this->audit->record($user, 'mfa.recovery_code_used', $user, [
                    'remaining' => $this->twoFactor->remainingRecoveryCodes($user),
                ]);
            } elseif (! $this->twoFactor->verifyCode($user, $request->string('code')->toString())) {
                RateLimiter::hit($limitKey, 60);
                $this->audit->record($user, 'mfa.challenge_failed', $user, ['method' => 'totp']);

                return ValidationException::withMessages(['code' => 'رمز التحقق غير صحيح.']);
            }

            RateLimiter::clear($limitKey);
            $request->session()->forget([self::SESSION_USER, self::SESSION_EXPIRES, self::SESSION_STAMP]);
            Auth::login($user);
            AccountSecurity::bind($request, $user->fresh(), true);

            return redirect()->intended(Route::has('home') ? route('home') : '/');
        });
        // Commit failed-attempt audit/limiter writes too, including database cache stores.
        if ($response instanceof ValidationException) {
            throw $response;
        }

        return $response;
    }

    private function pendingUser(Request $request, bool $lock = false): ?User
    {
        $id = $request->session()->get(self::SESSION_USER);
        $expires = (int) $request->session()->get(self::SESSION_EXPIRES, 0);

        if ($id === null || $expires < now()->timestamp) {
            $request->session()->forget([self::SESSION_USER, self::SESSION_EXPIRES, self::SESSION_STAMP]);

            return null;
        }

        $user = User::query()->when($lock, fn ($query) => $query->lockForUpdate())->find($id);

        if (! $user instanceof User || ! $user->isActive() || ! $this->twoFactor->challengeRequired($user) || ! hash_equals(AccountSecurity::stamp($user), (string) $request->session()->get(self::SESSION_STAMP, ''))) {
            $request->session()->forget([self::SESSION_USER, self::SESSION_EXPIRES, self::SESSION_STAMP]);

            return null;
        }

        return $user;
    }
}
