<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ChatSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember("chat_setting.{$key}", 60, function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget("chat_setting.{$key}");
    }

    public static function isSlowModeEnabled(): bool
    {
        return (bool) static::getValue('slow_mode_enabled', false);
    }

    public static function slowModeSeconds(): int
    {
        return (int) static::getValue('slow_mode_seconds', 10);
    }
}
