<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            SupportTicket::where('user_id', $request->user()->id)
                ->with(['messages' => function($q) {
                    $q->orderBy('created_at', 'asc');
                }])
                ->latest()
                ->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:low,normal,high',
            'attachment' => 'nullable|file|max:10240', // 10MB max
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $request->subject,
            'priority' => $request->priority,
            'status' => 'open',
        ]);

        $attachmentPath = null;
        $attachmentName = null;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('attachments', 'public');
            $attachmentName = $request->file('attachment')->getClientOriginalName();
        }

        TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'is_admin_reply' => trim($request->user()->role) === 'admin',
        ]);

        // --- NEW: Trigger Dynamic Emails ---
        try {
            $user = $request->user();
            // 1. Send Confirmation to User
            $user->notify(new \App\Notifications\TicketOpenedNotification([
                'user' => ['name' => $user->name],
                'ticket' => [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject
                ],
                'action_url' => url('/app/tickets/' . $ticket->id),
                'year' => date('Y')
            ]));

            // 2. Alert Admin about new ticket
            $adminEmail = \App\Models\Setting::getValue('admin_notification_email', 'admin@upgradedproxy.com');
            \Illuminate\Support\Facades\Notification::route('mail', $adminEmail)
                ->notify(new \App\Notifications\GenericDynamicNotification('admin_new_ticket', [
                    'user' => ['email' => $user->email],
                    'ticket' => [
                        'subject' => $ticket->subject,
                        'priority' => $ticket->priority
                    ],
                    'admin_url' => url('/admin/tickets/' . $ticket->id),
                    'year' => date('Y')
                ]));
        } catch (\Exception $e) {
            \Log::error("Failed to send support ticket emails: " . $e->getMessage());
        }
        // -----------------------------------

        return response()->json($ticket->load('messages'), 201);
    }

    public function show($id, Request $request)
    {
        $ticket = SupportTicket::with(['messages' => function($q) {
            $q->orderBy('created_at', 'asc');
        }])->findOrFail($id);
        
        // Authorization check
        if ($ticket->user_id != $request->user()->id && trim($request->user()->role) !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($ticket);
    }

    /**
     * Reply to a ticket.
     */
    public function reply(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:10240',
        ]);
        $ticket = SupportTicket::findOrFail($id);

        // Authorization check
        if ($ticket->user_id != $request->user()->id && trim($request->user()->role) !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $attachmentPath = null;
        $attachmentName = null;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('attachments', 'public');
            $attachmentName = $request->file('attachment')->getClientOriginalName();
        }

        $message = TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'is_admin_reply' => trim($request->user()->role) === 'admin',
        ]);

        // Auto-update status if admin replies
        if (trim($request->user()->role) === 'admin' && $ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);

            // --- NEW: Send Reply Notification to User ---
            try {
                $ticketUser = $ticket->user;
                if ($ticketUser) {
                    $ticketUser->notify(new \App\Notifications\SupportReplyNotification([
                        'user' => ['name' => $ticketUser->name],
                        'ticket' => [
                            'id' => $ticket->id,
                            'subject' => $ticket->subject
                        ],
                        'reply_content' => $request->message,
                        'action_url' => url('/app/tickets/' . $ticket->id),
                        'year' => date('Y')
                    ]));
                }
            } catch (\Exception $e) {
                \Log::error("Failed to send support reply email: " . $e->getMessage());
            }
        }

        return response()->json($message);
    }

    /**
     * Admin: List all tickets.
     */
    public function adminIndex()
    {
        return response()->json(
            SupportTicket::with('user:id,name,email')
                ->with(['messages' => function($q) {
                    $q->orderBy('created_at', 'asc');
                }])
                ->latest()
                ->get()
        );
    }

    /**
     * Admin: Update ticket status.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:open,in_progress,resolved,closed']);
        $ticket = SupportTicket::findOrFail($id);

        // Authorization: Admin can do anything, User can only close their own
        if (trim($request->user()->role) !== 'admin' && ($ticket->user_id != $request->user()->id || $request->status !== 'closed')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $ticket->update(['status' => $request->status]);

        return response()->json($ticket);
    }

    /**
     * Delete a ticket (Admin only)
     */
    public function destroy($id, Request $request)
    {
        if (trim($request->user()->role) !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $ticket = SupportTicket::findOrFail($id);
        $ticket->messages()->delete();
        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully']);
    }
}
