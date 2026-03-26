<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanChat
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->isBlocked()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your account has been blocked.',
                    'reason' => $user->block_reason,
                ], 403);
            }

            abort(403, 'Your account has been blocked.');
        }

        if ($user->isTimedOut()) {
            $remaining = now()->diffInSeconds($user->timed_out_until);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You are timed out.',
                    'reason' => $user->timeout_reason,
                    'remaining_seconds' => $remaining,
                    'timed_out_until' => $user->timed_out_until->toISOString(),
                ], 429);
            }

            abort(429, 'You are currently timed out.');
        }

        return $next($request);
    }
}
