<?php

namespace App\Console\Commands;

use App\Events\MessageSent;
use App\Models\Message;
use App\Services\ContentModeration;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

class ChatCommand extends Command
{
    protected $signature = 'chat:send
                            {--message= : Message body (skips interactive prompt)}';

    protected $description = 'Send chat messages from the console as [System] and view recent messages';

    public function handle(ContentModeration $moderation): int
    {
        $this->info('Chatting as: [System]');
        $this->newLine();

        $this->showRecentMessages();

        if ($body = $this->option('message')) {
            $this->sendMessage($body, $moderation);

            return self::SUCCESS;
        }

        // Interactive loop
        $this->line('<fg=gray>Type a message and press Enter. Type "quit" to exit, "history" to refresh.</>');
        $this->newLine();

        $promptLabel = '[System]';

        while (true) {
            $body = text(
                label: $promptLabel,
                placeholder: 'Type your message...',
                required: true,
                hint: '"quit" to exit, "history" to refresh',
            );

            if (strtolower(trim($body)) === 'quit') {
                $this->info('Bye!');

                break;
            }

            if (strtolower(trim($body)) === 'history') {
                $this->showRecentMessages();

                continue;
            }

            $this->sendMessage($body, $moderation);
        }

        return self::SUCCESS;
    }

    protected function showRecentMessages(): void
    {
        $messages = Message::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            $this->line('<fg=gray>No messages yet.</>');
            $this->newLine();

            return;
        }

        $this->line('<fg=yellow>── Recent Messages ──</>');

        foreach ($messages as $msg) {
            $name = $msg->user?->name ?? 'Unknown';
            $time = $msg->created_at->format('H:i');
            $this->line("<fg=gray>[{$time}]</> <fg=cyan>{$name}:</> {$msg->body}");
        }

        $this->line('<fg=yellow>─────────────────────</>');
        $this->newLine();
    }

    protected function sendMessage(string $body, ContentModeration $moderation): void
    {
        $sanitized = $moderation->sanitize($body);

        $message = Message::create([
            'user_id' => null,
            'body' => $sanitized,
        ]);

        $message->load('user:id,name,chat_color');

        MessageSent::dispatch($message);

        $this->line("<fg=green>✓</> Message sent: {$sanitized}");
    }
}
