<?php

namespace App\Http\Controllers\Notifications;

use App\Ai\Support\PaginatedProps;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A user only ever sees and updates their own notifications. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isActive(), 403);

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $request->user()->notifications()->latest()->paginate(20)
                ->through(fn ($n) => ['id' => $n->id, 'data' => $n->data, 'read_at' => $n->read_at, 'created_at' => $n->created_at]),
        ]);
    }

    public function page(Request $request): Response
    {
        abort_unless($request->user()->isActive(), 403);
        $q = $request->user()->notifications()->latest();
        if ($request->query('filter') === 'unread') {
            $q->whereNull('read_at');
        }

        return Inertia::render('Notifications/Index', [
            'notifications' => PaginatedProps::from($q->paginate(20)->withQueryString()
                ->through(fn ($n) => ['id' => $n->id, 'data' => $n->data, 'read_at' => $n->read_at?->toIso8601String(), 'created_at' => $n->created_at?->toIso8601String()])),
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'filters' => ['filter' => $request->query('filter')],
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        abort_unless($request->user()->isActive(), 403);
        $n = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $n->markAsRead();

        return response()->json(['id' => $n->id, 'read_at' => $n->read_at]);
    }
}
