<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Policies\PolicyAcceptanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicyController extends Controller
{
    public function __construct(private readonly PolicyAcceptanceService $policies)
    {
    }

    /**
     * Public policy metadata: the authoritative active versions served to
     * the frontend so registration and acceptance submissions reference the
     * versions of the policy content actually displayed.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'policies' => $this->policies->activePolicies(),
        ]);
    }

    /**
     * Acceptance status for the authenticated account. Accessible while the
     * agreement gate is pending so clients can decide what to show.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'accepted' => $this->policies->hasAcceptedAllActive($user),
            'pending' => $this->policies->pendingFor($user),
            'policies' => $this->policies->activePolicies(),
        ]);
    }

    /**
     * Authenticated first-login/activation acceptance endpoint. Uses the
     * current account identity, server-controlled versions, and
     * server-generated timestamps; idempotent for repeated submissions.
     */
    public function accept(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $errors = $this->policies->validateAcceptance($request->only([
            'terms_accepted',
            'privacy_acknowledged',
            'terms_version',
            'privacy_policy_version',
        ]));

        if ($errors !== null) {
            return response()->json([
                'message' => 'The policy versions you reviewed are no longer current. Please review the updated policies and accept them again.',
                'status' => 'policy_update_required',
                'errors' => $errors,
            ], 422);
        }

        $this->policies->recordActiveAcceptances($user);

        return response()->json([
            'message' => 'Thank you. Your acceptance of the HarborAI policies has been recorded.',
            'accepted' => true,
            'pending' => [],
        ]);
    }
}
