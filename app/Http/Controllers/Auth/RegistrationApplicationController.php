<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RegistrationApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guest self-registration request. Does not create a login account.
 * Beacon reviews applications separately (invitation / admin create).
 */
class RegistrationApplicationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'business_name' => ['required', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $pendingExists = RegistrationApplication::query()
            ->where('email', $data['email'])
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return back()
                ->withInput()
                ->withErrors(['email' => ui('register.pending_exists')]);
        }

        $application = new RegistrationApplication($data);
        $application->status = 'pending';
        $application->save();

        return redirect()
            ->route('register')
            ->with('success', ui('register.submitted'));
    }
}
