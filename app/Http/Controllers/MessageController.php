<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Services\ContentModeration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MessageController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $messages = Message::with('user:id,name,chat_color')
            ->orderBy('created_at') // oldest first (newest last)
            ->paginate(perPage: (int) $request->integer('per_page', 20));

        return JsonResource::collection($messages);
    }

    public function store(Request $request, ContentModeration $moderation): JsonResource|RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        $sanitized = $moderation->sanitize($validated['body']);

        $message = Message::create([
            'user_id' => Auth::id(),
            'body' => $sanitized,
        ]);

        $message->load('user:id,name,chat_color');

        return new JsonResource($message);
    }

    public function page(): InertiaResponse
    {
        return Inertia::render('Chat');
    }
}
