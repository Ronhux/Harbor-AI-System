<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminAnalyticsController extends Controller
{
    private const PRODUCER_USER_TYPES = ['Farmer', 'Fisherfolk', 'Producer'];

    private const ACTIVE_LISTING_STATUSES = ['active', 'available'];

    private const ACTIVE_DEMAND_STATUSES = ['active', 'offers received', 'open'];

    private const TERMINAL_ORDER_STATUSES = [
        'delivered',
        'completed',
        'cancelled',
        'rejected',
    ];

    public function monitoring(Request $request)
    {
        [$start, $end, $months] = $this->dateRange($request);
        $startTimestamp = $start->startOfDay()->format('Y-m-d H:i:s');
        $endTimestamp = $end->endOfDay()->format('Y-m-d H:i:s.u');
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $today = CarbonImmutable::now((string) config('app.timezone', 'UTC'))->toDateString();
        $userCreatedAt = 'users.' . User::accountCreatedAtColumn();

        $producerUsers = DB::table('users')
            ->whereIn('user_type', self::PRODUCER_USER_TYPES)
            ->whereBetween(User::accountCreatedAtColumn(), [$startTimestamp, $endTimestamp]);
        $buyerUsers = DB::table('users')
            ->where('user_type', 'Buyer')
            ->whereBetween(User::accountCreatedAtColumn(), [$startTimestamp, $endTimestamp]);
        $listingCohort = DB::table('product_listings')
            ->whereBetween('created_at', [$startTimestamp, $endTimestamp]);
        $orderDate = $this->orderDateExpression();
        $orderCohort = DB::table('orders')
            ->whereRaw("{$orderDate} BETWEEN ? AND ?", [$startDate, $endDate]);
        $demandCohort = DB::table('demand_requests')
            ->whereBetween('created_at', [$startTimestamp, $endTimestamp]);

        $producerVerification = DB::table('users')
            ->leftJoin('producers', 'producers.user_id', '=', 'users.user_id')
            ->whereIn('users.user_type', self::PRODUCER_USER_TYPES)
            ->whereBetween($userCreatedAt, [$startTimestamp, $endTimestamp]);
        $verificationStatus = "LOWER(COALESCE(NULLIF(TRIM(producers.verification_status), ''), 'not assessed'))";
        $verificationCounts = (clone $producerVerification)
            ->selectRaw("{$verificationStatus} AS status_key, MIN(COALESCE(NULLIF(TRIM(producers.verification_status), ''), 'Not assessed')) AS status, COUNT(DISTINCT users.user_id) AS count")
            ->groupBy('status_key')
            ->orderBy('status_key')
            ->get()
            ->map(fn ($row) => ['status' => $row->status, 'count' => (int) $row->count])
            ->values();

        $availableQuantity = 'COALESCE(product_listings.quantity_available, product_listings.quantity, 0)';
        $eligibleListing = (clone $listingCohort)
            ->join('producers', 'producers.producer_id', '=', 'product_listings.producer_id')
            ->where('producers.verification_status', 'Verified')
            ->whereRaw("LOWER(TRIM(product_listings.status)) IN (?, ?)", self::ACTIVE_LISTING_STATUSES);
        $availableListingCount = (clone $eligibleListing)->whereRaw("{$availableQuantity} > 0")->count();
        $outOfStockListingCount = (clone $eligibleListing)->whereRaw("{$availableQuantity} <= 0")->count();

        $activeDemand = (clone $demandCohort)
            ->whereIn(DB::raw('LOWER(TRIM(status))'), self::ACTIVE_DEMAND_STATUSES)
            ->where(fn (Builder $query) => $query->whereNull('deadline')->orWhereDate('deadline', '>=', $today));

        $orderStatusCounts = $this->statusCounts(clone $orderCohort, 'fulfillment_status');
        $demandStatusCounts = $this->statusCounts(clone $demandCohort, 'status');
        $listingStatusCounts = $this->statusCounts(clone $listingCohort, 'status');
        $inProgressOrderCount = (clone $orderCohort)
            ->where(function (Builder $query) {
                $query->whereNull('fulfillment_status')
                    ->orWhereNotIn(DB::raw('LOWER(TRIM(fulfillment_status))'), self::TERMINAL_ORDER_STATUSES);
            })
            ->count();

        $monthlyByMetric = [
            'producer_accounts' => $this->monthlyCount(clone $producerUsers, $userCreatedAt),
            'institutional_buyer_accounts' => $this->monthlyCount(clone $buyerUsers, $userCreatedAt),
            'product_listings_created' => $this->monthlyCount(clone $listingCohort, 'product_listings.created_at'),
            'orders_created' => $this->monthlyCount(clone $orderCohort, $orderDate),
            'demand_requests_created' => $this->monthlyCount(clone $demandCohort, 'demand_requests.created_at'),
        ];

        $series = collect($months)->map(function (array $month) use ($monthlyByMetric) {
            $point = $month;
            foreach ($monthlyByMetric as $metric => $counts) {
                $point[$metric] = (int) ($counts[$month['month']] ?? 0);
            }
            return $point;
        })->values();

        return response()->json([
            'meta' => $this->rangeMetadata($start, $end),
            'current_snapshot_as_of' => $today,
            'definitions' => [
                'producer_accounts' => 'Producer-role user accounts with a recorded account creation date in the selected date range; verified is the subset whose current producer profile status is Verified.',
                'institutional_buyer_accounts' => 'User accounts with user_type Buyer and a recorded account creation date in the selected date range.',
                'product_listings' => 'Listings created in the selected date range; availability and status counts describe their current stored values.',
                'orders' => 'Orders whose calendar order_date is in range, falling back to created_at only when order_date is null; in progress means status is not Delivered, Completed, Cancelled, or Rejected (including legacy/null status values), matching the producer order workflow.',
                'demand_requests' => 'Demand requests created in the selected date range; open_now uses active workflow statuses and excludes past deadlines.',
                'monthly_series' => 'Counts grouped by the persisted account creation date (date_joined when present, otherwise created_at), listing and demand creation date, and order_date for orders (created_at fallback only when absent).',
                'pending_actions' => 'Not reported: the current application has no persisted Admin task queue or Admin producer-verification action workflow.',
            ],
            'metrics' => [
                'producer_accounts' => [
                    'created_in_period' => (clone $producerUsers)->count(),
                    'verified_in_period' => (clone $producerVerification)->whereRaw("LOWER(TRIM(producers.verification_status)) = 'verified'")->distinct('users.user_id')->count('users.user_id'),
                    'verification_status_counts' => $verificationCounts,
                ],
                'institutional_buyer_accounts' => [
                    'created_in_period' => (clone $buyerUsers)->count(),
                ],
                'product_listings' => [
                    'created_in_period' => (clone $listingCohort)->count(),
                    'currently_available_in_period' => $availableListingCount,
                    'currently_out_of_stock_in_period' => $outOfStockListingCount,
                    'current_status_counts' => $listingStatusCounts,
                ],
                'orders' => [
                    'created_in_period' => (clone $orderCohort)->count(),
                    'in_progress_in_period' => $inProgressOrderCount,
                    'current_status_counts' => $orderStatusCounts,
                ],
                'demand_requests' => [
                    'created_in_period' => (clone $demandCohort)->count(),
                    'open_now_in_period' => $activeDemand->count(),
                    'current_status_counts' => $demandStatusCounts,
                ],
                'pending_actions' => [
                    'available' => false,
                    'count' => null,
                ],
            ],
            'monthly' => $series,
            'recent_activity' => $this->recentAdminActivity(),
        ]);
    }

    public function supplyDemand(Request $request)
    {
        [$start, $end, $months] = $this->dateRange($request);
        $startTimestamp = $start->startOfDay()->format('Y-m-d H:i:s');
        $endTimestamp = $end->endOfDay()->format('Y-m-d H:i:s.u');

        $monthlyActivity = $this->monthlyProductActivity(
            $startTimestamp,
            $endTimestamp,
        );
        $snapshotDate = CarbonImmutable::now((string) config('app.timezone', 'UTC'))->toDateString();
        $currentGroups = $this->currentSupplyDemandGroups($snapshotDate);

        return response()->json([
            'meta' => $this->rangeMetadata($start, $end),
            'current_snapshot_as_of' => $snapshotDate,
            'definitions' => [
                'monthly_activity' => 'Counts of listing records and demand requests created in each month, grouped by exact case-insensitive product name and unit. Quantities are not historical because the current schema has no quantity snapshots.',
                'current_snapshot' => 'Available supply is from verified producers with Active/Available listings and positive canonical quantity. Active demand uses Active, Offers Received, or legacy open status, with no deadline or a deadline today/future.',
                'grouping' => 'Product names and units are grouped only by trimmed, case-insensitive exact text; aliases and different units are not matched.',
                'fulfillment' => 'No fulfillment, shortage, surplus, completed sales, or price prediction is inferred.',
            ],
            'months' => $months,
            'monthly_activity' => $monthlyActivity,
            'current_snapshot' => $currentGroups,
        ]);
    }

    private function dateRange(Request $request): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $now = CarbonImmutable::now($timezone);
        $unsupportedParameters = array_diff(array_keys($request->query()), ['start_date', 'end_date']);
        if ($unsupportedParameters !== []) {
            throw ValidationException::withMessages([
                'query' => ['Unsupported analytics query parameter(s): ' . implode(', ', $unsupportedParameters) . '.'],
            ]);
        }
        $query = $request->validate([
            'start_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $endText = $query['end_date'] ?? $now->toDateString();
        $defaultStart = CarbonImmutable::createFromFormat('!Y-m-d', $endText, $timezone)
            ->startOfMonth()
            ->subMonths(5)
            ->toDateString();
        $startText = $query['start_date'] ?? $defaultStart;
        $range = Validator::make([
            'start_date' => $startText,
            'end_date' => $endText,
        ], [
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
        ])->validate();

        $start = CarbonImmutable::createFromFormat('!Y-m-d', $range['start_date'], $timezone);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $range['end_date'], $timezone);
        if ($start->startOfMonth()->diffInMonths($end->startOfMonth()) > 23) {
            throw ValidationException::withMessages([
                'start_date' => ['The selected range may not exceed 24 calendar months.'],
            ]);
        }

        $months = [];
        for ($cursor = $start->startOfMonth(); $cursor <= $end->startOfMonth(); $cursor = $cursor->addMonth()) {
            $months[] = [
                'month' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
            ];
        }

        return [$start, $end, $months];
    }

    private function rangeMetadata(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'timezone' => (string) config('app.timezone', 'UTC'),
            'boundaries' => 'Inclusive calendar dates in the application timezone.',
        ];
    }

    private function statusCounts(Builder $query, string $column): array
    {
        $statusExpression = "LOWER(COALESCE(NULLIF(TRIM({$column}), ''), 'unknown'))";

        return $query
            ->selectRaw("{$statusExpression} AS status_key, MIN(COALESCE(NULLIF(TRIM({$column}), ''), 'Unknown')) AS status, COUNT(*) AS count")
            ->groupBy('status_key')
            ->orderBy('status_key')
            ->get()
            ->map(fn ($row) => ['status' => $row->status, 'count' => (int) $row->count])
            ->values()
            ->all();
    }

    private function monthlyCount(Builder $query, string $dateColumn): array
    {
        $monthExpression = $this->monthExpression($dateColumn);

        return $query
            ->whereNotNull(DB::raw($dateColumn))
            ->selectRaw("{$monthExpression} AS month, COUNT(*) AS count")
            ->groupBy('month')
            ->pluck('count', 'month')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function monthlyProductActivity(string $startTimestamp, string $endTimestamp): array
    {
        $productExpression = 'LOWER(TRIM(product_name))';
        $listingUnitExpression = "LOWER(COALESCE(NULLIF(TRIM(unit_of_measure), ''), NULLIF(TRIM(unit), ''), 'unspecified'))";
        $listingUnitLabel = "MIN(COALESCE(NULLIF(TRIM(unit_of_measure), ''), NULLIF(TRIM(unit), ''), 'Unspecified'))";
        $demandUnitExpression = "LOWER(COALESCE(NULLIF(TRIM(unit), ''), 'unspecified'))";
        $demandUnitLabel = "MIN(COALESCE(NULLIF(TRIM(unit), ''), 'Unspecified'))";
        $listingMonth = $this->monthExpression('created_at');
        $demandMonth = $this->monthExpression('created_at');

        $listingRows = DB::table('product_listings')
            ->whereBetween('created_at', [$startTimestamp, $endTimestamp])
            ->selectRaw("{$listingMonth} AS month, {$productExpression} AS product_key, {$listingUnitExpression} AS unit_key, MIN(TRIM(product_name)) AS product_name, {$listingUnitLabel} AS unit, COUNT(*) AS listing_count")
            ->groupBy('month', 'product_key', 'unit_key')
            ->get();

        $demandRows = DB::table('demand_requests')
            ->whereBetween('created_at', [$startTimestamp, $endTimestamp])
            ->selectRaw("{$demandMonth} AS month, {$productExpression} AS product_key, {$demandUnitExpression} AS unit_key, MIN(TRIM(product_name)) AS product_name, {$demandUnitLabel} AS unit, COUNT(*) AS request_count")
            ->groupBy('month', 'product_key', 'unit_key')
            ->get();

        $combined = [];
        foreach ($listingRows as $row) {
            $key = $row->month . '|' . $row->product_key . '|' . $row->unit_key;
            $combined[$key] = [
                'month' => $row->month,
                'product_key' => $row->product_key,
                'product_name' => $row->product_name,
                'unit_key' => $row->unit_key,
                'unit' => $row->unit,
                'listings_created' => (int) $row->listing_count,
                'demand_requests_created' => 0,
            ];
        }
        foreach ($demandRows as $row) {
            $key = $row->month . '|' . $row->product_key . '|' . $row->unit_key;
            $combined[$key] ??= [
                'month' => $row->month,
                'product_key' => $row->product_key,
                'product_name' => $row->product_name,
                'unit_key' => $row->unit_key,
                'unit' => $row->unit,
                'listings_created' => 0,
                'demand_requests_created' => 0,
            ];
            $combined[$key]['demand_requests_created'] = (int) $row->request_count;
        }

        $rows = array_values($combined);
        usort($rows, static fn (array $left, array $right) => [$left['month'], $left['product_key'], $left['unit_key']] <=> [$right['month'], $right['product_key'], $right['unit_key']]);

        return $rows;
    }

    private function currentSupplyDemandGroups(string $asOfDate): array
    {
        $productExpression = 'LOWER(TRIM(product_name))';
        $listingUnitExpression = "LOWER(COALESCE(NULLIF(TRIM(unit_of_measure), ''), NULLIF(TRIM(unit), ''), 'unspecified'))";
        $listingUnitLabel = "MIN(COALESCE(NULLIF(TRIM(unit_of_measure), ''), NULLIF(TRIM(unit), ''), 'Unspecified'))";
        $demandUnitExpression = "LOWER(COALESCE(NULLIF(TRIM(unit), ''), 'unspecified'))";
        $demandUnitLabel = "MIN(COALESCE(NULLIF(TRIM(unit), ''), 'Unspecified'))";
        $quantityExpression = 'COALESCE(quantity_available, quantity, 0)';

        $supplyRows = DB::table('product_listings')
            ->join('producers', 'producers.producer_id', '=', 'product_listings.producer_id')
            ->where('producers.verification_status', 'Verified')
            ->whereIn(DB::raw('LOWER(TRIM(product_listings.status))'), self::ACTIVE_LISTING_STATUSES)
            ->whereRaw("{$quantityExpression} > 0")
            ->selectRaw("{$productExpression} AS product_key, {$listingUnitExpression} AS unit_key, MIN(TRIM(product_listings.product_name)) AS product_name, {$listingUnitLabel} AS unit, COUNT(*) AS listing_count, SUM({$quantityExpression}) AS quantity")
            ->groupBy('product_key', 'unit_key')
            ->get();

        $demandRows = DB::table('demand_requests')
            ->whereIn(DB::raw('LOWER(TRIM(status))'), self::ACTIVE_DEMAND_STATUSES)
            ->where(fn (Builder $query) => $query->whereNull('deadline')->orWhereDate('deadline', '>=', $asOfDate))
            ->selectRaw("{$productExpression} AS product_key, {$demandUnitExpression} AS unit_key, MIN(TRIM(product_name)) AS product_name, {$demandUnitLabel} AS unit, COUNT(*) AS request_count, SUM(quantity_needed) AS quantity")
            ->groupBy('product_key', 'unit_key')
            ->get();

        $groups = [];
        foreach ($supplyRows as $row) {
            $key = $row->product_key . '|' . $row->unit_key;
            $groups[$key] = [
                'product_key' => $row->product_key,
                'product_name' => $row->product_name,
                'unit_key' => $row->unit_key,
                'unit' => $row->unit,
                'available_listing_count' => (int) $row->listing_count,
                'available_supply_quantity' => (float) $row->quantity,
                'active_demand_request_count' => 0,
                'requested_demand_quantity' => 0.0,
            ];
        }
        foreach ($demandRows as $row) {
            $key = $row->product_key . '|' . $row->unit_key;
            $groups[$key] ??= [
                'product_key' => $row->product_key,
                'product_name' => $row->product_name,
                'unit_key' => $row->unit_key,
                'unit' => $row->unit,
                'available_listing_count' => 0,
                'available_supply_quantity' => 0.0,
                'active_demand_request_count' => 0,
                'requested_demand_quantity' => 0.0,
            ];
            $groups[$key]['active_demand_request_count'] = (int) $row->request_count;
            $groups[$key]['requested_demand_quantity'] = (float) $row->quantity;
        }

        $result = array_values($groups);
        usort($result, static fn (array $left, array $right) => [$left['product_key'], $left['unit_key']] <=> [$right['product_key'], $right['unit_key']]);

        return $result;
    }

    private function monthExpression(string $dateExpression): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$dateExpression})",
            'mysql' => "DATE_FORMAT({$dateExpression}, '%Y-%m')",
            'pgsql' => "TO_CHAR(({$dateExpression})::timestamp, 'YYYY-MM')",
            default => throw new \RuntimeException('Monthly analytics are not supported for this database driver.'),
        };
    }

    private function orderDateExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite', 'mysql' => 'DATE(COALESCE(orders.order_date, orders.created_at))',
            'pgsql' => 'CAST(COALESCE(CAST(orders.order_date AS TIMESTAMP), orders.created_at) AS DATE)',
            default => throw new \RuntimeException('Order date analytics are not supported for this database driver.'),
        };
    }

    private function recentAdminActivity(): array
    {
        try {
            return [
                'available' => true,
                'events' => DB::table('admin_activity_logs')
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get(['id', 'action', 'subject', 'status', 'created_at'])
                    ->map(fn ($event) => [
                        'id' => (int) $event->id,
                        'action' => $event->action,
                        'subject' => $event->subject,
                        'status' => $event->status,
                        'created_at' => $event->created_at,
                    ])
                    ->values(),
            ];
        } catch (Throwable $exception) {
            Log::warning('Admin analytics could not load persisted administrator activity.', [
                'exception' => $exception::class,
            ]);

            return ['available' => false, 'events' => []];
        }
    }
}
