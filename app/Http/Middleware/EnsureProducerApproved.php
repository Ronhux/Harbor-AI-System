<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProducerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(strtolower((string) $request->user()?->user_type), ['farmer', 'fisherfolk', 'producer'], true)) {
            // Reuse existing restrictions for producer and shared protected APIs.
            return app(EnsureUserType::class)->handle($request, $next, 'Farmer', 'Fisherfolk', 'Producer');
        }

        return $next($request);
    }
}