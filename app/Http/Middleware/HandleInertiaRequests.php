<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Navigation\NavigationBuilder;
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
            'offline_versions' => fn () => $request->user() ? \App\Support\OfflinePolicy::versions($request, true) : [],
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'auth' => [
                'user' => $this->sharedUser($request),
                // One boolean per sidebar key (NavigationBuilder: route middleware + policy/gate), plus record_transaction.
                'can' => [
                    ...$this->sidebarCan($request),
                    'record_transaction' => fn () => $this->quickRecord($request) !== null,
                ],
                'quick_record' => fn () => $this->quickRecord($request),
                'navigation' => fn () => app(NavigationBuilder::class)->build($request->user(), $request),
                // System-wide in-app inbox preview for header badge/menu (all notification types).
                'notifications' => fn () => $this->sharedNotifications($request),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'invitation_link' => fn () => $request->session()->get('invitation_link'),
                'created_document_id' => fn () => $request->session()->get('created_document_id'),
            ],
        ];
    }

    /**
     * @return array{name: string, role: string|null}|null
     */
    /** @var array{enterprise_public_id: string, upload_url: string, voice_url: ?string, manual_url: string}|null|false */
    private array|null|false $quickRecord = false;

    /**
     * Quick "record transaction" targets for the user's own enterprise, gated by the
     * same transaction create policy as the transaction store route.
     *
     * @return array{enterprise_public_id: string, upload_url: string, voice_url: ?string, manual_url: string}|null
     */
    private function quickRecord(Request $request): ?array
    {
        if ($this->quickRecord !== false) {
            return $this->quickRecord;
        }

        $user = $request->user();
        $enterprise = $user && data_get($user, 'role') === UserRole::SmeUser
            ? Enterprise::query()->whereHas('members', fn ($q) => $q->whereKey($user->id))->first()
            : null;

        if ($enterprise === null || ! $user->can('create', [FinancialTransaction::class, $enterprise])) {
            return $this->quickRecord = null;
        }

        return $this->quickRecord = [
            'enterprise_public_id' => $enterprise->public_id,
            'upload_url' => route('enterprises.documents.store', ['enterprise' => $enterprise, 'extract' => 1]),
            'voice_url' => config('ai.voice.enabled') ? route('ai.voice-drafts.store', $enterprise) : null,
            'manual_url' => route('enterprises.transactions.create', $enterprise),
        ];
    }

    /** @var array<string, bool>|null */
    private ?array $sidebarAbilities = null;

    /**
     * Lazy `auth.can.<sidebar key>` flags, evaluated once per request from NavigationBuilder.
     *
     * @return array<string, \Closure(): bool>
     */
    private function sidebarCan(Request $request): array
    {
        $builder = app(NavigationBuilder::class);
        $flags = [];

        foreach ($builder->definitions() as $item) {
            $key = $item['key'];
            $flags[$key] = fn (): bool => ($this->sidebarAbilities ??= $builder->abilities($request->user()))[$key] ?? false;
        }

        return $flags;
    }

    private function sharedUser(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'draft_scope' => hash_hmac('sha256', 'offline-user:'.$user->id, (string) config('app.key')),
            'name' => (string) data_get($user, 'name', ''),
            'role' => $this->sharedRole(data_get($user, 'role')),
        ];
    }

    /**
     * Unread count + latest rows for the shell badge/menu. Own notifications only.
     *
     * @return array{unread_count: int, recent: list<array{id: string, data: array<string, mixed>, read_at: ?string, created_at: ?string}>}|null
     */
    private function sharedNotifications(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null || ! $user->isActive()) {
            return null;
        }

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->latest()->limit(5)->get()->map(fn ($n) => [
                'id' => (string) $n->id,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all(),
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
