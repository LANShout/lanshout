<?php

namespace App\Services;

use App\Models\FilterChain;
use Illuminate\Support\Facades\Cache;

class ContentModeration
{
    /**
     * Sanitize and filter message body through the filter chain.
     *
     * @return array{body: string, blocked: bool, warnings: list<string>}
     */
    public function process(string $body): array
    {
        $sanitized = $this->sanitize($body);
        $warnings = [];
        $blocked = false;

        foreach ($this->getActiveFilters() as $filter) {
            $matches = $this->matchesFilter($sanitized, $filter);

            if (! $matches) {
                continue;
            }

            if ($filter->action === 'block') {
                $blocked = true;

                break;
            }

            if ($filter->action === 'replace') {
                $sanitized = $this->applyReplacement($sanitized, $filter);
            }

            if ($filter->action === 'warn') {
                $warnings[] = "Message matched filter: {$filter->name}";
            }
        }

        return [
            'body' => $sanitized,
            'blocked' => $blocked,
            'warnings' => $warnings,
        ];
    }

    /**
     * Very small initial sanitizer. Extend with profanity filters, URL stripping, etc.
     */
    public function sanitize(string $body): string
    {
        $clean = trim($body);
        $clean = preg_replace('/\s+/u', ' ', $clean ?? '');
        $clean = strip_tags($clean);

        return (string) $clean;
    }

    /**
     * @return list<FilterChain>
     */
    protected function getActiveFilters(): array
    {
        return Cache::remember('active_filter_chains', 60, function () {
            return FilterChain::where('is_active', true)
                ->orderBy('priority')
                ->get()
                ->all();
        });
    }

    protected function matchesFilter(string $body, FilterChain $filter): bool
    {
        return match ($filter->type) {
            'exact' => mb_strtolower($body) === mb_strtolower($filter->pattern),
            'contains' => str_contains(mb_strtolower($body), mb_strtolower($filter->pattern)),
            'regex' => (bool) @preg_match($filter->pattern, $body),
            default => false,
        };
    }

    protected function applyReplacement(string $body, FilterChain $filter): string
    {
        $replacement = $filter->replacement ?? '***';

        return match ($filter->type) {
            'exact' => $replacement,
            'contains' => str_ireplace($filter->pattern, $replacement, $body),
            'regex' => (string) @preg_replace($filter->pattern, $replacement, $body),
            default => $body,
        };
    }
}
