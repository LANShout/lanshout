<?php

namespace App\Services;

class ContentModeration
{
    /**
     * Very small initial sanitizer. Extend with profanity filters, URL stripping, etc.
     */
    public function sanitize(string $body): string
    {
        // Trim, collapse whitespace, and strip HTML tags to avoid XSS in MVP.
        $clean = trim($body);
        $clean = preg_replace('/\s+/u', ' ', $clean ?? '');
        $clean = strip_tags($clean);

        return (string) $clean;
    }
}
