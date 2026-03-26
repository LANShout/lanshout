<?php

use App\Services\LanCore\Exceptions\LanCoreDisabledException;
use App\Services\LanCore\Exceptions\LanCoreRequestException;
use App\Services\LanCore\LanCoreClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'lancore.enabled' => true,
        'lancore.base_url' => 'https://lancore.test',
        'lancore.internal_url' => null,
        'lancore.token' => 'lci_test-integration-token',
        'lancore.timeout' => 5,
        'lancore.retries' => 0,
        'lancore.retry_delay' => 0,
    ]);
});

it('reports enabled status from config', function () {
    $client = new LanCoreClient;

    expect($client->isEnabled())->toBeTrue();

    config(['lancore.enabled' => false]);
    expect($client->isEnabled())->toBeFalse();
});

it('throws LanCoreDisabledException when resolving by id while disabled', function () {
    config(['lancore.enabled' => false]);

    $client = new LanCoreClient;
    $client->resolveUserById(1);
})->throws(LanCoreDisabledException::class);

it('throws LanCoreDisabledException when resolving by email while disabled', function () {
    config(['lancore.enabled' => false]);

    $client = new LanCoreClient;
    $client->resolveUserByEmail('test@example.com');
})->throws(LanCoreDisabledException::class);

it('resolves a user by id successfully', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response([
            'data' => [
                'id' => 42,
                'username' => 'mkohn',
                'locale' => 'en',
                'avatar' => 'https://lancore.test/avatars/42.jpg',
                'created_at' => '2025-01-01T00:00:00Z',
                'email' => 'matt@example.com',
            ],
        ], 200),
    ]);

    $client = new LanCoreClient;
    $user = $client->resolveUserById(42);

    expect($user)->not->toBeNull()
        ->and($user->id)->toBe(42)
        ->and($user->username)->toBe('mkohn')
        ->and($user->email)->toBe('matt@example.com')
        ->and($user->avatar)->toBe('https://lancore.test/avatars/42.jpg');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/integration/user/resolve')
            && $request['user_id'] === 42;
    });
});

it('resolves a user by email successfully', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response([
            'data' => [
                'id' => 42,
                'username' => 'mkohn',
                'locale' => 'de',
                'avatar' => null,
                'created_at' => '2025-01-01T00:00:00Z',
                'email' => 'matt@example.com',
            ],
        ], 200),
    ]);

    $client = new LanCoreClient;
    $user = $client->resolveUserByEmail('matt@example.com');

    expect($user)->not->toBeNull()
        ->and($user->id)->toBe(42)
        ->and($user->locale)->toBe('de');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/integration/user/resolve')
            && $request['email'] === 'matt@example.com';
    });
});

it('throws LanCoreRequestException on 401 response', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $client = new LanCoreClient;
    $client->resolveUserById(1);
})->throws(LanCoreRequestException::class, 'LanCore rejected the integration token.');

it('throws LanCoreRequestException on 403 response', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response(['error' => 'Forbidden'], 403),
    ]);

    $client = new LanCoreClient;
    $client->resolveUserById(1);
})->throws(LanCoreRequestException::class, 'LanCore rejected the integration token.');

it('throws LanCoreRequestException on 500 server error', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response('Internal Server Error', 500),
    ]);

    $client = new LanCoreClient;
    $client->resolveUserById(1);
})->throws(LanCoreRequestException::class, 'LanCore request failed with status 500.');

it('throws LanCoreRequestException when LanCore is unreachable', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $client = new LanCoreClient;
    $client->resolveUserById(1);
})->throws(LanCoreRequestException::class, 'LanCore is unreachable.');

it('sends the integration token as a bearer header', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response([
            'data' => [
                'id' => 1,
                'username' => 'test',
                'locale' => null,
                'avatar' => null,
                'created_at' => '2025-01-01T00:00:00Z',
            ],
        ], 200),
    ]);

    $client = new LanCoreClient;
    $client->resolveUserById(1);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer lci_test-integration-token');
    });
});

it('handles response without email scope', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response([
            'data' => [
                'id' => 10,
                'username' => 'no-email-scope',
                'locale' => 'en',
                'avatar' => null,
                'created_at' => '2025-06-01T00:00:00Z',
            ],
        ], 200),
    ]);

    $client = new LanCoreClient;
    $user = $client->resolveUserById(10);

    expect($user->email)->toBeNull()
        ->and($user->username)->toBe('no-email-scope');
});

// --- SSO methods ---

it('builds the SSO authorize URL correctly', function () {
    $client = new LanCoreClient;
    $url = $client->ssoAuthorizeUrl('https://shout.test/auth/lancore/callback');

    expect($url)->toContain('https://lancore.test/sso/authorize')
        ->and($url)->toContain('app=lanshout')
        ->and($url)->toContain(urlencode('https://shout.test/auth/lancore/callback'));
});

it('uses config callback_url as default for SSO authorize URL', function () {
    config(['lancore.callback_url' => 'https://configured.test/callback']);

    $client = new LanCoreClient;
    $url = $client->ssoAuthorizeUrl();

    expect($url)->toContain(urlencode('https://configured.test/callback'));
});

it('throws LanCoreDisabledException when building SSO URL while disabled', function () {
    config(['lancore.enabled' => false]);

    $client = new LanCoreClient;
    $client->ssoAuthorizeUrl();
})->throws(LanCoreDisabledException::class);

it('exchanges an SSO code for user data', function () {
    Http::fake([
        'lancore.test/api/integration/sso/exchange' => Http::response([
            'data' => [
                'id' => 42,
                'username' => 'mkohn',
                'locale' => 'en',
                'avatar_url' => 'https://lancore.test/avatars/42.jpg',
                'created_at' => '2025-01-01T00:00:00Z',
                'email' => 'matt@example.com',
                'roles' => ['member'],
            ],
        ], 200),
    ]);

    $code = str_repeat('a', 64);
    $client = new LanCoreClient;
    $user = $client->exchangeCode($code);

    expect($user)->not->toBeNull()
        ->and($user->id)->toBe(42)
        ->and($user->username)->toBe('mkohn')
        ->and($user->email)->toBe('matt@example.com');

    Http::assertSent(function ($request) use ($code) {
        return str_contains($request->url(), '/api/integration/sso/exchange')
            && $request['code'] === $code;
    });
});

it('throws LanCoreRequestException on expired SSO code', function () {
    Http::fake([
        'lancore.test/api/integration/sso/exchange' => Http::response([
            'error' => 'Invalid or expired authorization code',
        ], 400),
    ]);

    $client = new LanCoreClient;
    $client->exchangeCode(str_repeat('x', 64));
})->throws(LanCoreRequestException::class, 'Invalid or expired authorization code');

it('throws LanCoreRequestException when SSO code belongs to wrong app', function () {
    Http::fake([
        'lancore.test/api/integration/sso/exchange' => Http::response([
            'error' => 'Authorization code does not belong to this application',
        ], 403),
    ]);

    $client = new LanCoreClient;
    $client->exchangeCode(str_repeat('y', 64));
})->throws(LanCoreRequestException::class, 'LanCore rejected the integration token.');
