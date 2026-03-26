<?php

it('has expected config keys', function () {
    expect(config('lancore.enabled'))->toBeBool()
        ->and(config('lancore.base_url'))->toBeString()
        ->and(config('lancore.timeout'))->toBeInt()
        ->and(config('lancore.retries'))->toBeInt()
        ->and(config('lancore.retry_delay'))->toBeInt();
});

it('can be toggled via config', function () {
    config(['lancore.enabled' => true]);

    expect(config('lancore.enabled'))->toBeTrue();
});

it('accepts a custom base url', function () {
    config(['lancore.base_url' => 'https://core.lan.party']);

    expect(config('lancore.base_url'))->toBe('https://core.lan.party');
});
