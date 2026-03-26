<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\BlockUserRequest;
use App\Http\Requests\Chat\TimeoutUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $query = User::with('roles:id,name,display_name')
            ->select('id', 'name', 'email', 'is_blocked', 'block_reason', 'blocked_at', 'timed_out_until', 'timeout_reason');

        if ($request->has('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->boolean('blocked_only')) {
            $query->where('is_blocked', true);
        }

        if ($request->boolean('timed_out_only')) {
            $query->where('timed_out_until', '>', now());
        }

        $users = $query->orderBy('name')->paginate(20);

        return response()->json($users);
    }

    public function timeout(TimeoutUserRequest $request, User $user): JsonResponse
    {
        if ($user->isModerator()) {
            return response()->json(['message' => 'Cannot timeout a moderator or admin.'], 403);
        }

        $user->update([
            'timed_out_until' => now()->addMinutes($request->integer('duration_minutes')),
            'timeout_reason' => $request->string('reason')->toString() ?: null,
            'timed_out_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => "User {$user->name} has been timed out.",
            'timed_out_until' => $user->timed_out_until->toISOString(),
        ]);
    }

    public function relieveTimeout(Request $request, User $user): JsonResponse
    {
        $user->update([
            'timed_out_until' => null,
            'timeout_reason' => null,
            'timed_out_by' => null,
        ]);

        return response()->json([
            'message' => "Timeout for {$user->name} has been relieved.",
        ]);
    }

    public function block(BlockUserRequest $request, User $user): JsonResponse
    {
        if ($user->isModerator()) {
            return response()->json(['message' => 'Cannot block a moderator or admin.'], 403);
        }

        $user->update([
            'is_blocked' => true,
            'block_reason' => $request->string('reason')->toString() ?: null,
            'blocked_at' => now(),
            'blocked_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => "User {$user->name} has been blocked.",
        ]);
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $user->update([
            'is_blocked' => false,
            'block_reason' => null,
            'blocked_at' => null,
            'blocked_by' => null,
        ]);

        return response()->json([
            'message' => "User {$user->name} has been unblocked.",
        ]);
    }
}
