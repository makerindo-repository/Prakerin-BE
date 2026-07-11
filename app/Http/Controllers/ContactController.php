<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Http\Requests\StoreContactReplyRequest;
use App\Mail\ContactFormSubmitted;
use App\Mail\ContactReplyNotification;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Store a newly created contact message (Public).
     */
    public function store(StoreContactMessageRequest $request)
    {
        $data = $request->validated();
        
        // Auto-attach current user_id if logged-in via sanctum
        $user = Auth::guard('sanctum')->user();
        if ($user) {
            $data['user_id'] = $user->id;
        }

        $contactMessage = ContactMessage::create($data);

        // Send email to admin
        try {
            $adminEmail = config('mail.from.address') ?: 'admin@prakerin.id';
            Mail::to($adminEmail)->send(new ContactFormSubmitted($contactMessage));
        } catch (\Exception $e) {
            Log::error('Failed to send contact notification email to admin: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message_id' => $contactMessage->id,
        ], 201);
    }

    /**
     * List all contact messages (Admin only).
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $category = $request->query('category');
        $search = $request->query('search');

        $query = ContactMessage::withCount('replies')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $messages = $query->paginate(20);

        return response()->json($messages);
    }

    /**
     * Show a single contact message with its replies (Admin only).
     */
    public function show(string $id)
    {
        $contactMessage = ContactMessage::with(['replies.repliedBy', 'user'])->find($id);

        if (!$contactMessage) {
            throw new HttpResponseException(response([
                "errors" => "Contact message not found"
            ], 404));
        }

        // Auto-mark message as "read" on first view
        $contactMessage->markAsRead();

        return response()->json([
            'data' => $contactMessage
        ]);
    }

    /**
     * Add reply to a contact message (Admin only).
     */
    public function reply(StoreContactReplyRequest $request, string $id)
    {
        $contactMessage = ContactMessage::find($id);

        if (!$contactMessage) {
            throw new HttpResponseException(response([
                "errors" => "Contact message not found"
            ], 404));
        }

        $data = $request->validated();
        
        // Auto-set replied_by to current admin/staff user
        $data['contact_message_id'] = $contactMessage->id;
        $data['replied_by_id'] = Auth::id(); // since this endpoint is protected by auth:sanctum

        $reply = ContactReply::create($data);

        // Auto-mark message status as "replied"
        $contactMessage->markAsReplied();

        // Send email notification to original sender
        try {
            Mail::to($contactMessage->email)->send(new ContactReplyNotification($contactMessage, $reply));
        } catch (\Exception $e) {
            Log::error('Failed to send contact reply notification to user: ' . $e->getMessage());
        }

        // Load relations for response
        $reply->load('repliedBy');

        return response()->json([
            'success' => true,
            'data' => $reply
        ], 201);
    }

    /**
     * Check replies for user messages by email (Public).
     */
    public function checkReplies(Request $request, string $email)
    {
        // Simple search by email.
        $messages = ContactMessage::with(['replies.repliedBy'])
            ->where('email', $email)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $messages
        ]);
    }
}
