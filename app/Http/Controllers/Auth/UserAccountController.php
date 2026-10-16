<?php

namespace App\Http\Controllers\Auth;

use App\Ai\Support\PaginatedProps;
use App\Actions\Auth\ActivateUserAccount;
use App\Actions\Auth\CreateUserAccount;
use App\Actions\Auth\DeactivateUserAccount;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActivateUserRequest;
use App\Http\Requests\Auth\DeactivateUserRequest;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TODO: BUSINESS DECISION REQUIRED
 * التحقق بخطوتين للمدراء بانتظار قرار تشغيلي من بيكون.
 * Do not implement TOTP or recovery codes until Beacon approves the operational policy.
 */
class UserAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $like = '%'.addcslashes($search, '%_\\').'%';

        $users = User::query()
            ->when($search !== '', function ($query) use ($like): void {
                $query->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
            ]);

        return Inertia::render('Administration/Users/Index', [
            'users' => PaginatedProps::from($users),
            'search' => $search,
            'currentUserId' => $request->user()->id,
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->values()->all(),
            'mfaNote' => config('mfa.enabled')
                ? 'التحقق بخطوتين مفعّل للمدراء. على كل مدير تفعيله من صفحة التحقق بخطوتين في حسابه.'
                : 'التحقق بخطوتين للمدراء غير مفعّل حالياً بانتظار قرار تشغيلي من بيكون.',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Administration/Users/Create', [
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->values()->all(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAccount $createUserAccount): RedirectResponse
    {
        $createUserAccount($request->user(), $request->validated());

        return redirect()->route('users.index');
    }

    public function deactivate(DeactivateUserRequest $request, User $user, DeactivateUserAccount $deactivateUserAccount): RedirectResponse
    {
        $deactivateUserAccount($request->user(), $user);

        return back();
    }

    public function activate(ActivateUserRequest $request, User $user, ActivateUserAccount $activateUserAccount): RedirectResponse
    {
        $activateUserAccount($request->user(), $user);

        return back();
    }
}
