<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side guard for admin-only areas (user management, settings).
 * Navigation hiding in the UI is cosmetic; this is the actual protection.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can access this page.');

        return $next($request);
    }
}
