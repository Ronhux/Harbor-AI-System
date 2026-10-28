<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Policies\FcaWorkflowPolicy;

class EnsureFcaWorkflowEnabled
{
    public function handle(Request $request, Closure $next)
    {
        app(FcaWorkflowPolicy::class)->enabled();
        return $next($request);
    }
}
