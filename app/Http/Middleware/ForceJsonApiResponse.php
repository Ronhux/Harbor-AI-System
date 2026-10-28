<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API has no login page to redirect an unauthenticated caller to, so every
 * /api request is answered as JSON. Without this an expired session answers a
 * 500 "Route [login] not defined" HTML page instead of a readable 401.
 */
class ForceJsonApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}