<?php

namespace App\Http\Middleware;

use App\Models\Producer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnsureUserType
{
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();
        $allowedTypes = array_map('strtolower', $types);

        if (!$user || !in_array(strtolower((string) $user->user_type), $allowedTypes, true)) {
            return response()->json(['message' => 'You are not authorized to access this resource.'], 403);
        }

        if (in_array(strtolower((string) $user->user_type), ['admin', 'super admin'], true) && $user->is_active === false) {
            return response()->json(['message' => 'This administrator account is inactive.'], 403);
        }

        if (in_array(strtolower((string) $user->user_type), ['farmer', 'fisherfolk', 'producer'], true)) {
            $producer = Producer::where('user_id', $user->user_id)->first();
            $status = strtolower((string) ($producer?->verification_status ?? ''));

            if (!$producer || !in_array($status, ['verified'], true)) {
                Log::warning('Producer route blocked for unverified producer', [
                    'user_id' => $user->user_id,
                    'route' => $request->route()?->uri() ?? $request->path(),
                    'verification_status' => $producer?->verification_status ?? 'none',
                ]);

                return response()->json([
                    'message' => 'Your producer account is pending administrator approval and cannot access producer features yet.',
                    'verification_required' => true,
                ], 403);
            }
        }

        $routeUri = $request->route()?->uri() ?? $request->path();
        $isAdminApiRequest = strtolower((string) $user->user_type) === 'admin' && $request->is('api/admin/*');
        $isRegistryImport = strtolower((string) $user->user_type) === 'super admin'
            && $request->method() === 'POST'
            && $routeUri === 'api/superadmin/registry/import';

        if (!$isAdminApiRequest && !$isRegistryImport) {
            return $next($request);
        }

        try {
            $response = $next($request);
            $this->recordAdminRequest($request, $response->getStatusCode() < 400 ? 'success' : 'failure');

            return $response;
        } catch (Throwable $exception) {
            $this->recordAdminRequest($request, 'failure');
            throw $exception;
        }
    }

    private function recordAdminRequest(Request $request, string $status): void
    {
        $route = $request->route();
        $routeUri = $route?->uri() ?? $request->path();
        $routeKey = $request->method() . ' ' . $routeUri;
        $actions = [
            'GET api/admin/reports' => 'Viewed admin reports',
            'GET api/admin/reports/{id}' => 'Viewed saved admin report',
            'POST api/admin/reports/preview' => 'Previewed admin report',
            'POST api/admin/reports' => 'Generated admin report',
            'GET api/admin/reports/{id}/download' => 'Downloaded admin report',
            'GET api/admin/supply' => 'Viewed admin supply report',
            'GET api/admin/registry' => 'Viewed official producer registry',
            'GET api/admin/registry/search' => 'Searched official producer registry',
            'GET api/admin/demands' => 'Viewed buyer demand monitoring',
            'POST api/superadmin/registry/import' => 'Imported official producer registry',
        ];
        $action = $actions[$routeKey] ?? strtoupper($request->method()) . ' ' . $routeUri;

        try {
            DB::table('admin_activity_logs')->insert([
                'user_id' => $request->user()->user_id,
                'action' => $action,
                'subject' => $request->path(),
                'status' => $status,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unable to record administrator API activity.', [
                'route' => $routeUri,
                'exception' => $exception::class,
            ]);
        }
    }
}
