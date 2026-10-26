<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use App\Support\AccountSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(PasswordResetLinkRequest $request): RedirectResponse
    {
        Password::sendResetLink(
            ['email' => AccountSecurity::canonicalEmail($request->validated('email')) ?? '']
        );

        // Identical status, redirect and validation bag for known, unknown and
        // broker-throttled emails. Never enumerate registered accounts.
        return back()->with('status', 'إذا كان البريد مسجلاً، أرسلنا رابط إعادة تعيين كلمة المرور.');
    }
}
