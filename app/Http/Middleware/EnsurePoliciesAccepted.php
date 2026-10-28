<?php

namespace App\Http\Middleware;

use App\Services\Policies\PolicyAcceptanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates protected API features until the authenticated account has recorded
 * affirmative acceptance of the active HarborAI Terms and Conditions and
 * Privacy Policy. Acceptance never elevates permissions; it only unlocks
 * already-authorized functionality.
 */
class EnsurePoliciesAccepted
{
    public function __construct(private readonly PolicyAcceptanceService $policies)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $pending = $this->policies->pendingFor($user);

        if ($pending !== []) {
            return response()->json([
                'message' => 'Please review and accept the HarborAI Terms and Conditions and Privacy Policy to continue.',
                'code' => 'policy_acceptance_required',
                'pending' => $pending,
            ], 403);
        }

        return $next($request);
    }
}
