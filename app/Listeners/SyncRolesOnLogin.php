<?php

namespace App\Listeners;

use App\Actions\LanCore\SyncUserRolesFromLanCore;
use Illuminate\Auth\Events\Login;

class SyncRolesOnLogin
{
    public function __construct(private SyncUserRolesFromLanCore $syncRoles) {}

    /**
     * Handle a successful login event.
     *
     * Skips role sync if the user was just synced via LanCore SSO (within the
     * last 30 seconds), since the SSO callback already synced roles from the
     * exchange response directly.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        if ($user->lancore_synced_at !== null && $user->lancore_synced_at->diffInSeconds(now()) < 30) {
            return;
        }

        $this->syncRoles->execute($user);
    }
}
