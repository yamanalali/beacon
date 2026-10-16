<?php

namespace App\Http\Controllers\Administration;

use App\Actions\Auth\ChangeUserRole;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserRoleController extends Controller
{
    public function update(UpdateUserRoleRequest $request, User $user, ChangeUserRole $changeUserRole): RedirectResponse
    {
        $changeUserRole($request->user(), $user, UserRole::from($request->validated('role')));

        return back()->with('success', 'تم تغيير الدور.');
    }
}
