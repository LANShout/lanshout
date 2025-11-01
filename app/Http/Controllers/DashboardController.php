<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard page.
     */
    public function index(): Response
    {
        abort_unless(auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'admin', 'moderator']), 403);

        // Fetch basic statistics
        $userCount = User::count();
        $messageCount = Message::count();
        $activeSessions = $this->getActiveSessionsCount();

        return Inertia::render('Dashboard', [
            'statistics' => [
                'userCount' => $userCount,
                'messageCount' => $messageCount,
                'activeSessions' => $activeSessions,
            ],
        ]);
    }

    /**
     * Get time-series statistics data for charts.
     */
    public function statistics(Request $request): JsonResponse
    {
        abort_unless(auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'admin', 'moderator']), 403);

        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:hour,day,week'],
            'metric' => ['required', 'string', 'in:messages,users,sessions'],
        ]);

        $resolution = $validated['resolution'];
        $metric = $validated['metric'];

        $data = match ($metric) {
            'messages' => $this->getMessagesData($resolution),
            'users' => $this->getUsersData($resolution),
            'sessions' => $this->getSessionsData($resolution),
        };

        return response()->json($data);
    }

    /**
     * Get messages count aggregated by time resolution.
     */
    private function getMessagesData(string $resolution): array
    {
        $formatMap = [
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
        ];

        $intervalMap = [
            'hour' => 24, // last 24 hours
            'day' => 30, // last 30 days
            'week' => 12, // last 12 weeks
        ];

        $format = $formatMap[$resolution];
        $periods = $intervalMap[$resolution];

        // Get the starting point
        $startDate = match ($resolution) {
            'hour' => now()->subHours($periods - 1),
            'day' => now()->subDays($periods - 1),
            'week' => now()->subWeeks($periods - 1),
        };

        // Query messages grouped by time period
        $results = Message::select(
            DB::raw("DATE_FORMAT(created_at, '$format') as period"),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        // Generate all time periods and fill in data
        return $this->fillTimePeriods($resolution, $periods, $results);
    }

    /**
     * Get users count aggregated by time resolution.
     */
    private function getUsersData(string $resolution): array
    {
        $formatMap = [
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
        ];

        $intervalMap = [
            'hour' => 24,
            'day' => 30,
            'week' => 12,
        ];

        $format = $formatMap[$resolution];
        $periods = $intervalMap[$resolution];

        $startDate = match ($resolution) {
            'hour' => now()->subHours($periods - 1),
            'day' => now()->subDays($periods - 1),
            'week' => now()->subWeeks($periods - 1),
        };

        $results = User::select(
            DB::raw("DATE_FORMAT(created_at, '$format') as period"),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        return $this->fillTimePeriods($resolution, $periods, $results);
    }

    /**
     * Get active sessions (users who posted messages recently).
     */
    private function getSessionsData(string $resolution): array
    {
        $formatMap = [
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
        ];

        $intervalMap = [
            'hour' => 24,
            'day' => 30,
            'week' => 12,
        ];

        $format = $formatMap[$resolution];
        $periods = $intervalMap[$resolution];

        $startDate = match ($resolution) {
            'hour' => now()->subHours($periods - 1),
            'day' => now()->subDays($periods - 1),
            'week' => now()->subWeeks($periods - 1),
        };

        // Count distinct users who posted messages in each period
        $results = Message::select(
            DB::raw("DATE_FORMAT(created_at, '$format') as period"),
            DB::raw('COUNT(DISTINCT user_id) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        return $this->fillTimePeriods($resolution, $periods, $results);
    }

    /**
     * Fill in missing time periods with zero values.
     */
    private function fillTimePeriods(string $resolution, int $periods, $results): array
    {
        $data = [];
        $now = now();

        for ($i = $periods - 1; $i >= 0; $i--) {
            $date = match ($resolution) {
                'hour' => $now->copy()->subHours($i),
                'day' => $now->copy()->subDays($i),
                'week' => $now->copy()->subWeeks($i),
            };

            $periodKey = match ($resolution) {
                'hour' => $date->format('Y-m-d H:00:00'),
                'day' => $date->format('Y-m-d'),
                'week' => $date->format('Y-W'),
            };

            $label = match ($resolution) {
                'hour' => $date->format('H:00'),
                'day' => $date->format('M d'),
                'week' => 'Week ' . $date->format('W'),
            };

            $data[] = [
                'name' => $label,
                'value' => $results->has($periodKey) ? (int) $results[$periodKey]->count : 0,
            ];
        }

        return $data;
    }

    /**
     * Get current active sessions count (users who posted in last 5 minutes).
     */
    private function getActiveSessionsCount(): int
    {
        return Message::where('created_at', '>=', now()->subMinutes(5))
            ->distinct('user_id')
            ->count('user_id');
    }
}
