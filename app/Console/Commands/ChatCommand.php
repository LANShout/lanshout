<?php

namespace App\Console\Commands;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use App\Services\ContentModeration;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ChatCommand extends Command
{
    protected $signature = 'chat:send
                            {--user= : User ID to send as}
                            {--system : Send as [System] (no user)}
                            {--message= : Message body (skips interactive prompt)}';

    protected $description = 'Send chat messages from the console and view recent messages';

    public function handle(ContentModeration $moderation): int
    {
        $isSystem = $this->option('system');
        $user = $isSystem ? null : $this->resolveUser();

        if (! $isSystem && ! $user) {
            $this->error('No users found. Create a user first.');

            return self::FAILURE;
        }

        $label = $isSystem ? '[System]' : "{$user->name} (ID: {$user->id})";
        $this->info("Chatting as: {$label}");
        $this->newLine();

        $this->showRecentMessages();

        if ($body = $this->option('message')) {
            $this->sendMessage($user, $body, $moderation);

            return self::SUCCESS;
        }

        // Interactive loop
        $this->line('<fg=gray>Type a message and press Enter. Type "quit" to exit, "history" to refresh.</>');
        $this->newLine();

        $promptLabel = $isSystem ? '[System]' : $user->name;

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

            $this->sendMessage($user, $body, $moderation);
        }

        return self::SUCCESS;
    }

    protected function resolveUser(): ?User
    {
        if ($userId = $this->option('user')) {
            return User::find($userId);
        }

        $users = User::orderBy('id')->limit(20)->get();

        if ($users->isEmpty()) {
            return null;
        }

        if ($users->count() === 1) {
            return $users->first();
        }

        $choices = $users->mapWithKeys(fn (User $u) => [
            $u->id => "{$u->name} (#{$u->id})".($u->isLanCoreUser() ? ' [LC]' : ''),
        ])->all();

        $selectedId = select(
            label: 'Send messages as which user?',
            options: $choices,
        );

        return $users->firstWhere('id', $selectedId);
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

    protected function sendMessage(?User $user, string $body, ContentModeration $moderation): void
    {
        $sanitized = $moderation->sanitize($body);

        $message = Message::create([
            'user_id' => $user?->id,
            'body' => $sanitized,
        ]);

        $message->load('user:id,name,chat_color');

        MessageSent::dispatch($message);

        $this->line("<fg=green>✓</> Message sent: {$sanitized}");
    }
}
