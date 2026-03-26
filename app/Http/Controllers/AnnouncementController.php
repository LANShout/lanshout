<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Models\Message;
use Illuminate\Http\Response;

class AnnouncementController extends Controller
{
    public function store(StoreAnnouncementRequest $request): Response
    {
        $data = $request->validated();
        $announcement = $data['announcement'];

        $message = Message::create([
            'user_id' => null,
            'body' => $announcement['title'],
            'type' => 'announcement',
            'priority' => $announcement['priority'],
        ]);

        MessageSent::dispatch($message);

        return response()->noContent();
    }
}
