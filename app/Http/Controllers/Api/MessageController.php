<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    // Liste des conversations
public function index(Request $request): JsonResponse
{
    $userId = $request->user()->id;

    // Récupérer tous les interlocuteurs uniques
    $sentTo    = Message::where('sender_id', $userId)->pluck('receiver_id');
    $receivedFrom = Message::where('receiver_id', $userId)->pluck('sender_id');

    $otherIds = $sentTo->merge($receivedFrom)->unique()->values();

    $conversations = $otherIds->map(function ($otherId) use ($userId) {
        $other = \App\Models\User::find($otherId);
        if (!$other) return null;

        $lastMessage = Message::where(function ($q) use ($userId, $otherId) {
                $q->where('sender_id', $userId)->where('receiver_id', $otherId);
            })->orWhere(function ($q) use ($userId, $otherId) {
                $q->where('sender_id', $otherId)->where('receiver_id', $userId);
            })
            ->orderBy('created_at', 'desc')
            ->first();

        $unread = Message::where('sender_id', $otherId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();

        return [
            'user' => [
                'id'   => $other->id,
                'name' => $other->name,
                'role' => $other->role,
            ],
            'last_message' => $lastMessage?->content,
            'unread'       => $unread,
            'time'         => $lastMessage?->created_at->diffForHumans(null, true),
        ];
    })->filter()->values();

    return response()->json($conversations);
}
    // Messages d'une conversation
    public function conversation(int $userId, Request $request): JsonResponse
    {
        $myId = $request->user()->id;

        // Marquer comme lus
        Message::where('sender_id', $userId)
            ->where('receiver_id', $myId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        $messages = Message::where(fn($q) =>
            $q->where('sender_id', $myId)->where('receiver_id', $userId)
        )->orWhere(fn($q) =>
            $q->where('sender_id', $userId)->where('receiver_id', $myId)
        )
        ->orderBy('created_at', 'asc')
        ->get()
        ->map(fn($m) => [
            'id'      => $m->id,
            'from'    => $m->sender_id === $myId ? 'me' : 'other',
            'content' => $m->content,
            'time'    => $m->created_at->format('H:i'),
            'is_read' => $m->is_read,
        ]);

        return response()->json($messages);
    }

// Envoyer un message
public function store(Request $request): JsonResponse
{
    $request->validate([
        'receiver_id' => 'required|exists:users,id',
        'content'     => 'required|string|max:1000',
    ]);

    $message = Message::create([
        'sender_id'   => $request->user()->id,
        'receiver_id' => $request->receiver_id,
        'content'     => $request->content,
    ]);

    // 🔴 Diffuser le message via Reverb
    broadcast(new \App\Events\MessageSent($message));

    return response()->json([
        'message' => 'Message envoyé.',
        'data'    => [
            'id'      => $message->id,
            'from'    => 'me',
            'content' => $message->content,
            'time'    => $message->created_at->format('H:i'),
        ],
    ], 201);
}
public function onlineStatus(Request $request): JsonResponse
{
    $ids = explode(',', $request->ids);

    $users = \App\Models\User::whereIn('id', $ids)
        ->get()
        ->map(fn($u) => [
            'id'        => $u->id,
            'is_online' => $u->last_login_at &&
                           $u->last_login_at->gt(now()->subMinutes(5)),
        ]);

    return response()->json($users);
}
}