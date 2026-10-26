<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Models\User;
use App\Support\AccountSecurity;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(NewPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            array_replace($request->only('email', 'password', 'password_confirmation', 'token'), ['email' => AccountSecurity::canonicalEmail($request->validated('email')) ?? '']),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                AccountSecurity::revokeDatabaseSessions($user);
                app(RecordAuditLog::class)->record($user, 'security.password_reset', $user);
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'تم تعيين كلمة المرور. يمكنك تسجيل الدخول الآن.');
        }

        throw ValidationException::withMessages([
            'email' => 'رابط إعادة التعيين غير صالح أو انتهت صلاحيته.',
        ]);
    }
}
