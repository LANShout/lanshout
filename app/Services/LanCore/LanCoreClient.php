<?php

namespace App\Services\LanCore;

use App\Services\LanCore\Exceptions\LanCoreDisabledException;
use App\Services\LanCore\Exceptions\LanCoreRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LanCoreClient
{
    public function isEnabled(): bool
    {
        return (bool) config('lancore.enabled');
    }

    /**
     * Resolve a LanCore user by their upstream ID.
     *
     * Calls POST /api/integration/user/resolve with { user_id: ... }.
     */
    public function resolveUserById(int $lanCoreUserId): ?LanCoreUser
    {
        return $this->resolveUser(['user_id' => $lanCoreUserId]);
    }

    /**
     * Resolve a LanCore user by their email address.
     *
     * Calls POST /api/integration/user/resolve with { email: ... }.
     * Requires the user:email scope to be granted to the integration app.
     */
    public function resolveUserByEmail(string $email): ?LanCoreUser
    {
        return $this->resolveUser(['email' => $email]);
    }

    /**
     * Build the LanCore SSO authorization URL.
     *
     * The browser is redirected here. If the user is already logged in
     * to LanCore, they are immediately redirected back with a `code`.
     * If not, LanCore shows a login page first.
     */
    public function ssoAuthorizeUrl(?string $redirectUri = null): string
    {
        $this->ensureEnabled();

        $redirectUri ??= config('lancore.callback_url')
            ?? url('/auth/lancore/callback');

        return rtrim(config('lancore.base_url'), '/').'/sso/authorize?'.http_build_query([
            'app' => config('lancore.app_slug'),
            'redirect_uri' => $redirectUri,
        ]);
    }

    /**
     * Exchange a single-use SSO authorization code for user data.
     *
     * Calls POST /api/integration/sso/exchange with { code: ... }.
     * The code is 64 characters, valid for 5 minutes, and single-use.
     */
    public function exchangeCode(string $code): ?LanCoreUser
    {
        $this->ensureEnabled();

        return $this->safeRequest(function () use ($code) {
            $response = $this->http()
                ->post('/api/integration/sso/exchange', ['code' => $code]);

            $response->throw();

            return LanCoreUser::fromArray($response->json('data'));
        });
    }

    /**
     * Fetch the session user behind a browser cookie / session.
     *
     * Calls GET /api/integration/user/me. Intended for a future
     * seamless browser SSO flow where the user's LanCore session
     * cookie is forwarded.
     */
    public function fetchSessionUser(string $sessionCookie): ?LanCoreUser
    {
        $this->ensureEnabled();

        return $this->safeRequest(function () use ($sessionCookie) {
            $response = $this->http()
                ->withCookies(
                    ['lancore_session' => $sessionCookie],
                    parse_url(config('lancore.base_url'), PHP_URL_HOST),
                )
                ->get('/api/integration/user/me');

            $response->throw();

            return LanCoreUser::fromArray($response->json('data'));
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveUser(array $payload): ?LanCoreUser
    {
        $this->ensureEnabled();

        return $this->safeRequest(function () use ($payload) {
            $response = $this->http()
                ->post('/api/integration/user/resolve', $payload);

            $response->throw();

            return LanCoreUser::fromArray($response->json('data'));
        });
    }

    protected function http(): PendingRequest
    {
        $apiBaseUrl = config('lancore.internal_url') ?? config('lancore.base_url');

        return Http::baseUrl($apiBaseUrl)
            ->withToken(config('lancore.token'))
            ->timeout(config('lancore.timeout'))
            ->retry(
                config('lancore.retries'),
                config('lancore.retry_delay'),
                fn ($exception) => $exception instanceof ConnectionException,
            )
            ->acceptJson();
    }

    protected function ensureEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new LanCoreDisabledException;
        }
    }

    protected function safeRequest(callable $callback): ?LanCoreUser
    {
        try {
            return $callback();
        } catch (ConnectionException $e) {
            Log::error('LanCore unreachable.', [
                'message' => $e->getMessage(),
                'base_url' => config('lancore.base_url'),
            ]);

            throw new LanCoreRequestException(
                'LanCore is unreachable.',
                previous: $e,
            );
        } catch (RequestException $e) {
            $status = $e->response?->status();

            Log::warning('LanCore request failed.', [
                'status' => $status,
                'body' => $e->response?->body(),
            ]);

            if ($status === 401 || $status === 403) {
                throw new LanCoreRequestException(
                    'LanCore rejected the integration token.',
                    $status,
                    $e,
                );
            }

            if ($status === 400) {
                throw new LanCoreRequestException(
                    $e->response?->json('error') ?: 'Bad request.',
                    $status,
                    $e,
                );
            }

            throw new LanCoreRequestException(
                "LanCore request failed with status {$status}.",
                $status ?? 0,
                $e,
            );
        }
    }
}
