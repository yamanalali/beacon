<?php

namespace App\Http\Middleware;

use BackedEnum;
use Illuminate\Http\Request;
use Inertia\Middleware;
use UnitEnum;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'auth' => [
                'user' => $this->sharedUser($request),
            ],
        ];
    }

    /**
     * @return array{name: string, role: string|null}|null
     */
    private function sharedUser(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'name' => (string) data_get($user, 'name', ''),
            'role' => $this->sharedRole(data_get($user, 'role')),
        ];
    }

    private function sharedRole(mixed $role): ?string
    {
        if ($role instanceof BackedEnum) {
            $value = $role->value;

            return is_string($value) || is_int($value) ? (string) $value : null;
        }

        if ($role instanceof UnitEnum) {
            return $role->name;
        }

        if (is_string($role) || is_int($role)) {
            return (string) $role;
        }

        return null;
    }
}
