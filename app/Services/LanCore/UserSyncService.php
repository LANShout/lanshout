<?php

namespace App\Services\LanCore;

use App\Models\User;
use App\Services\LanCore\Exceptions\InvalidLanCoreUserException;
use Illuminate\Support\Facades\Log;

class UserSyncService
{
    public function __construct(
        private LanCoreClient $client,
    ) {}

    public function resolveFromUpstream(LanCoreUser $lanCoreUser): User
    {
        if (! $lanCoreUser->isValid()) {
            throw new InvalidLanCoreUserException;
        }

        $user = User::where('lancore_user_id', $lanCoreUser->id)->first();

        if ($user) {
            return $this->updateShadow($user, $lanCoreUser);
        }

        return $this->createShadow($lanCoreUser);
    }

    public function refreshFromLanCore(User $user): User
    {
        if (! $user->isLanCoreUser()) {
            return $user;
        }

        $lanCoreUser = $this->client->resolveUserById($user->lancore_user_id);

        if (! $lanCoreUser) {
            Log::warning('Could not refresh user from LanCore.', [
                'user_id' => $user->id,
                'lancore_user_id' => $user->lancore_user_id,
            ]);

            return $user;
        }

        return $this->updateShadow($user, $lanCoreUser);
    }

    protected function createShadow(LanCoreUser $lanCoreUser): User
    {
        Log::info('Creating local shadow user from LanCore.', [
            'lancore_user_id' => $lanCoreUser->id,
            'username' => $lanCoreUser->username,
        ]);

        $attributes = [
            'lancore_user_id' => $lanCoreUser->id,
            'name' => $lanCoreUser->username,
            'display_name' => $lanCoreUser->username,
            'avatar_url' => $lanCoreUser->avatar,
            'locale' => $lanCoreUser->locale,
            'lancore_synced_at' => now(),
        ];

        if ($lanCoreUser->email !== null) {
            $attributes['email'] = $lanCoreUser->email;
        }

        $user = User::create($attributes);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user->refresh();
    }

    protected function updateShadow(User $user, LanCoreUser $lanCoreUser): User
    {
        $attributes = [
            'name' => $lanCoreUser->username,
            'display_name' => $user->display_name ?: $lanCoreUser->username,
            'avatar_url' => $lanCoreUser->avatar,
            'locale' => $lanCoreUser->locale,
            'lancore_synced_at' => now(),
        ];

        if ($lanCoreUser->email !== null) {
            $attributes['email'] = $lanCoreUser->email;
        }

        $user->update($attributes);

        Log::debug('Updated local shadow user from LanCore.', [
            'user_id' => $user->id,
            'lancore_user_id' => $lanCoreUser->id,
        ]);

        return $user->refresh();
    }
}
