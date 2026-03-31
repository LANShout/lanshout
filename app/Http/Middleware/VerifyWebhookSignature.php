<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('lancore.webhook_secret');

        if (empty($secret)) {
            return $next($request);
        }

        $signature = $request->header('X-Webhook-Signature');

        if (empty($signature)) {
            return response()->json(['message' => 'Missing webhook signature.'], 401);
        }

        // Strip the "sha256=" prefix if present (LanCore sends "sha256=<hex>").
        $hex = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $hex)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
