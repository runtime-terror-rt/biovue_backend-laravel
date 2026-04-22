<?php

namespace App\Http\Controllers\Message;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Events\MessageSent;
use App\Models\User;
use App\Models\UserNotificationSetting;
use App\Notifications\ClientMessageNotification;
use App\Notifications\CoachMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string'
        ]);

        $msg = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
        ]);

        if (!$msg) {
            return response()->json(['success' => false, 'message' => 'Failed to save message'], 500);
        }

        broadcast(new MessageSent($msg->load('sender')))->toOthers();

        $receiver = User::find($request->receiver_id);
        $settings = UserNotificationSetting::where('user_id', $receiver->id)->first();

        Log::info('Notification Debug:', [
            'receiver_id' => $receiver->id,
            'user_type' => $receiver->user_type,
            'coach_enabled' => $settings->coach_messages ?? 'Not Set (Default 0)',
            'client_enabled' => $settings->client_messages ?? 'Not Set (Default 0)'
        ]);

        try {
            if ($receiver->user_type === 'individual' && ($settings->coach_messages ?? 0) == 1) {
                $receiver->notify(new CoachMessageNotification('New Coach Message', $request->message, 'coach_message'));
                Log::info("Coach notification sent to User ID: {$receiver->id}");
            } 
            elseif (($settings->client_messages ?? 0) == 1) {
                $receiver->notify(new ClientMessageNotification('New Client Message', $request->message, 'client_message'));
                Log::info("Client notification sent to User ID: {$receiver->id}");
            }
        } catch (\Exception $e) {
            Log::error("Notification process failed for User ID: {$receiver->id}. Error: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => $msg
        ]);
    }

    public function getMessages($id)
    {
        $messages = Message::where(function($q) use ($id) {
            $q->where('sender_id', Auth::id())->where('receiver_id', $id);
        })->orWhere(function($q) use ($id) {
            $q->where('sender_id', $id)->where('receiver_id', Auth::id());
        })
        ->with(['sender', 'receiver'])
        ->orderBy('created_at', 'asc')
        ->get();

        return response()->json($messages);
    }

    public function getConversations()
    {
        $userId = auth()->id();

        $conversations = Message::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($message) use ($userId) {
                return $message->sender_id == $userId ? $message->receiver_id : $message->sender_id;
            });

        return response()->json($conversations);
    }
}
