<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->signature_path || $user->signature_skipped_at) {
            return redirect()->route('home');
        }

        return Inertia::render('Auth/Onboarding', [
            'can_skip' => true,
        ]);
    }

    public function storeSignature(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'signature' => ['nullable', 'file', 'image', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
            'signature_data' => ['nullable', 'string', 'max:600000'],
        ]);

        if (empty($data['signature']) && empty($data['signature_data'])) {
            throw ValidationException::withMessages([
                'signature' => ui('onboarding.signature_required'),
            ]);
        }

        $disk = Storage::disk('local');
        $dir = 'private/signatures/'.$user->id;
        $filename = Str::lower(Str::ulid()).'.png';
        $path = $dir.'/'.$filename;

        if (! empty($data['signature'])) {
            $stored = $data['signature']->storeAs($dir, $filename, 'local');
            $path = $stored;
        } else {
            $raw = (string) $data['signature_data'];
            if (! preg_match('#^data:image/(png|jpeg|jpg|webp);base64,#i', $raw)) {
                throw ValidationException::withMessages([
                    'signature_data' => ui('onboarding.signature_invalid'),
                ]);
            }
            $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $raw), true);
            if ($binary === false || strlen($binary) < 32) {
                throw ValidationException::withMessages([
                    'signature_data' => ui('onboarding.signature_invalid'),
                ]);
            }
            $disk->put($path, $binary);
        }

        if ($user->signature_path && $disk->exists($user->signature_path)) {
            $disk->delete($user->signature_path);
        }

        $user->forceFill([
            'signature_path' => $path,
            'signature_skipped_at' => null,
        ])->save();

        return redirect()->route('home')->with('success', ui('onboarding.signature_saved'));
    }

    public function skip(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'signature_skipped_at' => now(),
        ])->save();

        return redirect()->route('home')->with('info', ui('onboarding.signature_skipped'));
    }
}
