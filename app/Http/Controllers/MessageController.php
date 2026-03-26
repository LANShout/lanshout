<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\ChatSetting;
use App\Models\Message;
use App\Services\ContentModeration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MessageController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $messages = Message::with('user:id,name,chat_color')
            ->orderBy('created_at', 'desc') // newest first for pagination
            ->paginate(perPage: (int) $request->integer('per_page', 20));

        return JsonResource::collection($messages);
    }

    public function store(Request $request, ContentModeration $moderation): JsonResource|JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        // Enforce slow mode
        if (ChatSetting::isSlowModeEnabled()) {
            $seconds = ChatSetting::slowModeSeconds();
            $lastMessage = Message::where('user_id', Auth::id())
                ->latest()
                ->first();

            if ($lastMessage && $lastMessage->created_at->diffInSeconds(now()) < $seconds) {
                $remaining = $seconds - $lastMessage->created_at->diffInSeconds(now());

                return response()->json([
                    'message' => "Slow mode is enabled. Please wait {$remaining} seconds.",
                    'remaining_seconds' => $remaining,
                ], 429);
            }
        }

        $result = $moderation->process($validated['body']);

        if ($result['blocked']) {
            return response()->json([
                'message' => 'Your message was blocked by a content filter.',
            ], 422);
        }

        $message = Message::create([
            'user_id' => Auth::id(),
            'body' => $result['body'],
        ]);

        $message->load('user:id,name,chat_color');

        MessageSent::dispatch($message);

        return new JsonResource($message);
    }

    public function page(): InertiaResponse
    {
        $user = Auth::user();
        $lastReadAt = $user?->last_chat_read_at;

        $unreadCount = $lastReadAt
            ? Message::where('created_at', '>', $lastReadAt)->count()
            : Message::count();

        return Inertia::render('Chat', [
            'lastReadAt' => $lastReadAt?->toISOString(),
            'unreadCount' => $unreadCount,
            'isModerator' => $user?->isModerator() ?? false,
        ]);
    }

    public function markRead(): Response
    {
        Auth::user()->update(['last_chat_read_at' => now()]);

        return response()->noContent();
    }
}
