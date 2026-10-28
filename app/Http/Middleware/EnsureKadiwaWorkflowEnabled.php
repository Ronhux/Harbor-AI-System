<?php

namespace App\Http\Middleware;

use App\Policies\KadiwaWorkflowPolicy;
use Closure;
use Illuminate\Http\Request;

/**
 * Closes every KADIWA route while the feature flag is false. Registering the routes grants
 * no access: this runs before authentication and before any controller code.
 */
class EnsureKadiwaWorkflowEnabled
{
    public function handle(Request $request, Closure $next)
    {
        app(KadiwaWorkflowPolicy::class)->enabled();

        return $next($request);
    }
}