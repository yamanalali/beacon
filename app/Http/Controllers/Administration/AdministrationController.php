<?php

namespace App\Http\Controllers\Administration;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Enterprise;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Administration/Index', [
            'stats' => [
                'users_active' => User::query()->where('is_active', true)->count(),
                'users_inactive' => User::query()->where('is_active', false)->count(),
                'enterprises' => Enterprise::query()->count(),
                'audit_last_7_days' => AuditLog::query()->where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role): array => [
                'label' => $role->label(),
                'count' => User::query()->where('role', $role->value)->count(),
            ])->all(),
        ]);
    }
}
