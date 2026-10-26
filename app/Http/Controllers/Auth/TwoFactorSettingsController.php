<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Support\AccountSecurity;
use App\Support\TwoFactor\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorSettingsController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly RecordAuditLog $audit,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $pending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;

        return Inertia::render('Account/TwoFactor', [
            'mfa' => [
                'confirmed' => $user->hasConfirmedTwoFactor(),
                'pending' => $pending,
                'qr_svg' => $pending ? $this->twoFactor->qrCodeSvg($user, $user->two_factor_secret) : null,
                'setup_key' => $pending ? $user->two_factor_secret : null,
                'recovery_codes_remaining' => $this->twoFactor->remainingRecoveryCodes($user),
            ],
            'recovery_codes' => $request->session()->get('mfa_new_recovery_codes'),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $this->ensurePassword($request);
        $user = $request->user();

        if (! $user->hasConfirmedTwoFactor()) {
            $user->forceFill([
                'two_factor_secret' => $this->twoFactor->generateSecret(),
                'two_factor_confirmed_at' => null,
                'two_factor_last_timestep' => null,
            ])->save();
        }

        AccountSecurity::revokeDatabaseSessions($user, $request->session()->getId());
        AccountSecurity::bind($request, $user->fresh(), true);

        return redirect()->route('two-factor.show');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:32']]);
        $user = $request->user();

        if ($user->hasConfirmedTwoFactor() || $user->two_factor_secret === null) {
            return redirect()->route('two-factor.show');
        }

        if (! $this->twoFactor->verifyCode($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'رمز التحقق غير صحيح.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        AccountSecurity::revokeDatabaseSessions($user, $request->session()->getId());
        AccountSecurity::bind($request, $user->fresh(), true);
        $this->audit->record($user, 'mfa.enrolled', $user, ['factor' => 'totp']);

        return redirect()->route('two-factor.show')->with('mfa_new_recovery_codes', $codes);
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $this->ensurePassword($request);
        $user = $request->user();
        abort_unless($user->hasConfirmedTwoFactor(), 404);

        $codes = $this->twoFactor->regenerateRecoveryCodes($user);
        AccountSecurity::revokeDatabaseSessions($user, $request->session()->getId());
        AccountSecurity::bind($request, $user->fresh(), true);
        $this->audit->record($user, 'mfa.recovery_codes_regenerated', $user);

        return redirect()->route('two-factor.show')->with('mfa_new_recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->ensurePassword($request);
        $user = $request->user();
        $wasConfirmed = $user->hasConfirmedTwoFactor();

        $this->twoFactor->disable($user);
        AccountSecurity::revokeDatabaseSessions($user, $request->session()->getId());
        AccountSecurity::bind($request, $user->fresh(), true);

        if ($wasConfirmed) {
            $this->audit->record($user, 'mfa.disabled', $user, ['by' => 'self']);
        }

        return redirect()->route('two-factor.show')->with('status', 'تم إيقاف التحقق بخطوتين.');
    }

    private function ensurePassword(Request $request): void
    {
        $request->validate(['current_password' => ['required', 'string']]);

        if (! Hash::check($request->string('current_password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'كلمة المرور غير صحيحة.']);
        }
    }
}
