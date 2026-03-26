<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreFilterChainRequest;
use App\Http\Requests\Chat\UpdateFilterChainRequest;
use App\Http\Requests\Chat\UpdateSlowModeRequest;
use App\Models\ChatSetting;
use App\Models\FilterChain;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ChatSettingsController extends Controller
{
    public function index(): InertiaResponse
    {
        $filterChains = FilterChain::orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (FilterChain $filter) => [
                'id' => $filter->id,
                'name' => $filter->name,
                'type' => $filter->type,
                'pattern' => $filter->pattern,
                'action' => $filter->action,
                'replacement' => $filter->replacement,
                'is_active' => $filter->is_active,
                'priority' => $filter->priority,
            ]);

        return Inertia::render('chat/Settings', [
            'filterChains' => $filterChains,
            'slowMode' => [
                'enabled' => ChatSetting::isSlowModeEnabled(),
                'seconds' => ChatSetting::slowModeSeconds(),
            ],
        ]);
    }

    public function storeFilter(StoreFilterChainRequest $request): JsonResponse
    {
        $filter = FilterChain::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        Cache::forget('active_filter_chains');

        return response()->json([
            'message' => "Filter '{$filter->name}' created.",
            'filter' => $filter,
        ], 201);
    }

    public function updateFilter(UpdateFilterChainRequest $request, FilterChain $filterChain): JsonResponse
    {
        $filterChain->update($request->validated());

        Cache::forget('active_filter_chains');

        return response()->json([
            'message' => "Filter '{$filterChain->name}' updated.",
            'filter' => $filterChain->fresh(),
        ]);
    }

    public function destroyFilter(FilterChain $filterChain): JsonResponse
    {
        $name = $filterChain->name;
        $filterChain->delete();

        Cache::forget('active_filter_chains');

        return response()->json([
            'message' => "Filter '{$name}' deleted.",
        ]);
    }

    public function updateSlowMode(UpdateSlowModeRequest $request): JsonResponse
    {
        ChatSetting::setValue('slow_mode_enabled', $request->boolean('enabled') ? '1' : '0');

        if ($request->boolean('enabled')) {
            ChatSetting::setValue('slow_mode_seconds', (string) $request->integer('seconds'));
        }

        return response()->json([
            'message' => 'Slow mode settings updated.',
            'slowMode' => [
                'enabled' => $request->boolean('enabled'),
                'seconds' => $request->boolean('enabled') ? $request->integer('seconds') : ChatSetting::slowModeSeconds(),
            ],
        ]);
    }
}
