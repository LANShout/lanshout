<?php

namespace App\Http\Middleware;

use App\Models\ChatSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'is_blocked' => $user->isBlocked(),
                    'is_timed_out' => $user->isTimedOut(),
                    'timed_out_until' => $user->timed_out_until?->toISOString(),
                    'is_lancore_user' => $user->isLanCoreUser(),
                ] : null,
            ],
            'isAdmin' => optional($user)->id === 1,            'lancore' => [
                'enabled' => (bool) config('lancore.enabled'),
                'sso_url' => config('lancore.enabled')
                    ? route('lancore.redirect')
                    : null,
                'base_url' => config('lancore.enabled')
                    ? config('lancore.base_url')
                    : null,
            ],            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'chatSettings' => [
                'slowMode' => [
                    'enabled' => ChatSetting::isSlowModeEnabled(),
                    'seconds' => ChatSetting::slowModeSeconds(),
                ],
            ],
        ];
    }
}
