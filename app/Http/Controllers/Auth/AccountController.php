<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Support\AccountSecurity;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function show(): Response
    {
        $user = request()->user();

        return Inertia::render('Account/Index', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => $user->role->label(),
            ],
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        AccountSecurity::revokeDatabaseSessions($request->user(), $request->session()->getId());
        AccountSecurity::bind($request, $request->user()->fresh(), true);
        app(RecordAuditLog::class)->record($request->user(), 'security.password_changed', $request->user());

        return back()->with('status', 'تم تحديث كلمة المرور وإنهاء الجلسات الأخرى.');
    }
}
