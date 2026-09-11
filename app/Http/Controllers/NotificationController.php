<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return NotificationResource::collection(
            $request->user()->notifications()->paginate()
        );
    }

    public function markRead(Request $request, string $notification)
    {
        // Scoped through the current user's own relation — never a global
        // lookup — so one user can never mark another user's notification.
        $notif = $request->user()->notifications()->findOrFail($notification);
        $notif->markAsRead();

        return response()->json(['data' => ['message' => 'Notification marked as read.']]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['data' => ['message' => 'All notifications marked as read.']]);
    }
}
