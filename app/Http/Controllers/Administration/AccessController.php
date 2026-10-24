<?php

namespace App\Http\Controllers\Administration;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AccessController extends Controller
{
    public function show(Request $request, User $user)
    {
        return Inertia::render('Administration/Users/Access', ['target' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->label(), 'permissions' => $user->effectivePermissions(), 'custom' => $user->access_permissions !== null], 'canEdit' => ! $user->is($request->user())]);
    }

    public function update(Request $request, User $user, RecordAuditLog $audit)
    {
        abort_if($user->is($request->user()), 403);
        $data = $request->validate(['permissions' => ['present', 'nullable', 'array:view,record,approve,export,require_approval'],
            'permissions.view' => ['required_with:permissions', 'boolean'], 'permissions.record' => ['required_with:permissions', 'boolean'],
            'permissions.approve' => ['required_with:permissions', 'boolean'], 'permissions.export' => ['required_with:permissions', 'boolean'], 'permissions.require_approval' => ['required_with:permissions', 'boolean']]);
        DB::transaction(function () use ($request, $user, $audit, $data) {
            [, $locked] = AccountSecurity::administrationPair($request->user(), $user);
            $before = $locked->access_permissions;
            $locked->access_permissions = $data['permissions'];
            $locked->save();
            AccountSecurity::revokeDatabaseSessions($locked);
            $audit->record($request->user(), 'user.permissions_updated', $locked, ['before' => $before, 'after' => $data['permissions']]);
        });

        return back()->with('success', 'تم تحديث الصلاحيات وإنهاء الجلسات السابقة.');
    }
}
