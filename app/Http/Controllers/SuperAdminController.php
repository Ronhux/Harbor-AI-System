<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    public function overview()
    {
        $warnings = [];
        $adminCounts = User::query()->where('user_type', 'Admin')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN is_active = 0 OR is_active IS NULL THEN 1 ELSE 0 END) as inactive')
            ->first();
        $stats = [
            'admins' => (int) $adminCounts->total,
            'active_admins' => (int) $adminCounts->active,
            'inactive_admins' => (int) $adminCounts->inactive,
            'producers' => User::where('user_type', 'Farmer')->count(),
            'buyers' => User::where('user_type', 'Buyer')->count(),
            'listings' => $this->tableCount('product_listings', $warnings),
            'demands' => $this->tableCount('demand_requests', $warnings),
            'orders' => $this->tableCount('orders', $warnings),
        ];
        try {
            $activity = DB::table('admin_activity_logs')->leftJoin('users', 'users.user_id', '=', 'admin_activity_logs.user_id')
                ->orderByDesc('admin_activity_logs.created_at')->limit(100)
                ->get(['admin_activity_logs.id', 'admin_activity_logs.action', 'admin_activity_logs.subject', 'admin_activity_logs.status', 'admin_activity_logs.created_at', 'users.email', 'users.first_name', 'users.last_name']);
        } catch (\Throwable $exception) {
            Log::warning('Unable to load administrator activity.', ['message' => $exception->getMessage()]);
            $activity = collect();
            $warnings[] = 'Administrator activity is temporarily unavailable.';
        }

        return response()->json(['stats' => $stats, 'activity' => $activity, 'warnings' => $warnings]);
    }

    public function administrators(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $dateColumn = User::accountCreatedAtColumn();
        $query = User::query()->where('user_type', 'Admin');
        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($matches) use ($search) {
                $term = '%'.$search.'%';
                $matches->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('is_active', ($filters['status'] ?? 'active') === 'active');
        }

        $administrators = $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('user_id')
            ->paginate($filters['per_page'] ?? 15, [
                'user_id',
                'first_name',
                'last_name',
                'email',
                'is_active',
                "{$dateColumn} as joined_at",
            ]);

        return response()->json([
            'data' => $administrators->items(),
            'pagination' => [
                'page' => $administrators->currentPage(),
                'per_page' => $administrators->perPage(),
                'total' => $administrators->total(),
                'last_page' => max(1, $administrators->lastPage()),
            ],
            'summary' => [
                'total' => User::query()->where('user_type', 'Admin')->count(),
                'active' => User::query()->where('user_type', 'Admin')->where('is_active', true)->count(),
                'inactive' => User::query()->where('user_type', 'Admin')->where(function ($query) {
                    $query->where('is_active', false)->orWhereNull('is_active');
                })->count(),
            ],
        ]);
    }

    private function tableCount(string $table, array &$warnings): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable $exception) {
            Log::warning('Unable to count dashboard table.', ['table' => $table, 'message' => $exception->getMessage()]);
            $warnings[] = ucfirst($table).' totals are temporarily unavailable.';

            return null;
        }
    }

    public function updateAdminStatus(Request $request, int $userId)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        if ($userId === (int) $request->user()->user_id) {
            return response()->json(['message' => 'You cannot change your own account status.'], 422);
        }

        DB::transaction(function () use ($data, $request, $userId) {
            $admin = User::query()->where('user_type', 'Admin')->findOrFail($userId);
            $admin->is_active = $data['is_active'];
            $admin->save();

            DB::table('admin_activity_logs')->insert([
                'user_id' => $request->user()->user_id,
                'action' => $data['is_active'] ? 'Activated administrator' : 'Deactivated administrator',
                'subject' => $admin->email,
                'status' => 'success',
                'created_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Administrator status updated.']);
    }
}
