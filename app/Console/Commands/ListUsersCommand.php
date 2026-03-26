<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsersCommand extends Command
{
    protected $signature = 'users:list
                            {--role= : Filter by role name}
                            {--lancore : Show only LanCore users}
                            {--local : Show only local (non-LanCore) users}';

    protected $description = 'List all users with their roles and LanCore status';

    public function handle(): int
    {
        $query = User::with('roles');

        if ($role = $this->option('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if ($this->option('lancore')) {
            $query->whereNotNull('lancore_user_id');
        } elseif ($this->option('local')) {
            $query->whereNull('lancore_user_id');
        }

        $users = $query->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->info('No users found.');

            return self::SUCCESS;
        }

        $rows = $users->map(fn (User $user) => [
            $user->id,
            $user->name,
            $user->email ?? '—',
            $user->roles->pluck('name')->implode(', ') ?: '—',
            $user->lancore_user_id ? "LC#{$user->lancore_user_id}" : 'local',
            $user->lancore_synced_at?->diffForHumans() ?? '—',
        ]);

        $this->table(
            ['ID', 'Name', 'Email', 'Roles', 'Source', 'Last Sync'],
            $rows,
        );

        $this->newLine();
        $this->info("Total: {$users->count()} user(s)");

        return self::SUCCESS;
    }
}
