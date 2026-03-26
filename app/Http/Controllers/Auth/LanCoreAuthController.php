<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LanCore\Exceptions\InvalidLanCoreUserException;
use App\Services\LanCore\Exceptions\LanCoreDisabledException;
use App\Services\LanCore\Exceptions\LanCoreRequestException;
use App\Services\LanCore\LanCoreClient;
use App\Services\LanCore\UserSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
     * Redirect the browser to LanCore's SSO authorization endpoint.
     *
     * If the user is already logged in to LanCore, they will be
     * immediately redirected back with a code. Otherwise LanCore
     * shows its login page first.
     */
    public function redirect(): RedirectResponse
    {
        if (! $this->client->isEnabled()) {
            abort(503, 'LanCore integration is currently disabled.');
        }

        return redirect()->away($this->client->ssoAuthorizeUrl());
    }

    /**
     * Handle the callback from LanCore SSO.
     *
     * Receives a single-use authorization code, exchanges it
     * server-to-server for user data, creates/updates the local
     * shadow user, and logs them into LanShout.
     */
    public function callback(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:64'],
        ]);

        try {
            $lanCoreUser = $this->client->exchangeCode($request->query('code'));

            if (! $lanCoreUser) {
                return redirect()->route('home')->with(
                    'error',
                    'Unable to resolve your identity. Please try again.',
                );
            }

            $user = $this->syncService->resolveFromUpstream($lanCoreUser);

            Auth::login($user, remember: true);

            $request->session()->regenerate();

            return redirect()->intended(config('fortify.home', '/'));
        } catch (LanCoreDisabledException) {
            abort(503, 'LanCore integration is currently disabled.');
        } catch (LanCoreRequestException $e) {
            Log::error('LanCore SSO callback failed.', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $message = $e->getCode() === 400
                ? 'Your login link has expired or was already used. Please try again.'
                : 'Unable to reach the identity provider. Please try again later.';

            return redirect()->route('home')->with('error', $message);
        } catch (InvalidLanCoreUserException $e) {
            Log::warning('LanCore returned invalid user data during SSO.', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('home')->with(
                'error',
                'Received incomplete user information from the identity provider.',
            );
        }
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->client->isEnabled(),
        ]);
    }
}
