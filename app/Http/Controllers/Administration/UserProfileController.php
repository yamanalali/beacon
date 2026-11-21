<?php

namespace App\Http\Controllers\Administration;

use App\Actions\Auth\UpdateUserProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\UpdateUserProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserProfileController extends Controller
{
    public function update(UpdateUserProfileRequest $request, User $user, UpdateUserProfile $updateUserProfile): RedirectResponse
    {
        $updateUserProfile($request->user(), $user, $request->validated());

        return back()->with('success', ui('flash.user_profile_updated'));
    }
}
