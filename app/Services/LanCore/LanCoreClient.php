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

    public function fetchUserByToken(string $token): ?LanCoreUser
    {
        $this->ensureEnabled();

        return $this->safeRequest(function () use ($token) {
            $response = $this->http()
                ->withHeader('Authorization', 'Bearer '.$token)
                ->post('/api/v1/auth/verify-token');

            $response->throw();

            return LanCoreUser::fromArray($response->json('user'));
        });
    }

    public function fetchUserById(int $lanCoreUserId): ?LanCoreUser
    {
        $this->ensureEnabled();

        return $this->safeRequest(function () use ($lanCoreUserId) {
            $response = $this->http()
                ->get("/api/v1/users/{$lanCoreUserId}");

            $response->throw();

            return LanCoreUser::fromArray($response->json('user'));
        });
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(config('lancore.base_url'))
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

            throw new LanCoreRequestException(
                "LanCore request failed with status {$status}.",
                $status ?? 0,
                $e,
            );
        }
    }
}
