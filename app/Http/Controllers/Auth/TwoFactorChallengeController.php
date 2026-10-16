<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactor\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public const SESSION_USER = 'mfa.login.user_id';

    public const SESSION_EXPIRES = 'mfa.login.expires_at';

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly RecordAuditLog $audit,
    ) {
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['nullable', 'string', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if ($request->filled('recovery_code')) {
            if (! $this->twoFactor->useRecoveryCode($user, $request->string('recovery_code')->toString())) {
                $this->audit->record($user, 'mfa.challenge_failed', $user, ['method' => 'recovery_code']);
                throw ValidationException::withMessages(['recovery_code' => 'رمز الاسترداد غير صحيح.']);
            }

            $this->audit->record($user, 'mfa.recovery_code_used', $user, [
                'remaining' => $this->twoFactor->remainingRecoveryCodes($user),
            ]);
        } elseif (! $this->twoFactor->verifyCode($user, $request->string('code')->toString())) {
            $this->audit->record($user, 'mfa.challenge_failed', $user, ['method' => 'totp']);
            throw ValidationException::withMessages(['code' => 'رمز التحقق غير صحيح.']);
        }

        $request->session()->forget([self::SESSION_USER, self::SESSION_EXPIRES]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(Route::has('home') ? route('home') : '/');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::SESSION_USER);
        $expires = (int) $request->session()->get(self::SESSION_EXPIRES, 0);

        if ($id === null || $expires < now()->timestamp) {
            $request->session()->forget([self::SESSION_USER, self::SESSION_EXPIRES]);

            return null;
        }

        $user = User::query()->find($id);

        if (! $user instanceof User || ! $user->isActive() || ! $this->twoFactor->challengeRequired($user)) {
            return null;
        }

        return $user;
    }
}
