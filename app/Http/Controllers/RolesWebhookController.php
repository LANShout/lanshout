<?php

namespace App\Http\Controllers;

use App\Actions\LanCore\SyncUserRolesFromLanCore;
use App\Http\Requests\StoreRolesWebhookRequest;
use App\Models\User;
use App\Services\LanCore\LanCoreUser;
use Illuminate\Http\JsonResponse;

class RolesWebhookController extends Controller
{
    public function __construct(private SyncUserRolesFromLanCore $syncRoles) {}

    public function __invoke(StoreRolesWebhookRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $lanCoreUserId = $payload['user']['id'];

        $user = User::where('lancore_user_id', $lanCoreUserId)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found locally, ignored.'], 200);
        }

        $lanCoreUser = new LanCoreUser(
            id: $lanCoreUserId,
            username: $payload['user']['username'],
            roles: $payload['user']['roles'],
        );

        $this->syncRoles->execute($user, $lanCoreUser);

        return response()->json(['message' => 'Roles synced.'], 200);
    }
}
