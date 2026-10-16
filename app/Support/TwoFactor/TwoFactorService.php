<?php

namespace App\Support\TwoFactor;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function enabled(): bool
    {
        return (bool) config('mfa.enabled');
    }

    public function appliesTo(?User $user): bool
    {
        return $user instanceof User
            && $this->enabled()
            && in_array($user->role?->value, (array) config('mfa.roles', []), true);
    }

    /** A password-verified login must pass the second factor before a session is granted. */
    public function challengeRequired(User $user): bool
    {
        return $this->appliesTo($user) && $user->hasConfirmedTwoFactor();
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl((string) config('mfa.issuer'), $user->email, $secret);

        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    /** Verifies a TOTP code and rejects reuse of an already accepted time step. */
    public function verifyCode(User $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $secret = $locked->two_factor_secret;
            if ($secret === null) {
                return false;
            }
            $timestep = $this->google2fa->verifyKeyNewer(
                $secret, $code, $locked->two_factor_last_timestep ?? 0,
                (int) config('mfa.window', 1),
            );
            if ($timestep === false) {
                return false;
            }
            if ($timestep === true) {
                $timestep = $this->google2fa->getTimestamp();
            }
            $locked->forceFill(['two_factor_last_timestep' => (int) $timestep])->save();
            $user->refresh();

            return true;
        });
    }

    /**
     * Generates new plain recovery codes and stores only their hashes.
     *
     * @return list<string> plain codes, shown to the user once
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $plain = [];

        for ($i = 0; $i < (int) config('mfa.recovery_codes', 8); $i++) {
            $plain[] = Str::upper(Str::random(5).'-'.Str::random(5));
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $c) => Hash::make($c), $plain),
        ])->save();

        return $plain;
    }

    /** Consumes a recovery code; each code works exactly once. */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::upper(trim($code));

        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $hashes = $locked->two_factor_recovery_codes ?? [];
            foreach ($hashes as $index => $hash) {
                if (Hash::check($code, $hash)) {
                    unset($hashes[$index]);
                    $locked->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
                    $user->refresh();

                    return true;
                }
            }

            return false;
        });
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestep' => null,
        ])->save();
    }
}
