<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockLanCoreUserFromFortifyFeatures
{
    /**
     * Block LanCore SSO users from Fortify features that do not apply to them.
     *
     * - verification.send (POST /email/verification-notification):
     *   Aborts with 403 if the authenticated user is a LanCore user.
     *
     * - password.email (POST /forgot-password):
     *   If the submitted email belongs to a LanCore user, silently pretends the
     *   action succeeded without performing it. This avoids leaking whether an
     *   email address belongs to a LanCore account.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if ($routeName === 'verification.send' && $request->user()?->isLanCoreUser()) {
            abort(403, 'Email verification is not available for SSO accounts.');
        }

        if ($routeName === 'password.email' && $request->has('email')) {
            $user = User::where('email', $request->input('email'))->first();

            if ($user?->isLanCoreUser()) {
                return back()->with('status', __('passwords.sent'));
            }
        }

        return $next($request);
    }
}
