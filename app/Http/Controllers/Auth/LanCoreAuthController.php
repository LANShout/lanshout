<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LanCore\Exceptions\InvalidLanCoreUserException;
use App\Services\LanCore\Exceptions\LanCoreDisabledException;
use App\Services\LanCore\Exceptions\LanCoreRequestException;
use App\Services\LanCore\LanCoreClient;
use App\Services\LanCore\UserSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LanCoreAuthController extends Controller
{
    public function __construct(
        private LanCoreClient $client,
        private UserSyncService $syncService,
    ) {}

    /**
     * Accept a LanCore user identifier, resolve the user via the
     * integration API, create or update the local shadow, and log them in.
     *
     * This endpoint is designed to be called during a central-login
     * handoff from LanCore. The caller provides a lancore_user_id
     * (preferred) or email to identify the user. LanShout then calls
     * POST /api/integration/user/resolve on LanCore to fetch the
     * scoped user data.
     *
     * The exact browser flow (redirect, callback URL, etc.) will be
     * finalized once LanCore exposes its SSO redirect endpoints.
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'lancore_user_id' => ['required_without:email', 'nullable', 'integer', 'min:1'],
            'email' => ['required_without:lancore_user_id', 'nullable', 'email'],
        ]);

        try {
            $lanCoreUser = $request->filled('lancore_user_id')
                ? $this->client->resolveUserById((int) $request->input('lancore_user_id'))
                : $this->client->resolveUserByEmail($request->input('email'));

            if (! $lanCoreUser) {
                return response()->json([
                    'message' => 'Unable to resolve user with LanCore.',
                ], 401);
            }

            $user = $this->syncService->resolveFromUpstream($lanCoreUser);

            Auth::login($user, remember: true);

            $request->session()->regenerate();

            return response()->json([
                'message' => 'Authenticated via LanCore.',
                'redirect' => config('fortify.home', '/'),
            ]);
        } catch (LanCoreDisabledException) {
            return response()->json([
                'message' => 'LanCore integration is currently disabled.',
            ], 503);
        } catch (LanCoreRequestException $e) {
            Log::error('LanCore auth callback failed.', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'message' => 'Unable to reach the identity provider. Please try again later.',
            ], 502);
        } catch (InvalidLanCoreUserException $e) {
            Log::warning('LanCore returned invalid user data during auth.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Received incomplete user information from the identity provider.',
            ], 422);
        }
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->client->isEnabled(),
        ]);
    }
}
