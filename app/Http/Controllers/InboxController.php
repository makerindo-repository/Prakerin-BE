<?php

namespace App\Http\Controllers;

use App\Models\InboxItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InboxController extends Controller
{
    /**
     * GET /api/v1/inbox
     * Returns paginated inbox items for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $items = InboxItem::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $unreadCount = InboxItem::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'message'      => 'Inbox retrieved successfully',
            'data'         => $items->items(),
            'unread_count' => $unreadCount,
            'meta'         => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'total'        => $items->total(),
                'per_page'     => $items->perPage(),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/inbox/{id}/read
     * Mark a single inbox item as read.
     */
    public function markRead(string $id)
    {
        $user = Auth::user();

        $item = InboxItem::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $item->markAsRead();

        return response()->json([
            'message' => 'Marked as read',
            'data'    => $item,
        ]);
    }

    /**
     * PATCH /api/v1/inbox/mark-all-read
     * Mark all inbox items as read for the current user.
     */
    public function markAllRead()
    {
        $user = Auth::user();

        InboxItem::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'All items marked as read',
        ]);
    }

    /**
     * GET /api/v1/inbox/unread-count
     * Fast endpoint for badge counter in the sidebar.
     */
    public function unreadCount()
    {
        $user = Auth::user();

        $count = InboxItem::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}
