<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $user->notifications()->latest()->paginate(25),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(8)->get();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(fn (DatabaseNotification $notification): array => $this->serialize($notification)),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ownedNotification = $user->notifications()->findOrFail($notification);
        $ownedNotification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['unread_count' => $user->unreadNotifications()->count()]);
        }

        return back();
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['unread_count' => 0]);
        }

        return back();
    }

    /** @return array{id: string, title: string, message: string, severity: string, url: string, read: bool, created_at: string} */
    private function serialize(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => (string) ($notification->data['title'] ?? 'Notificación'),
            'message' => (string) ($notification->data['message'] ?? ''),
            'severity' => (string) ($notification->data['severity'] ?? 'info'),
            'url' => (string) ($notification->data['url'] ?? route('dashboard', absolute: false)),
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }
}
