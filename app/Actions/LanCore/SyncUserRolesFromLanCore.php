<?php

namespace App\Actions\LanCore;

use App\Models\Role;
use App\Models\User;
use App\Services\LanCore\Exceptions\LanCoreDisabledException;
use App\Services\LanCore\Exceptions\LanCoreRequestException;
use App\Services\LanCore\LanCoreClient;
use App\Services\LanCore\LanCoreUser;
use Illuminate\Support\Facades\Log;

class SyncUserRolesFromLanCore
{
    /**
     * Local role names that LanCore is authoritative over.
     *
     * @var list<string>
     */
    private const MANAGED_ROLES = ['user', 'admin', 'moderator', 'super_admin'];

    /**
     * Mapping from LanCore role names to local role names.
     *
     * @var array<string, string>
     */
    private const ROLE_MAP = [
        'superadmin' => 'super_admin',
    ];

    public function __construct(private LanCoreClient $client) {}

    /**
     * Sync LanCore roles to the local user record.
     *
     * If a LanCoreUser DTO is provided (e.g. from an SSO exchange response),
     * its roles are used directly and no additional API request is made.
     * Otherwise, LanCore is queried using the user's lancore_user_id or email.
     *
     * Roles not in MANAGED_ROLES are never modified (local-only roles are preserved).
     * On 404 the user is treated as having no LanCore roles (all managed roles removed).
     * On any other error the failure is logged and local roles are left unchanged.
     */
    public function execute(User $user, ?LanCoreUser $lanCoreUser = null): void
    {
        if ($lanCoreUser === null) {
            try {
                $lanCoreUser = $this->fetchLanCoreUser($user);
            } catch (LanCoreDisabledException) {
                return;
            } catch (LanCoreRequestException $e) {
                if ($e->getCode() === 404) {
                    $this->syncRoles($user, []);

                    return;
                }

                Log::error('Failed to fetch LanCore roles; local roles unchanged.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'status' => $e->getCode(),
                ]);

                return;
            } catch (\Throwable $e) {
                Log::error('Unexpected error syncing LanCore roles; local roles unchanged.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        if ($lanCoreUser === null) {
            // Cannot identify user in LanCore (no lancore_user_id or email).
            return;
        }

        if ($lanCoreUser->roles === null) {
            // Roles scope not granted — cannot determine roles, leave unchanged.
            return;
        }

        $this->syncRoles($user, $this->mapRoles($lanCoreUser->roles));
    }

    /**
     * @param  list<string>  $lanCoreRoles
     * @return list<string>
     */
    private function mapRoles(array $lanCoreRoles): array
    {
        return array_values(array_map(
            fn (string $role) => self::ROLE_MAP[$role] ?? $role,
            $lanCoreRoles,
        ));
    }

    /**
     * @param  list<string>  $lanCoreLocalRoleNames
     */
    private function syncRoles(User $user, array $lanCoreLocalRoleNames): void
    {
        $currentRoles = Role::whereIn('id', $user->roles()->pluck('roles.id'))->get();

        $localOnlyRoleIds = $currentRoles
            ->whereNotIn('name', self::MANAGED_ROLES)
            ->pluck('id');

        $lanCoreRoleIds = Role::whereIn('name', $lanCoreLocalRoleNames)->pluck('id');

        $newRoleIds = $localOnlyRoleIds->merge($lanCoreRoleIds)->unique()->values();

        $user->roles()->sync($newRoleIds);
    }

    private function fetchLanCoreUser(User $user): ?LanCoreUser
    {
        if ($user->lancore_user_id !== null) {
            return $this->client->resolveUserById($user->lancore_user_id);
        }

        if ($user->email !== null) {
            return $this->client->resolveUserByEmail($user->email);
        }

        return null;
    }
}
