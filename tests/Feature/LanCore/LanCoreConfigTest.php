<?php

it('has expected default values', function () {
    expect(config('lancore.enabled'))->toBeFalse()
        ->and(config('lancore.base_url'))->toBe('http://localhost:8080')
        ->and(config('lancore.token'))->toBeNull()
        ->and(config('lancore.timeout'))->toBe(5)
        ->and(config('lancore.retries'))->toBe(2)
        ->and(config('lancore.retry_delay'))->toBe(100);
});

it('can be toggled via config', function () {
    config(['lancore.enabled' => true]);

    expect(config('lancore.enabled'))->toBeTrue();
});

it('accepts a custom base url', function () {
    config(['lancore.base_url' => 'https://core.lan.party']);

    expect(config('lancore.base_url'))->toBe('https://core.lan.party');
});
