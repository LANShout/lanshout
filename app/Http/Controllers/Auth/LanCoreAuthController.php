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
     * Accept a LanCore user-token, resolve the upstream user,
     * create or update the local shadow, and log them in.
     *
     * This endpoint is designed to be called by LanCore during a
     * central-login handoff. The exact browser flow around it
     * (redirect, callback, etc.) will be finalized once LanCore
     * exposes its SSO endpoints.
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            $lanCoreUser = $this->client->fetchUserByToken($request->input('token'));

            if (! $lanCoreUser) {
                return response()->json([
                    'message' => 'Unable to verify token with LanCore.',
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
