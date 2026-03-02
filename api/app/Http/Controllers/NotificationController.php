<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->query('limit', 20);
            $notifications = $request->user()->notifications()->latest()->limit($limit)->get();

            return response()->json($notifications->map(function ($n) {
                // Defensive check: ensure data is an array
                $data = $n->data;
                if (is_string($data)) {
                    $data = json_decode($data, true) ?? [];
                }

                return [
                    'id'         => $n->id,
                    'type'       => $data['type'] ?? 'alert',
                    'title'      => $data['title'] ?? 'Notification',
                    'message'    => $data['message'] ?? '',
                    'read'       => !is_null($n->read_at),
                    'created_at' => $n->created_at->toIso8601String(),
                    'time'       => $n->created_at->diffForHumans(),
                ];
            }));
        } catch (\Exception $e) {
            \Log::error('Notifications Fetch Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Mark a specific notification as read.
     */
    public function markRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }
}
