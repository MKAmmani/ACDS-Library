<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboxMessage;
use App\Models\InboxThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboxController extends Controller
{
    // ── Staff / Admin ────────────────────────────────────────────────────────

    /**
     * GET /admin/inbox?channel=user_to_staff|staff_to_admin&status=open|closed
     */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $channel = $request->get('channel', 'user_to_staff');
        $status  = $request->get('status');

        $query = InboxThread::with([
            'fromUser:id,name,member_number,role',
            'latestMessage.sender:id,name,role',
        ])->where('channel', $channel);

        // Staff only see their own staff_to_admin threads; admin sees all
        if ($channel === 'staff_to_admin' && ! $user->isAdmin()) {
            $query->where('from_user_id', $user->id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return response()->json(
            $query->orderByDesc('last_message_at')->paginate(25)
        );
    }

    /**
     * GET /admin/inbox/{inboxThread}/messages
     */
    public function messages(InboxThread $inboxThread): JsonResponse
    {
        return response()->json(
            $inboxThread->messages()
                ->with('sender:id,name,role')
                ->orderBy('created_at')
                ->get()
        );
    }

    /**
     * POST /admin/inbox/{inboxThread}/reply
     * Staff or admin adds a reply to any thread.
     */
    public function reply(Request $request, InboxThread $inboxThread): JsonResponse
    {
        if ($inboxThread->status === 'closed') {
            return response()->json(['message' => 'Thread is closed.'], 422);
        }

        $data = $request->validate(['body' => ['required', 'string']]);
        $user = $request->user();

        $msg = $this->addMessage($inboxThread, $data['body'], $user->id, $user->role);

        return response()->json($msg->load('sender:id,name,role'), 201);
    }

    /**
     * POST /admin/inbox/compose
     * Staff starts a new staff-to-admin internal thread.
     */
    public function compose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);

        $user = $request->user();

        $thread = DB::transaction(function () use ($data, $user) {
            $t = InboxThread::create([
                'channel'          => 'staff_to_admin',
                'subject'          => $data['subject'],
                'from_user_id'     => $user->id,
                'status'           => 'open',
                'last_sender_role' => $user->role,
                'last_message_at'  => now(),
            ]);

            InboxMessage::create([
                'thread_id'   => $t->id,
                'sender_id'   => $user->id,
                'sender_name' => $user->name,
                'body'        => $data['body'],
            ]);

            return $t;
        });

        return response()->json(
            $thread->load(['fromUser:id,name,role', 'latestMessage.sender:id,name,role']),
            201
        );
    }

    /**
     * POST /admin/inbox/log-query
     * Staff logs a walk-in, phone, or email patron query.
     */
    public function logQuery(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_name'  => ['required', 'string', 'max:255'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'body'       => ['required', 'string'],
            'query_type' => ['sometimes', 'in:reference,acquisition,citation,support,other'],
            'subject'    => ['nullable', 'string', 'max:255'],
        ]);

        $thread = DB::transaction(function () use ($data) {
            $t = InboxThread::create([
                'channel'          => 'user_to_staff',
                'subject'          => $data['subject'] ?? null,
                'query_type'       => $data['query_type'] ?? 'reference',
                'from_user_id'     => null,
                'from_name'        => $data['from_name'],
                'status'           => 'open',
                'last_sender_role' => 'user',
                'last_message_at'  => now(),
            ]);

            InboxMessage::create([
                'thread_id'   => $t->id,
                'sender_id'   => null,
                'sender_name' => $data['from_name'],
                'body'        => $data['body'],
            ]);

            return $t;
        });

        return response()->json($thread->load('latestMessage'), 201);
    }

    /** PATCH /admin/inbox/{inboxThread}/close */
    public function close(InboxThread $inboxThread): JsonResponse
    {
        $inboxThread->update(['status' => 'closed']);
        return response()->json($inboxThread->fresh());
    }

    /** PATCH /admin/inbox/{inboxThread}/open */
    public function open(InboxThread $inboxThread): JsonResponse
    {
        $inboxThread->update(['status' => 'open']);
        return response()->json($inboxThread->fresh());
    }

    /** DELETE /admin/inbox/{inboxThread} */
    public function destroy(InboxThread $inboxThread): JsonResponse
    {
        $inboxThread->delete();
        return response()->json(['message' => 'Thread deleted.']);
    }

    // ── Member-facing ────────────────────────────────────────────────────────

    /** POST /inbox/submit — member starts a new Ask-Librarian thread */
    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject'    => ['nullable', 'string', 'max:255'],
            'body'       => ['required', 'string'],
            'query_type' => ['sometimes', 'in:reference,acquisition,citation,support,other'],
        ]);

        $user = $request->user();

        $thread = DB::transaction(function () use ($data, $user) {
            $t = InboxThread::create([
                'channel'          => 'user_to_staff',
                'subject'          => $data['subject'] ?? null,
                'query_type'       => $data['query_type'] ?? 'reference',
                'from_user_id'     => $user->id,
                'status'           => 'open',
                'last_sender_role' => 'user',
                'last_message_at'  => now(),
            ]);

            InboxMessage::create([
                'thread_id'   => $t->id,
                'sender_id'   => $user->id,
                'sender_name' => $user->name,
                'body'        => $data['body'],
            ]);

            return $t;
        });

        return response()->json(
            $thread->load(['fromUser:id,name', 'latestMessage']),
            201
        );
    }

    /** GET /inbox/threads — member views their own threads */
    public function myThreads(Request $request): JsonResponse
    {
        return response()->json(
            InboxThread::with(['latestMessage.sender:id,name,role'])
                ->where('channel', 'user_to_staff')
                ->where('from_user_id', $request->user()->id)
                ->orderByDesc('last_message_at')
                ->paginate(20)
        );
    }

    /** GET /inbox/threads/{inboxThread}/messages — member reads their thread */
    public function myMessages(Request $request, InboxThread $inboxThread): JsonResponse
    {
        if ($inboxThread->from_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(
            $inboxThread->messages()
                ->with('sender:id,name,role')
                ->orderBy('created_at')
                ->get()
        );
    }

    /** POST /inbox/threads/{inboxThread}/reply — member replies in their thread */
    public function userReply(Request $request, InboxThread $inboxThread): JsonResponse
    {
        $user = $request->user();

        if ($inboxThread->from_user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($inboxThread->status === 'closed') {
            return response()->json(['message' => 'This conversation is closed.'], 422);
        }

        $data = $request->validate(['body' => ['required', 'string']]);

        $msg = $this->addMessage($inboxThread, $data['body'], $user->id, $user->role);

        return response()->json($msg->load('sender:id,name,role'), 201);
    }

    // ── Private helper ───────────────────────────────────────────────────────

    private function addMessage(InboxThread $thread, string $body, ?int $senderId, string $senderRole): InboxMessage
    {
        $senderName = $senderId
            ? \App\Models\User::find($senderId)?->name
            : 'Unknown';

        $msg = InboxMessage::create([
            'thread_id'   => $thread->id,
            'sender_id'   => $senderId,
            'sender_name' => $senderName,
            'body'        => $body,
        ]);

        $thread->update([
            'last_sender_role' => $senderRole,
            'last_message_at'  => now(),
        ]);

        return $msg;
    }
}