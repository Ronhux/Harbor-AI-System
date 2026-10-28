<?php

namespace App\Http\Controllers;

use App\Models\DemandRequest;
use App\Models\Order;
use App\Models\ProductListing;
use App\Models\Producer;
use App\Models\User;
use App\Services\Reports\ReportPresentationService;
use App\Services\Reports\ReportTemplateRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportController extends Controller
{
    private const PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    private const REPORT_TYPES = [
        'producer_activity',
        'supply_demand',
        'demand_summary',
        'order_transactions',
    ];

    public function index(Request $request)
    {
        [$page, $perPage] = $this->paginationParams($request);
        $query = DB::table('generated_reports')
            ->where('generated_by', $request->user()->user_id)
            ->orderByDesc('created_at');
        $total = (clone $query)->count();
        $reports = $query
            ->select(['id', 'report_type', 'title', 'start_date', 'end_date', 'filters', 'created_at'])
            ->selectRaw('LENGTH(snapshot) AS snapshot_bytes')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($report) => [
                'id' => (int) $report->id,
                'type' => $report->report_type,
                'title' => $report->title,
                'start_date' => $report->start_date,
                'end_date' => $report->end_date,
                'filters' => json_decode($report->filters ?: '{}', true) ?: [],
                'generated_at' => $report->created_at,
                'size' => (int) $report->snapshot_bytes,
                'formats' => ['csv', 'xlsx', 'pdf'],
            ])
            ->values();

        return response()->json([
            'data' => $reports,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total_rows' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $row = $this->ownedReport($request, $id);
        $snapshot = app(ReportPresentationService::class)->decorate($this->decodeSnapshot($row->snapshot));

        return response()->json($this->pageSnapshot($request, $snapshot));
    }

    public function supply()
    {
        $records = ProductListing::query()->with('producer.user')->whereIn(DB::raw('lower(status)'), ['active', 'available'])->get();
        $groups = $records->groupBy(fn ($l) => mb_strtolower(trim($l->product_name)) . '|' . mb_strtolower(trim((string) ($l->unit_of_measure ?: $l->unit))))->map(function ($items, $key) {
            $first = $items->first();
            return ['group_key' => $key, 'product' => $first->product_name, 'category' => $first->product_category ?: $first->category, 'producer_count' => $items->pluck('producer_id')->unique()->count(), 'total_available' => $items->sum(fn ($l) => (float) ($l->quantity_available ?? $l->quantity ?? 0)), 'unit' => $first->unit_of_measure ?: $first->unit, 'records' => $items->map(fn ($l) => ['listing_id' => $l->listing_id, 'producer' => trim(($l->producer?->user?->first_name ?? '') . ' ' . ($l->producer?->user?->last_name ?? '')), 'quantity' => $l->quantity_available ?? $l->quantity, 'unit' => $l->unit_of_measure ?: $l->unit, 'location' => $l->location ?: $l->producer?->location])->values()];
        })->values();
        return response()->json($groups);
    }

    public function preview(Request $request)
    {
        $params = $this->validated($request);
        return response()->json($this->pageSnapshot($request, $this->build($params, $request->user())));
    }

    public function generate(Request $request)
    {
        $params = $this->validated($request);
        $report = $this->build($params, $request->user());
        $id = DB::table('generated_reports')->insertGetId([
            'generated_by' => $request->user()->user_id,
            'report_type' => $params['type'], 'title' => $report['title'],
            'start_date' => $params['start_date'], 'end_date' => $params['end_date'],
            'filters' => json_encode($params['filters'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'snapshot' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $report['report_reference'] = 'HR-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $report = app(ReportPresentationService::class)->decorate($report);
        DB::table('generated_reports')->where('id', $id)->update([
            'snapshot' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return response()->json([
            'id' => $id,
            'report' => $this->pageSnapshot($request, $report),
        ], 201);
    }

    public function download(Request $request, int $id, string $format = 'csv'): Response
    {
        $row = $this->ownedReport($request, $id);
        $snapshot = app(ReportPresentationService::class)->decorate($this->decodeSnapshot($row->snapshot));
        $format = strtolower($format);
        Validator::make(['format' => $format], ['format' => ['required', 'in:csv,xlsx,pdf']])->validate();
        $filename = $this->filename($snapshot, $id, $format);

        return match ($format) {
            'csv' => $this->downloadCsv($snapshot, $filename),
            'xlsx' => $this->downloadXlsx($snapshot, $filename),
            'pdf' => $this->downloadPdf($snapshot, $filename),
        };
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', self::REPORT_TYPES)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'filters' => ['nullable', 'array:product,location,status'],
            'filters.product' => ['nullable', 'string', 'max:120'],
            'filters.location' => ['nullable', 'string', 'max:120'],
            'filters.status' => ['nullable', 'string', 'max:60'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ]);

        $timezone = (string) config('app.timezone', 'UTC');
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $data['start_date'], $timezone);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $data['end_date'], $timezone);
        $today = CarbonImmutable::now($timezone)->toDateString();
        if ($end->lt($start) || $end->toDateString() > $today) {
            throw ValidationException::withMessages(['end_date' => ['The end date must be on or after the start date and not in the future.']]);
        }

        $filters = array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $data['filters'] ?? []), static fn ($value) => $value !== null && $value !== '');
        $supportedFilters = match ($data['type']) {
            'producer_activity', 'demand_summary' => ['product', 'location', 'status'],
            'supply_demand' => ['product', 'location'],
            'order_transactions' => ['product', 'status'],
            default => [],
        };
        $unsupportedFilters = array_diff(array_keys($filters), $supportedFilters);
        if ($unsupportedFilters !== []) {
            throw ValidationException::withMessages(['filters' => ['Unsupported filter(s) for this report: ' . implode(', ', $unsupportedFilters) . '.']]);
        }

        $data['filters'] = $filters;
        $data['timezone'] = $timezone;
        return $data;
    }

    private function build(array $params, User $admin): array
    {
        $preparedBy = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? ''));
        $report = [
            'report_type' => $params['type'],
            'title' => $this->reportTitle($params['type']),
            'report_reference' => 'Not assigned (preview)',
            'period' => $params['start_date'] . ' to ' . $params['end_date'],
            'reporting_period' => [
                'start_date' => $params['start_date'],
                'end_date' => $params['end_date'],
                'timezone' => $params['timezone'],
                'date_basis' => $this->dateBasis($params['type']),
                'boundaries' => 'Inclusive calendar dates in the application timezone.',
            ],
            'filters' => $params['filters'],
            'generated_at' => CarbonImmutable::now($params['timezone'])->toIso8601String(),
            'prepared_by' => $preparedBy !== '' ? $preparedBy : 'Not available',
            'prepared_by_role' => 'Admin',
            'definitions' => [],
            'columns' => [],
            'summary' => [],
            'row_count' => 0,
            'rows' => [],
        ];

        $report = match ($params['type']) {
            'producer_activity' => $this->buildProducerActivity($report, $params),
            'supply_demand' => $this->buildSupplyDemand($report, $params),
            'demand_summary' => $this->buildDemandSummary($report, $params),
            'order_transactions' => $this->buildOrderTransactions($report, $params),
        };
        $report['row_count'] = count($report['rows']);

        return app(ReportPresentationService::class)->decorate($report);
    }

    private function buildProducerActivity(array $report, array $params): array
    {
        $filters = $params['filters'];
        $userCreatedAt = 'users.' . User::accountCreatedAtColumn();
        $listingStats = DB::table('product_listings')
            ->whereBetween('created_at', $this->timestampRange($params))
            ->when(isset($filters['product']), fn ($query) => $query->where('product_name', 'like', '%' . $filters['product'] . '%'))
            ->select('producer_id')
            ->selectRaw('COUNT(*) AS listings_created')
            ->groupBy('producer_id');

        $query = DB::table('users')
            ->join('producers', 'producers.user_id', '=', 'users.user_id')
            ->leftJoinSub($listingStats, 'listing_activity', 'listing_activity.producer_id', '=', 'producers.producer_id')
            ->whereIn('users.user_type', ['Farmer', 'Fisherfolk', 'Producer'])
            ->whereBetween($userCreatedAt, $this->timestampRange($params))
            ->when(isset($filters['location']), fn ($builder) => $builder->where('producers.location', 'like', '%' . $filters['location'] . '%'))
            ->when(isset($filters['status']), fn ($builder) => $builder->whereRaw('LOWER(TRIM(producers.verification_status)) = ?', [strtolower($filters['status'])]))
            ->when(isset($filters['product']), fn ($builder) => $builder->where('listing_activity.listings_created', '>', 0))
            ->select([
                'users.user_id as producer_user_id',
                'users.first_name',
                'users.last_name',
                $userCreatedAt . ' as registered_at',
                'producers.producer_id',
                'producers.producer_type',
                'producers.verification_status',
                'producers.location',
            ])
            ->selectRaw('COALESCE(listing_activity.listings_created, 0) AS listings_created')
            ->orderBy($userCreatedAt)
            ->orderBy('users.user_id');

        $rows = $query->get()->map(fn ($row) => [
            'producer_id' => (int) $row->producer_id,
            'producer_name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
            'producer_type' => $row->producer_type,
            'verification_status' => $row->verification_status ?: 'Not assessed',
            'location' => $row->location,
            'registered_at' => $row->registered_at,
            'listings_created_in_period' => (int) $row->listings_created,
            'activity_status' => (int) $row->listings_created > 0 ? 'Created listing' : 'No listing created',
        ])->values()->all();

        $report['definitions'] = [
            'rows' => 'One producer profile whose user account was created in the selected period. Producer name is included for authorized administrative reporting; email and contact data are omitted.',
            'registered_at' => 'Canonical user account creation date, inclusive in the selected application-timezone date range.',
            'listings_created_in_period' => 'Count of listing records created in the same range; the product filter, when supplied, applies to these listing records and limits producers to those with a match.',
            'activity_status' => 'Created listing means at least one listing was created in the selected period; it does not imply sales or revenue.',
        ];
        $report['columns'] = [
            ['key' => 'producer_id', 'label' => 'Producer ID'],
            ['key' => 'producer_name', 'label' => 'Producer'],
            ['key' => 'producer_type', 'label' => 'Producer Type'],
            ['key' => 'verification_status', 'label' => 'Verification Status'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'registered_at', 'label' => 'Account Registration Date'],
            ['key' => 'listings_created_in_period', 'label' => 'Listings Created'],
            ['key' => 'activity_status', 'label' => 'Activity'],
        ];
        $report['rows'] = $rows;
        $report['summary'] = [
            'producers' => count($rows),
            'verified_producers' => count(array_filter($rows, fn ($row) => strtolower((string) $row['verification_status']) === 'verified')),
            'producers_with_listing_activity' => count(array_filter($rows, fn ($row) => $row['listings_created_in_period'] > 0)),
            'listings_created' => array_sum(array_column($rows, 'listings_created_in_period')),
        ];

        return $report;
    }

    private function buildSupplyDemand(array $report, array $params): array
    {
        $filters = $params['filters'];
        $listingUnit = "LOWER(COALESCE(NULLIF(TRIM(product_listings.unit_of_measure), ''), NULLIF(TRIM(product_listings.unit), ''), 'unspecified'))";
        $listingUnitLabel = "MIN(COALESCE(NULLIF(TRIM(product_listings.unit_of_measure), ''), NULLIF(TRIM(product_listings.unit), ''), 'Unspecified'))";
        $listingQuantity = 'COALESCE(product_listings.quantity_available, product_listings.quantity, 0)';

        $supplyQuery = DB::table('product_listings')
            ->join('producers', 'producers.producer_id', '=', 'product_listings.producer_id')
            ->where('producers.verification_status', 'Verified')
            ->whereBetween('product_listings.created_at', $this->timestampRange($params))
            ->whereIn(DB::raw('LOWER(TRIM(product_listings.status))'), ['active', 'available'])
            ->whereRaw("{$listingQuantity} > 0")
            ->when(isset($filters['product']), fn ($query) => $query->where('product_listings.product_name', 'like', '%' . $filters['product'] . '%'))
            ->when(isset($filters['location']), fn ($query) => $query->where(fn ($location) => $location->where('product_listings.location', 'like', '%' . $filters['location'] . '%')->orWhere('producers.location', 'like', '%' . $filters['location'] . '%')))
            ->when(isset($filters['status']), fn ($query) => $query->whereRaw('LOWER(TRIM(product_listings.status)) = ?', [strtolower($filters['status'])]));

        $supply = $supplyQuery
            ->selectRaw("LOWER(TRIM(product_listings.product_name)) AS product_key, MIN(TRIM(product_listings.product_name)) AS product_name, {$listingUnit} AS unit_key, {$listingUnitLabel} AS unit, COUNT(*) AS record_count, SUM({$listingQuantity}) AS quantity")
            ->groupBy('product_key', 'unit_key')
            ->orderBy('product_key')
            ->orderBy('unit_key')
            ->get()
            ->map(fn ($row) => [
                'record_type' => 'Available Supply',
                'product_name' => $row->product_name,
                'unit' => $row->unit,
                'records' => (int) $row->record_count,
                'quantity' => (float) $row->quantity,
            ])
            ->all();

        $demandUnit = "LOWER(COALESCE(NULLIF(TRIM(unit), ''), 'unspecified'))";
        $demandUnitLabel = "MIN(COALESCE(NULLIF(TRIM(unit), ''), 'Unspecified'))";
        $activeDemandStatuses = ['active', 'offers received', 'open'];
        $demandQuery = DB::table('demand_requests')
            ->whereBetween('created_at', $this->timestampRange($params))
            ->whereIn(DB::raw('LOWER(TRIM(status))'), $activeDemandStatuses)
            ->where(fn ($query) => $query->whereNull('deadline')->orWhereDate('deadline', '>=', now((string) config('app.timezone', 'UTC'))->toDateString()))
            ->when(isset($filters['product']), fn ($query) => $query->where('product_name', 'like', '%' . $filters['product'] . '%'))
            ->when(isset($filters['location']), fn ($query) => $query->where('location', 'like', '%' . $filters['location'] . '%'))
            ->when(isset($filters['status']), fn ($query) => $query->whereRaw('LOWER(TRIM(status)) = ?', [strtolower($filters['status'])]));

        $demand = $demandQuery
            ->selectRaw("LOWER(TRIM(product_name)) AS product_key, MIN(TRIM(product_name)) AS product_name, {$demandUnit} AS unit_key, {$demandUnitLabel} AS unit, COUNT(*) AS record_count, SUM(quantity_needed) AS quantity")
            ->groupBy('product_key', 'unit_key')
            ->orderBy('product_key')
            ->orderBy('unit_key')
            ->get()
            ->map(fn ($row) => [
                'record_type' => 'Active Buyer Demand',
                'product_name' => $row->product_name,
                'unit' => $row->unit,
                'records' => (int) $row->record_count,
                'quantity' => (float) $row->quantity,
            ])
            ->all();

        $report['definitions'] = [
            'date_basis' => 'Supply and demand records are selected by their record creation date; current listing quantity and current request status/deadline also apply.',
            'available_supply' => 'Verified producer listings with Active or Available status and positive canonical quantity (quantity_available, falling back to quantity only when null).',
            'active_demand' => 'Demand requests in Active, Offers Received, or legacy open status, with no deadline or a deadline today/future in the application timezone.',
            'grouping' => 'Rows are grouped by exact trimmed case-insensitive product name and exact normalized unit, and supply and demand remain separate rows.',
            'amounts' => 'Supply quantities reflect current listing availability; request quantities reflect requested demand. They are not historical quantity snapshots or completed sales.',
        ];
        $report['columns'] = [
            ['key' => 'record_type', 'label' => 'Record Type'],
            ['key' => 'product_name', 'label' => 'Product'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'records', 'label' => 'Record Count'],
            ['key' => 'quantity', 'label' => 'Current Quantity'],
        ];
        $report['rows'] = array_merge($supply, $demand);
        $report['summary'] = [
            'available_listing_groups' => count($supply),
            'active_demand_groups' => count($demand),
            'supply_totals_by_unit' => $this->sumByUnit($supply),
            'demand_totals_by_unit' => $this->sumByUnit($demand),
        ];

        return $report;
    }

    private function buildDemandSummary(array $report, array $params): array
    {
        $filters = $params['filters'];
        $query = DemandRequest::query()
            ->with('buyer:buyer_id,organization_name')
            ->whereBetween('created_at', $this->timestampRange($params))
            ->when(isset($filters['product']), fn ($builder) => $builder->where('product_name', 'like', '%' . $filters['product'] . '%'))
            ->when(isset($filters['location']), fn ($builder) => $builder->where('location', 'like', '%' . $filters['location'] . '%'))
            ->when(isset($filters['status']), fn ($builder) => $builder->whereRaw('LOWER(TRIM(status)) = ?', [strtolower($filters['status'])]))
            ->orderBy('created_at')
            ->orderBy('request_id');

        $rows = $query->get()->map(fn (DemandRequest $demand) => [
            'request_id' => (int) $demand->request_id,
            'buyer_organization' => $demand->buyer?->organization_name ?: 'Buyer #' . $demand->buyer_id,
            'product_name' => $demand->product_name,
            'category' => $demand->category,
            'quantity_requested' => (float) $demand->quantity_needed,
            'unit' => $demand->unit ?: 'Unspecified',
            'target_price_per_unit' => $demand->max_price_per_unit,
            'location' => $demand->location,
            'status' => $demand->status,
            'deadline' => $demand->deadline?->toDateString(),
            'request_created_at' => $demand->created_at?->toIso8601String(),
        ])->values()->all();

        $report['definitions'] = [
            'rows' => 'One demand request record created in the selected date range.',
            'request_created_at' => 'Demand request creation date, inclusive in the application-timezone date range.',
            'requested_quantities_by_unit' => 'Sum of quantity_needed grouped by the stored request unit; quantities in different units are never combined.',
            'buyer_organization' => 'Buyer organization label only; personal contact details are omitted.',
        ];
        $report['columns'] = [
            ['key' => 'request_id', 'label' => 'Request ID'],
            ['key' => 'buyer_organization', 'label' => 'Buyer Organization'],
            ['key' => 'product_name', 'label' => 'Product'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'quantity_requested', 'label' => 'Quantity Requested'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'target_price_per_unit', 'label' => 'Target Price per Unit'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'deadline', 'label' => 'Deadline'],
            ['key' => 'request_created_at', 'label' => 'Request Date'],
        ];
        $report['rows'] = $rows;
        $report['summary'] = [
            'demand_requests' => count($rows),
            'requested_quantities_by_unit' => $this->sumByUnit(array_map(fn ($row) => [
                'unit' => $row['unit'],
                'quantity' => $row['quantity_requested'],
            ], $rows)),
            'status_counts' => $this->countBy($rows, 'status'),
        ];

        return $report;
    }

    private function buildOrderTransactions(array $report, array $params): array
    {
        $filters = $params['filters'];
        $orderDate = $this->orderDateExpression();
        $query = Order::query()
            ->with(['buyer:buyer_id,organization_name', 'details.listing:listing_id,product_name'])
            ->whereRaw("{$orderDate} BETWEEN ? AND ?", [$params['start_date'], $params['end_date']])
            ->when(isset($filters['product']), fn ($builder) => $builder->whereHas('details.listing', fn ($listing) => $listing->where('product_name', 'like', '%' . $filters['product'] . '%')))
            ->when(isset($filters['status']), fn ($builder) => $builder->whereRaw('LOWER(TRIM(fulfillment_status)) = ?', [strtolower($filters['status'])]))
            ->orderByRaw("{$orderDate} ASC")
            ->orderBy('order_id');

        $rows = $query->get()->map(function (Order $order) use ($filters) {
            $products = $order->details
                ->map(fn ($detail) => $detail->listing?->product_name)
                ->filter()
                ->unique()
                ->values();
            if (isset($filters['product'])) {
                $products = $products->filter(fn ($product) => stripos($product, $filters['product']) !== false)->values();
            }

            return [
                'order_id' => (int) $order->order_id,
                'order_date' => $order->order_date?->toDateString() ?? $order->created_at?->toDateString(),
                'buyer_organization' => $order->buyer?->organization_name ?: 'Buyer #' . $order->buyer_id,
                'products' => $products->implode('; '),
                'fulfillment_status' => $order->fulfillment_status ?: 'Unknown',
                'payment_status' => $order->payment_status ?: 'Unknown',
                'delivery_date' => $order->delivery_date?->toDateString(),
                'recorded_order_amount' => (float) $order->total_amount,
            ];
        })->values()->all();

        $report['definitions'] = [
            'rows' => 'One persisted order record. Product filters select orders that include at least one matching order detail/listing.',
            'order_date' => 'The order date, using the record creation date only when an order date is unavailable; inclusive application-timezone calendar dates.',
            'recorded_order_amount' => 'The stored amount for the full order; this is an order value, not realized revenue, income, or profit.',
            'statuses' => 'Fulfillment and payment statuses are the values stored by the existing negotiation/order workflow. No status is inferred as a sale.',
            'buyer_organization' => 'Buyer organization label only; shipping address and personal contact information are omitted.',
        ];
        $report['columns'] = [
            ['key' => 'order_id', 'label' => 'Order ID'],
            ['key' => 'order_date', 'label' => 'Order Date'],
            ['key' => 'buyer_organization', 'label' => 'Buyer Organization'],
            ['key' => 'products', 'label' => 'Products'],
            ['key' => 'fulfillment_status', 'label' => 'Fulfillment Status'],
            ['key' => 'payment_status', 'label' => 'Payment Status'],
            ['key' => 'delivery_date', 'label' => 'Delivery Date'],
            ['key' => 'recorded_order_amount', 'label' => 'Recorded Order Amount (Not Revenue)'],
        ];
        $report['rows'] = $rows;
        $report['summary'] = [
            'orders' => count($rows),
            'fulfillment_status_counts' => $this->countBy($rows, 'fulfillment_status'),
            'payment_status_counts' => $this->countBy($rows, 'payment_status'),
            'recorded_order_amount_total_not_revenue' => array_sum(array_column($rows, 'recorded_order_amount')),
        ];

        return $report;
    }

    private function timestampRange(array $params): array
    {
        $timezone = $params['timezone'];
        return [
            CarbonImmutable::createFromFormat('!Y-m-d', $params['start_date'], $timezone)->startOfDay(),
            CarbonImmutable::createFromFormat('!Y-m-d', $params['end_date'], $timezone)->endOfDay(),
        ];
    }

    private function orderDateExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite', 'mysql' => 'DATE(COALESCE(orders.order_date, orders.created_at))',
            'pgsql' => 'CAST(COALESCE(CAST(orders.order_date AS TIMESTAMP), orders.created_at) AS DATE)',
            default => throw new \RuntimeException('Order reports are not supported for this database driver.'),
        };
    }

    private function sumByUnit(array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $unit = trim((string) ($row['unit'] ?? '')) ?: 'Unspecified';
            $totals[$unit] = ($totals[$unit] ?? 0.0) + (float) ($row['quantity'] ?? 0);
        }

        ksort($totals, SORT_NATURAL | SORT_FLAG_CASE);
        return array_map(static fn ($unit, $quantity) => ['unit' => $unit, 'quantity' => $quantity], array_keys($totals), array_values($totals));
    }

    private function countBy(array $rows, string $key): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row[$key] ?? '')) ?: 'Unknown';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
        return array_map(static fn ($value, $count) => ['value' => $value, 'count' => $count], array_keys($counts), array_values($counts));
    }

    private function reportTitle(string $type): string
    {
        return match ($type) {
            'producer_activity' => 'Producer Activity Report',
            'supply_demand' => 'Supply-Demand Report',
            'demand_summary' => 'Demand Summary Report',
            'order_transactions' => 'Order Transactions Report',
        };
    }

    private function dateBasis(string $type): string
    {
        return match ($type) {
            'producer_activity' => 'Canonical Producer account creation date; listing activity uses listing creation dates.',
            'supply_demand', 'demand_summary' => 'Record creation date.',
            'order_transactions' => 'Order date; record creation date is used only when the order date is unavailable.',
        };
    }

    private function pageSnapshot(Request $request, array $snapshot): array
    {
        $snapshot = app(ReportPresentationService::class)->decorate($snapshot);
        [$page, $perPage] = $this->paginationParams($request);
        $rows = $snapshot['rows'] ?? [];
        $total = count($rows);

        $snapshot['row_count'] = (int) ($snapshot['row_count'] ?? $total);
        $snapshot['rows'] = array_slice($rows, ($page - 1) * $perPage, $perPage);
        $snapshot['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total_rows' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];

        return $snapshot;
    }

    private function paginationParams(Request $request): array
    {
        $pagination = Validator::make($request->only(['page', 'per_page']), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ])->validate();

        return [(int) ($pagination['page'] ?? 1), (int) ($pagination['per_page'] ?? self::PER_PAGE)];
    }

    private function ownedReport(Request $request, int $id): object
    {
        $report = DB::table('generated_reports')
            ->where('id', $id)
            ->where('generated_by', $request->user()->user_id)
            ->first();
        abort_unless($report, 404, 'Report unavailable.');

        return $report;
    }

    private function decodeSnapshot(string $snapshot): array
    {
        $report = json_decode($snapshot, true);
        abort_unless(is_array($report) && is_array($report['rows'] ?? null), 500, 'Saved report snapshot is unavailable.');

        return $report;
    }

    private function filename(array $report, int $id, string $format): string
    {
        $title = \Illuminate\Support\Str::slug((string) ($report['title'] ?? 'harborai-report')) ?: 'harborai-report';
        return substr($title, 0, 80) . '-' . $id . '.' . $format;
    }

    private function downloadCsv(array $report, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('Unable to open CSV report output stream.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            $this->writePreambleCsv($output, $report);
            fputcsv($output, ['5. Detailed Records'], ',', '"', '');
            $headers = array_column($report['columns'] ?? [], 'label');
            $keys = array_column($report['columns'] ?? [], 'key');
            fputcsv($output, $headers, ',', '"', '');
            if (($report['rows'] ?? []) === []) {
                fputcsv($output, ['No records found.'], ',', '"', '');
            } else {
                foreach ($report['rows'] as $row) {
                    fputcsv($output, array_map(fn ($key) => $this->csvValue($row[$key] ?? null), $keys), ',', '"', '');
                }
            }
            $this->writePostambleCsv($output, $report);
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function downloadXlsx(array $report, string $filename): Response
    {
        $path = tempnam(sys_get_temp_dir(), 'harborai-report-');
        if ($path === false) {
            abort(500, 'Unable to prepare report export.');
        }

        try {
            $writer = new XlsxWriter();
            $writer->setCreator('HarborAI');
            $writer->openToFile($path);
            $sheetName = mb_substr(preg_replace('/[\\\\\/\?\*:\[\]]/', ' ', (string) ($report['title'] ?? 'Report')) ?: 'Report', 0, 31);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName($sheetName);
            $headerStyle = (new Style())
                ->setFontBold()
                ->setFontColor(Color::WHITE)
                ->setBackgroundColor(Color::toARGB('123C5C'));
            $this->writePreambleXlsx($writer, $report);
            $writer->addRow(Row::fromValues(['5. Detailed Records']));
            $columns = $report['columns'] ?? [];
            foreach ($columns as $index => $column) {
                $width = min(40, max(14, mb_strlen((string) ($column['label'] ?? 'Column')) + 2));
                $sheet->setColumnWidth((float) $width, $index + 1);
            }
            $writer->addRow(Row::fromValues(array_column($columns, 'label'), $headerStyle));
            $keys = array_column($columns, 'key');
            if (($report['rows'] ?? []) === []) {
                $writer->addRow(Row::fromValues(['No records found.']));
            } else {
                foreach ($report['rows'] as $row) {
                    $writer->addRow(Row::fromValues(array_map(fn ($key) => $this->cellValue($row[$key] ?? null), $keys)));
                }
            }
            $this->writePostambleXlsx($writer, $report);
            $writer->close();
        } catch (Throwable $exception) {
            @unlink($path);
            \Illuminate\Support\Facades\Log::error('Report XLSX export failed.', ['exception' => $exception::class]);
            abort(500, 'Unable to generate requested report export.');
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function downloadPdf(array $report, string $filename): \Illuminate\Http\Response
    {
        try {
            $html = app(ReportTemplateRenderer::class)->render($report);
            return Pdf::loadHTML($html, 'UTF-8')->setPaper('a4', 'portrait')->download($filename);
        } catch (Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('Report PDF export failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            abort(500, 'Unable to generate requested report export.');
        }
    }

    private function writePreambleCsv($output, array $report): void
    {
        $presentation = $report['presentation'] ?? app(ReportPresentationService::class)->decorate($report)['presentation'];
        fputcsv($output, ['Report', $this->csvValue($report['title'] ?? 'HarborAI Report')], ',', '"', '');
        foreach ($presentation['metadata'] as $item) {
            fputcsv($output, [$item['label'], $this->csvValue($item['value'])], ',', '"', '');
        }
        fputcsv($output, ['Date Basis', $this->csvValue($report['reporting_period']['date_basis'] ?? '')], ',', '"', '');
        fputcsv($output, ['Result Rows', (string) ($report['row_count'] ?? count($report['rows'] ?? []))], ',', '"', '');
        fputcsv($output, ['Filters'], ',', '"', '');
        $filters = $report['filters'] ?? [];
        if ($filters === []) {
            fputcsv($output, ['None applied'], ',', '"', '');
        } else {
            fputcsv($output, ['Filter', 'Value'], ',', '"', '');
            foreach ($filters as $key => $value) {
                fputcsv($output, [$this->humanize((string) $key), $this->csvValue($value)], ',', '"', '');
            }
        }
        fputcsv($output, [], ',', '"', '');
        fputcsv($output, ['1. Executive Summary', $this->csvValue($presentation['executive_summary'])], ',', '"', '');
        fputcsv($output, ['2. Purpose and Scope', $this->csvValue($presentation['purpose_scope'])], ',', '"', '');
        fputcsv($output, ['3. Key Highlights'], ',', '"', '');
        $highlightSummary = $presentation['highlight_summary'] ?? [];
        fputcsv($output, ['TOTAL RECORDS', 'KEY METRIC', 'CHANGE / STATUS'], ',', '"', '');
        fputcsv($output, [
            (string) ($highlightSummary['total_records'] ?? ''),
            $this->csvValue($highlightSummary['key_metric'] ?? ''),
            $this->csvValue($highlightSummary['change_status'] ?? ''),
        ], ',', '"', '');
        fputcsv($output, ['Metric', 'Value'], ',', '"', '');
        foreach ($presentation['highlights'] as $metric) {
            fputcsv($output, [$metric['label'], $this->csvValue($metric['value'])], ',', '"', '');
        }
        foreach ($presentation['summary_tables'] as $table) {
            fputcsv($output, [], ',', '"', '');
            fputcsv($output, [$table['title']], ',', '"', '');
            fputcsv($output, array_column($table['columns'], 'label'), ',', '"', '');
            foreach ($table['rows'] as $row) {
                fputcsv($output, array_map(fn ($column) => $this->csvValue($row[$column['key']] ?? null), $table['columns']), ',', '"', '');
            }
        }
        foreach ([
            '4. Findings and Observations' => $presentation['findings'],
        ] as $section => $items) {
            fputcsv($output, [], ',', '"', '');
            fputcsv($output, [$section], ',', '"', '');
            foreach ($items as $item) {
                fputcsv($output, [$item], ',', '"', '');
            }
        }
        fputcsv($output, [], ',', '"', '');
    }

    private function writePostambleCsv($output, array $report): void
    {
        $presentation = $report['presentation'] ?? app(ReportPresentationService::class)->decorate($report)['presentation'];
        foreach ([
            '6. Analysis and Interpretation' => $presentation['analysis'],
            '7. Issues, Limitations, and Data Notes' => $presentation['limitations'],
        ] as $section => $items) {
            fputcsv($output, [], ',', '"', '');
            fputcsv($output, [$section], ',', '"', '');
            foreach ($items as $item) {
                fputcsv($output, [$item], ',', '"', '');
            }
        }
        fputcsv($output, [], ',', '"', '');
        fputcsv($output, ['8. Conclusion', $this->csvValue($presentation['conclusion'])], ',', '"', '');
        fputcsv($output, ['9. Recommendations / Next Steps', $this->csvValue($presentation['recommendations'])], ',', '"', '');
        fputcsv($output, [], ',', '"', '');
        fputcsv($output, ['10. Review and Approval'], ',', '"', '');
        $approval = $presentation['approval'] ?? [];
        fputcsv($output, ['PREPARED BY', $this->csvValue($approval['prepared_by'] ?? 'Not available')], ',', '"', '');
        fputcsv($output, ['REVIEWED BY', $this->csvValue($approval['reviewed_by'] ?? '')], ',', '"', '');
        fputcsv($output, ['APPROVED BY', $this->csvValue($approval['approved_by'] ?? '')], ',', '"', '');
        fputcsv($output, [], ',', '"', '');
    }

    private function writePreambleXlsx(XlsxWriter $writer, array $report): void
    {
        $presentation = $report['presentation'] ?? app(ReportPresentationService::class)->decorate($report)['presentation'];
        $writer->addRow(Row::fromValues(['Report', $this->cellValue($report['title'] ?? 'HarborAI Report')]));
        foreach ($presentation['metadata'] as $item) {
            $writer->addRow(Row::fromValues([$item['label'], $this->cellValue($item['value'])]));
        }
        $writer->addRow(Row::fromValues(['Date Basis', $this->cellValue($report['reporting_period']['date_basis'] ?? '')]));
        $writer->addRow(Row::fromValues(['Result Rows', (string) ($report['row_count'] ?? count($report['rows'] ?? []))]));
        $writer->addRow(Row::fromValues(['Filters']));
        $filters = $report['filters'] ?? [];
        if ($filters === []) {
            $writer->addRow(Row::fromValues(['None applied']));
        } else {
            $writer->addRow(Row::fromValues(['Filter', 'Value']));
            foreach ($filters as $key => $value) {
                $writer->addRow(Row::fromValues([$this->humanize((string) $key), $this->cellValue($value)]));
            }
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['1. Executive Summary', $presentation['executive_summary']]));
        $writer->addRow(Row::fromValues(['2. Purpose and Scope', $presentation['purpose_scope']]));
        $writer->addRow(Row::fromValues(['3. Key Highlights']));
        $highlightSummary = $presentation['highlight_summary'] ?? [];
        $writer->addRow(Row::fromValues(['TOTAL RECORDS', 'KEY METRIC', 'CHANGE / STATUS']));
        $writer->addRow(Row::fromValues([
            (string) ($highlightSummary['total_records'] ?? ''),
            $this->cellValue($highlightSummary['key_metric'] ?? ''),
            $this->cellValue($highlightSummary['change_status'] ?? ''),
        ]));
        $writer->addRow(Row::fromValues(['Metric', 'Value']));
        foreach ($presentation['highlights'] as $metric) {
            $writer->addRow(Row::fromValues([$metric['label'], $this->cellValue($metric['value'])]));
        }
        foreach ($presentation['summary_tables'] as $table) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([$table['title']]));
            $writer->addRow(Row::fromValues(array_column($table['columns'], 'label')));
            foreach ($table['rows'] as $row) {
                $writer->addRow(Row::fromValues(array_map(fn ($column) => $this->cellValue($row[$column['key']] ?? null), $table['columns'])));
            }
        }
        foreach ([
            '4. Findings and Observations' => $presentation['findings'],
        ] as $section => $items) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([$section]));
            foreach ($items as $item) {
                $writer->addRow(Row::fromValues([$item]));
            }
        }
        $writer->addRow(Row::fromValues([]));
    }

    private function writePostambleXlsx(XlsxWriter $writer, array $report): void
    {
        $presentation = $report['presentation'] ?? app(ReportPresentationService::class)->decorate($report)['presentation'];
        foreach ([
            '6. Analysis and Interpretation' => $presentation['analysis'],
            '7. Issues, Limitations, and Data Notes' => $presentation['limitations'],
        ] as $section => $items) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([$section]));
            foreach ($items as $item) {
                $writer->addRow(Row::fromValues([$item]));
            }
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['8. Conclusion', $presentation['conclusion']]));
        $writer->addRow(Row::fromValues(['9. Recommendations / Next Steps', $presentation['recommendations']]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['10. Review and Approval']));
        $approval = $presentation['approval'] ?? [];
        $writer->addRow(Row::fromValues(['PREPARED BY', $this->cellValue($approval['prepared_by'] ?? 'Not available')]));
        $writer->addRow(Row::fromValues(['REVIEWED BY', $this->cellValue($approval['reviewed_by'] ?? '')]));
        $writer->addRow(Row::fromValues(['APPROVED BY', $this->cellValue($approval['approved_by'] ?? '')]));
        $writer->addRow(Row::fromValues([]));
    }

    private function csvValue(mixed $value): string
    {
        $text = $this->displayValue($value);
        if (preg_match('/^[\t\r ]*[=+\-@]/u', $text) === 1) {
            return "'" . $text;
        }
        return $text;
    }

    private function cellValue(mixed $value): string|int|float|bool|null
    {
        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }
        return $this->displayValue($value);
    }

    private function displayValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return 'See structured report table';
        }
        if ($value === null) {
            return '';
        }
        return (string) $value;
    }

    private function humanize(string $key): string
    {
        return \Illuminate\Support\Str::of($key)->replace('_', ' ')->title()->toString();
    }
}
