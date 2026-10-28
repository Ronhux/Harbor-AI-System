<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Services\Marketplace\ListingAvailabilityService;
use App\Rules\LoginNumberNotTaken;
use App\Services\Registry\ProducerRegistryMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProducerController extends Controller
{
    private const REGISTRY_PRODUCER_USER_TYPES = ['Farmer', 'Fisherfolk', 'Producer'];

    /**
     * Derived availability for one listing.
     *
     * Reserved / delivered / sold quantities are read from real order records. This never
     * mutates quantity_available, so stock can never be deducted twice or restored wrongly.
     *
     * The totals map is supplied by the caller and computed exactly once per request, so the
     * query count never scales with the number of listings. It is deliberately NOT cached on
     * the controller instance: a controller instance can be reused across requests inside a
     * single process, which would silently serve a previous request's figures.
     *
     * @param  array<int, array<string, mixed>>  $availabilityTotals
     * @return array<string, mixed>
     */
    private function listingAvailability(ProductListing $listing, array $availabilityTotals): array
    {
        return app(ListingAvailabilityService::class)->present($listing, $availabilityTotals);
    }

    /**
     * One availability query for a whole response.
     *
     * @return array<int, array<string, mixed>>
     */
    private function availabilityTotalsFor(?int $producerId): array
    {
        if ($producerId === null || $producerId <= 0) {
            return [];
        }

        return app(ListingAvailabilityService::class)->forProducer($producerId);
    }

    /**
     * Fulfillment statuses that count as realized revenue. Cancelled,
     * rejected and unfinished orders are deliberately excluded.
     */
    private const REVENUE_FULFILLMENT_STATUSES = ['Delivered', 'Completed'];

    /**
     * Fulfillment statuses that close an order. Any other status, including
     * the legacy 'Pending' column default, is still in progress.
     */
    private const TERMINAL_FULFILLMENT_STATUSES = [
        'Delivered',
        'Completed',
        'Cancelled',
        'Rejected',
        'Failed',
    ];

    /**
     * Fulfillment statuses that mean an order never became a sale. The
     * fulfillment workflow returns their stock, so they are not counted as
     * orders on a listing.
     */
    private const UNFULFILLED_FULFILLMENT_STATUSES = ['Cancelled', 'Rejected'];

    /**
     * Number of calendar months reported by the revenue trend.
     */
    private const REVENUE_TREND_MONTHS = 6;

    public function producerProfile(Request $request)
    {
        $producer = Producer::with(['user', 'registeredProducer'])
            ->where('user_id', $request->user()->user_id)
            ->firstOrFail();

        return response()->json(['data' => $this->presentProducerProfile($producer)]);
    }

    public function updateProducerProfile(Request $request)
    {
        $producer = Producer::with(['user', 'registeredProducer'])
            ->where('user_id', $request->user()->user_id)
            ->firstOrFail();
        $user = $request->user();

        $data = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->user_id, 'user_id'),
            ],
            'contact_number' => ['nullable', 'string', 'max:50', new LoginNumberNotTaken($user->user_id)],
        ]);

        $user->update([
            'email' => $data['email'],
            'contact_number' => $data['contact_number'] ?? null,
        ]);

        return response()->json([
            'message' => 'Producer profile updated.',
            'data' => $this->presentProducerProfile($producer->fresh()->load(['user', 'registeredProducer'])),
        ]);
    }

    private function presentProducerProfile(Producer $producer): array
    {
        $registry = $producer->registeredProducer;

        return [
            'producer_id' => $producer->producer_id,
            'name' => trim(($producer->user?->first_name ?? '') . ' ' . ($producer->user?->last_name ?? '')),
            'email' => $producer->user?->email,
            'contact_number' => $producer->user?->contact_number,
            'rsbsa_number' => $producer->rsbsa_number,
            'location' => $producer->location,
            'primary_product_type' => $producer->primary_product_type,
            'producer_type' => $producer->producer_type,
            'verification_status' => $producer->verification_status,
            'registry' => $registry ? [
                'rsbsa_number' => $registry->rsbsa_number,
                'full_name' => trim(implode(' ', array_filter([
                    $registry->first_name,
                    $registry->middle_name,
                    $registry->last_name,
                    $registry->extension_name,
                ], fn ($value) => is_string($value) && trim($value) !== ''))),
                'sex' => $registry->sex,
                'barangay' => $registry->barangay,
                'farm_location' => $registry->farm_location,
                'area_ha' => $registry->area_ha,
                'status' => $producer->verification_status,
            ] : null,
        ];
    }

    public function dashboard()
    {
        $user = Auth::user();
        $response = $this->requireVerifiedProducer($user);
        if ($response) {
            return $response;
        }

        $producer = Producer::where('user_id', $user->user_id)->first();

        if (!$producer) {
            $producer = Producer::firstOrCreate(
                ['user_id' => $user->user_id],
                [
                    'rsbsa_number' => null,
                    'location' => null,
                    'primary_product_type' => null,
                    'verification_status' => 'Pending',
                    'producer_type' => $user->user_type === 'Farmer' ? 'Farmer' : null,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Producer Listings
        |--------------------------------------------------------------------------
        */

        $listings = $producer->productListings()
            ->with('images')
            ->orderByDesc('created_at')
            ->get();

        // One grouped query gives every listing its real distinct order count.
        $orderCounts = $this->orderCountsByListing($listings);

        /*
        |--------------------------------------------------------------------------
        | Eligible Listings
        |--------------------------------------------------------------------------
        | An eligible listing is sellable right now: an active or available
        | status with stock on hand. Active Listings, Product Mix and the
        | average listing price all share this single population so the cards
        | and the charts can never disagree with each other.
        */

        $eligibleListings = $listings
            ->filter(fn (ProductListing $listing) => $this->isEligibleListing($listing))
            ->values();

        // A listing without a usable price cannot pull an average down.
        $pricedListings = $eligibleListings->filter(
            fn (ProductListing $listing) => $this->listingPrice($listing) > 0
        );

        $averageListingPrice = $pricedListings->isEmpty()
            ? 0.0
            : (float) $pricedListings->avg(
                fn (ProductListing $listing) => $this->listingPrice($listing)
            );

        $averagesByUnit = $pricedListings->groupBy(fn ($listing) => strtolower(trim($listing->unit_of_measure ?? $listing->unit ?? '')))
            ->filter(fn ($group, $unit) => $unit !== '')
            ->map(fn ($group, $unit) => ['unit' => $unit, 'price' => round((float) $group->avg(fn ($listing) => $this->listingPrice($listing)), 2)])
            ->values();
        $averageListingPrice = $averagesByUnit->count() === 1 ? $averagesByUnit->first()['price'] : 0.0;

        /*
        |--------------------------------------------------------------------------
        | Revenue
        |--------------------------------------------------------------------------
        | A single joined read of this producer's own realized order details
        | feeds the lifetime total, the six month trend and both month over
        | month comparisons, so revenue is never re-queried or approximated.
        |
        | A multi producer order only contributes this producer's own detail
        | subtotals, never the whole order total.
        */

        $revenueDetails = OrderDetail::query()
            ->join('orders', 'orders.order_id', '=', 'order_details.order_id')
            ->join(
                'product_listings',
                'product_listings.listing_id',
                '=',
                'order_details.listing_id'
            )
            ->where('product_listings.producer_id', $producer->producer_id)
            ->whereIn(
                'orders.fulfillment_status',
                self::REVENUE_FULFILLMENT_STATUSES
            )
            ->where('orders.payment_status', 'Paid')
            ->get(['orders.order_date', 'order_details.subtotal']);

        $totalRevenue = (float) $revenueDetails->sum(
            fn ($detail) => (float) $detail->subtotal
        );

        $currentMonth = now()->format('Y-m');
        $previousMonth = now()->subMonthNoOverflow()->format('Y-m');

        $revenueByMonth = $this->revenueByMonth($revenueDetails);
        // Compare equal month-to-date windows, rather than a partial month
        // against an entire previous month. Clamp naturally at month end.
        $comparisonCutoffs = [$currentMonth => now()->toDateString(), $previousMonth => now()->subMonthNoOverflow()->toDateString()];
        $monthlyRevenue = [];
        foreach ($revenueDetails as $detail) {
            $month = $this->monthKey($detail->order_date);
            if (isset($comparisonCutoffs[$month]) && substr((string) $detail->order_date, 0, 10) <= $comparisonCutoffs[$month]) {
                $monthlyRevenue[$month] = ($monthlyRevenue[$month] ?? 0) + (float) $detail->subtotal;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Pending Orders
        |--------------------------------------------------------------------------
        | An order stays pending until it reaches a terminal status, so every
        | other status counts, including the legacy 'Pending' default. Each
        | Order row is already one distinct order, so an order holding several
        | of this producer's products is counted exactly once.
        */

        // Share one producer-scoped order population with the total, open
        // order count and recent order list. This matches the Orders endpoint:
        // each Order row is counted once when it contains this producer's
        // listings, regardless of how many of its own lines it has.
        $producerOrdersQuery = Order::query()
            ->whereHas('details.listing', function ($query) use ($producer) {
                $query->where('producer_id', $producer->producer_id);
            });

        $totalOrders = (clone $producerOrdersQuery)->count();

        $pendingOrderQuery = (clone $producerOrdersQuery)
            ->where(function ($query) {
                $query
                    ->whereNotIn(
                        'orders.fulfillment_status',
                        self::TERMINAL_FULFILLMENT_STATUSES
                    )
                    ->orWhereNull('orders.fulfillment_status');
            });

        $pendingOrders = (clone $pendingOrderQuery)->count();

        /*
        |--------------------------------------------------------------------------
        | Month over Month Comparison
        |--------------------------------------------------------------------------
        | Pending orders are grouped by order date, the same date basis the
        | revenue trend uses, and only the current and previous month are
        | read back so the comparison stays a bounded query.
        */

        $pendingByMonth = [];

        $comparisonWindowOrders = (clone $pendingOrderQuery)
            ->whereNotNull('orders.order_date')
            ->where(
                'orders.order_date',
                '>=',
                now()->startOfMonth()->subMonthNoOverflow()
            )
            ->get(['orders.order_date']);

        foreach ($comparisonWindowOrders as $order) {
            $month = $this->monthKey($order->order_date);

            if ($month === null || !isset($comparisonCutoffs[$month]) || substr((string) $order->order_date, 0, 10) > $comparisonCutoffs[$month]) {
                continue;
            }

            $pendingByMonth[$month] = ($pendingByMonth[$month] ?? 0) + 1;
        }

        /*
        |--------------------------------------------------------------------------
        | Product Mix
        |--------------------------------------------------------------------------
        | Share of eligible listings per category. Listing counts are used
        | rather than stock or revenue so one listing weighs the same as any
        | other regardless of quantity or unit of measure.
        */

        $productMix = $eligibleListings
            ->groupBy(
                fn (ProductListing $listing) => $this->listingCategory($listing)
            )
            ->map(fn ($group, string $category) => [
                'category' => $category,
                'listings' => $group->count(),
            ])
            ->sortByDesc('listings')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Recent Orders
        |--------------------------------------------------------------------------
        */

        $recentOrders = (clone $producerOrdersQuery)->with([
            'buyer.user',
            'details.listing',
        ])
            ->orderByDesc('order_date')
            ->orderByDesc('order_id')
            ->limit(5)
            ->get()
            ->map(function ($order) use ($producer) {
                // Keep only this producer's own order details.
                $ownDetails = $order->details
                    ->filter(function ($detail) use ($producer) {
                        return $detail->listing
                            && (int) $detail->listing->producer_id
                                === (int) $producer->producer_id;
                    })
                    ->values();

                $buyer = $order->buyer;
                $buyerUser = $buyer?->user;

                return [
                    'order_id' => $order->order_id,
                    'order_date' => $order->order_date,
                    'delivery_date' => $order->delivery_date,
                    'fulfillment_status' => $order->fulfillment_status,
                    'payment_status' => $order->payment_status,

                    'buyer' => [
                        'buyer_id' => $buyer?->buyer_id,
                        'name' => trim(
                            ($buyerUser?->first_name ?? '') . ' ' .
                            ($buyerUser?->last_name ?? '')
                        ) ?: 'Buyer',
                        // Institutional buyers are recognized by their
                        // organization, so it is offered alongside the
                        // contact person's name.
                        'organization_name' => $buyer?->organization_name,
                    ],

                    'details' => $ownDetails->map(function ($detail) {
                        return [
                            'order_detail_id' => $detail->order_detail_id,
                            'listing_id' => $detail->listing_id,
                            'product_name' => $detail->listing?->product_name
                                ?? 'Product',
                            'quantity' => (float) $detail->quantity,
                            'unit' => $detail->listing?->unit_of_measure
                                ?? $detail->listing?->unit,
                            'unit_price' => (float) $detail->unit_price,
                            'subtotal' => (float) $detail->subtotal,
                        ];
                    })->values(),

                    // This is the producer's own subtotal,
                    // not the entire order total.
                    'producer_subtotal' => (float) $ownDetails->sum('subtotal'),
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        // One availability query for the whole dashboard response, not one per listing.
        $availabilityTotals = $this->availabilityTotalsFor((int) $producer->producer_id);

        return response()->json([
            'producer' => [
                'producer_id' => $producer->producer_id,
                'user_id' => $producer->user_id,
                'rsbsa_number' => $producer->rsbsa_number,
                'location' => $producer->location,
                'primary_product_type' => $producer->primary_product_type,
                'verification_status' => $producer->verification_status,
                'producer_type' => $producer->producer_type,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
            ],

            'product_listings' => $listings
                ->map(fn (ProductListing $listing) => $this->serializeListing(
                    $listing,
                    $orderCounts[$listing->listing_id] ?? 0,
                    (int) ($listing->views ?? 0),
                    $availabilityTotals
                ))
                ->values(),

            'stats' => [
                'total_revenue' => round($totalRevenue, 2),
                'active_listings' => $eligibleListings->count(),
                'pending_orders' => $pendingOrders,
                'total_orders' => $totalOrders,
                'total_views' => (int) $listings->sum(
                    fn (ProductListing $listing) => (int) ($listing->views ?? 0)
                ),
                'average_listing_price' => round($averageListingPrice, 2),
                'average_prices_by_unit' => $averagesByUnit,
                'comparison_label' => 'Month to date vs. the same date last month (clamped at month end)',
                'changes' => [
                    'total_revenue' => $this->monthOverMonthChange(
                        (float) ($monthlyRevenue[$currentMonth] ?? 0),
                        (float) ($monthlyRevenue[$previousMonth] ?? 0)
                    ),
                    'pending_orders' => $this->monthOverMonthChange(
                        (int) ($pendingByMonth[$currentMonth] ?? 0),
                        (int) ($pendingByMonth[$previousMonth] ?? 0)
                    ),
                    /*
                    | product_listings keeps no status and no price history,
                    | so a previous month value for these two metrics cannot be
                    | rebuilt from the database. They report the current value
                    | and an unavailable comparison instead of a fabricated
                    | historical figure.
                    */
                    'active_listings' => $this->unavailableChange(
                        $eligibleListings->count()
                    ),
                    'average_listing_price' => $this->unavailableChange(
                        round($averageListingPrice, 2)
                    ),
                ],
            ],

            'revenue_by_month' => $revenueByMonth,

            'product_mix' => $productMix,

            'recent_orders' => $recentOrders,
        ]);
    }

public function listings()
    {
    $response = $this->requireVerifiedProducer(Auth::user());
    if ($response) {
        return $response;
    }

    $producer = Producer::where('user_id', Auth::user()->user_id)->first();

        abort_unless($producer, 403, 'Producer account not found.');

        $listings = $producer->productListings()
            ->with('images')
            ->orderByDesc('created_at')
            ->get();

        $orderCounts = $this->orderCountsByListing($listings);
        // One availability query for the whole listings response, not one per listing.
        $availabilityTotals = $this->availabilityTotalsFor((int) $producer->producer_id);

        $data = $listings
            ->map(fn (ProductListing $listing) => $this->serializeListing(
                $listing,
                $orderCounts[$listing->listing_id] ?? 0,
                (int) ($listing->views ?? 0),
                $availabilityTotals
            ))
            ->values();

        return response()->json(['data' => $data, 'product_listings' => $data]);
    }

    public function browseProducers()
    {
        $producers = Producer::with(['user', 'productListings' => function ($query) {
            $query->whereIn(DB::raw('lower(status)'), ['active', 'available'])
                ->where(function ($available) {
                    $available->where('quantity_available', '>', 0)
                        ->orWhere('quantity', '>', 0);
                })
                ->orderByDesc('created_at');
        }])
            ->whereRaw('LOWER(verification_status) = ?', ['verified'])
            ->orderByDesc('producer_id')
            ->get();

        $data = $producers->map(function (Producer $producer) {
            $listings = $producer->productListings->map(function ($listing) {
                return [
                    'listing_id' => $listing->listing_id,
                    'product_name' => $listing->product_name,
                    'product_category' => $listing->product_category ?? $listing->category,
                    'current_price_per_unit' => (float) ($listing->current_price_per_unit ?? $listing->price_per_unit ?? 0),
                    'unit' => $listing->unit_of_measure ?? $listing->unit ?? 'kg',
                    'quantity_available' => (float) ($listing->quantity_available ?? $listing->quantity ?? 0),
                    'status' => $listing->status ?? 'Active',
                ];
            })->values();

            $name = trim(($producer->user->first_name ?? '') . ' ' . ($producer->user->last_name ?? '')) ?: 'Verified Producer';

            return [
                'id' => $producer->producer_id,
                'producer_id' => $producer->producer_id,
                'name' => $name,
                'type' => $producer->producer_type === 'Fisherfolk' ? 'Fisher' : ($producer->producer_type ?? 'Farmer'),
                'location' => $producer->location ?? 'N/A',
                'contact_number' => $producer->user->contact_number,
                'email' => $producer->user->email,
                'primary_product_type' => $producer->primary_product_type,
                'verified' => strtolower((string) ($producer->verification_status ?? 'Pending')) === 'verified',
                'products' => $listings->pluck('product_name')->filter()->values()->all(),
                'product_listings' => $listings,
                'active_listing_count' => $listings->count(),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function browseProducts()
    {
        $listings = ProductListing::query()
            ->with(['producer.user', 'images'])
            ->whereHas('producer', fn ($query) => $query->whereRaw('LOWER(verification_status) = ?', ['verified']))
            ->whereIn(DB::raw('lower(status)'), ['active', 'available'])
            ->where(function ($query) {
                $query->where('quantity_available', '>', 0)->orWhere('quantity', '>', 0);
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ProductListing $listing) {
                $data = $this->serializeListing($listing);
                $producer = $listing->producer;
                $data['producer'] = [
                    'id' => $producer->producer_id,
                    'name' => trim(($producer->user->first_name ?? '') . ' ' . ($producer->user->last_name ?? '')) ?: 'Verified Producer',
                    'location' => $producer->location,
                    'type' => $producer->producer_type,
                    'primary_product_type' => $producer->primary_product_type,
                    'verification_status' => $producer->verification_status,
                    'contact_number' => $producer->user->contact_number,
                ];
                return $data;
            })->values();

        return response()->json(['data' => $listings]);
    }

    /**
     * Records that a buyer opened a product detail dialog.
     *
     * Only the listing identifier is accepted from the client: the count is
     * owned entirely by the server and can never be set or overwritten by a
     * submitted value. A view is skipped when the listing is not one a buyer
     * could legitimately be viewing, or when the viewer owns the listing, so
     * a producer cannot inflate their own numbers.
     */
    public function recordProductView(Request $request, int $listingId)
    {
        $listing = ProductListing::with('producer')
            ->find($listingId);

        // The listing may have been deleted between page load and this request.
        if (!$listing) {
            return response()->json(['message' => 'Listing not found'], 404);
        }

        // Mirrors the browseProducts visibility rules, so a hidden listing
        // cannot be inflated by guessing identifiers.
        $producerIsVerified = strtolower((string) ($listing->producer?->verification_status ?? '')) === 'verified';

        if (!$producerIsVerified || !$this->isEligibleListing($listing)) {
            return response()->json(['message' => 'Listing is not available'], 422);
        }

        // A dual role account must not count as a buyer viewing its own listing.
        if (Producer::where('user_id', $request->user()->user_id)
            ->whereKey($listing->producer_id)
            ->exists()) {
            return response()->json(['message' => 'View not recorded'], 204);
        }

        // Atomic increment, so concurrent views cannot lose a count.
        ProductListing::whereKey($listing->listing_id)->increment('views');

        return response()->json(['recorded' => true], 201);
    }

    public function pendingVerificationRequests(Request $request, ProducerRegistryMatcher $registryMatcher)
    {
        $producers = Producer::with('user')
            ->whereIn(DB::raw('LOWER(verification_status)'), ['pending', 'rejected'])
            ->whereHas('user', fn ($query) => $query->whereIn('user_type', self::REGISTRY_PRODUCER_USER_TYPES))
            ->orderBy('producer_id')
            ->get()
            ->map(function (Producer $producer) use ($registryMatcher) {
                $user = $producer->user;
                $applicantName = trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''));
                return [
                    'producer_id' => (int) $producer->producer_id,
                    'user_id' => (int) $producer->user_id,
                    'name' => $applicantName ?: 'Pending producer',
                    'email' => $user?->email,
                    'producer_type' => $producer->producer_type,
                    'location' => $producer->location,
                    'verification_status' => $producer->verification_status,
                    'rsbsa_number' => $producer->rsbsa_number,
                    'registry_match' => $registryMatcher->match($producer->rsbsa_number, $applicantName),
                ];
            })
            ->values();

        return response()->json(['data' => $producers]);
    }

    private function requireVerifiedProducer($user)
    {
        if (!$user || !in_array(strtolower((string) $user->user_type), ['farmer', 'fisherfolk', 'producer'], true)) {
            return null;
        }

        $producer = Producer::where('user_id', $user->user_id)->first();
        if (!$producer || strtolower((string) $producer->verification_status) !== 'verified') {
            return response()->json([
                'message' => 'Your producer account is not verified and cannot access producer features yet.',
                'verification_required' => true,
            ], 403);
        }

        return null;
    }

    public function updateVerificationStatus(Request $request, int $producerId)
    {
        $request->merge(['reason' => is_string($request->input('reason')) ? trim($request->input('reason')) : $request->input('reason')]);
        $data = $request->validate([
            'verification_status' => ['required', 'in:Verified,Rejected'],
            'reason' => ['required_if:verification_status,Rejected', 'nullable', 'string', 'min:10', 'max:2000', 'regex:/[\\pL\\pN]/u'],
        ]);
        $producer = app(\App\Services\Registry\ProducerRegistrationReview::class)->decide($request->user(), $producerId, $data['verification_status'], $data['reason'] ?? null);

        return response()->json([
            'message' => 'Producer verification status updated.',
            'producer' => [
                'producer_id' => (int) $producer->producer_id,
                'user_id' => (int) $producer->user_id,
                'name' => trim(($producer->user?->first_name ?? '') . ' ' . ($producer->user?->last_name ?? '')) ?: 'Producer',
                'email' => $producer->user?->email,
                'verification_status' => $producer->verification_status,
            ],
        ]);
    }

    public function updateAccountStatus(Request $request, int $producerId)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $producer = Producer::with('user')->findOrFail($producerId);
        if (!$producer->user || !in_array($producer->user->user_type, self::REGISTRY_PRODUCER_USER_TYPES, true)) {
            return response()->json(['message' => 'Producer account not found.'], 404);
        }

        $wasActive = (bool) $producer->user->is_active;
        $isActive = (bool) $data['is_active'];
        $producer->user->is_active = $isActive;
        $producer->user->save();

        if (!$isActive) {
            $producer->user->tokens()->delete();
        }

        DB::table('admin_activity_logs')->insert([
            'user_id' => $request->user()->user_id,
            'action' => $isActive ? 'Activated producer account' : 'Suspended producer account',
            'subject' => 'producer_id=' . $producer->producer_id,
            'status' => 'success',
            'details' => json_encode([
                'producer_id' => $producer->producer_id,
                'user_id' => $producer->user->user_id,
                'previous_status' => $wasActive ? 'Active' : 'Suspended',
                'new_status' => $isActive ? 'Active' : 'Suspended',
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => $isActive ? 'Producer account activated.' : 'Producer account suspended.',
            'account_status' => $isActive ? 'Active' : 'Suspended',
        ]);
    }

    public function createProductListing(Request $request)
    {
        $user = Auth::user();
        $response = $this->requireVerifiedProducer($user);
        if ($response) {
            return $response;
        }

        $producer = Producer::where('user_id', $user->user_id)->first();

        if (!$producer) {
            return response()->json(['message' => 'Producer profile not found'], 404);
        }

        $request->validate([
            'product_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'price_per_unit' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'harvest_date' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        abort_if(count($request->file('images', [])) + (int) $request->hasFile('image') > 10, 422, 'A product listing can have at most 10 images.');

        $category = $request->input('category');
        $unit = $request->input('unit');
        $quantity = (float) $request->input('quantity');
        $price = (float) $request->input('price_per_unit');

        $data = [
            'producer_id' => $producer->producer_id,
            'product_name' => $request->input('product_name'),
            'product_category' => $category,
            'category' => $category,
            'quantity_available' => $quantity,
            'quantity' => $quantity,
            'unit_of_measure' => $unit,
            'unit' => $unit,
            'current_price_per_unit' => $price,
            'price_per_unit' => $price,
            'harvest_date' => $request->input('harvest_date'),
            'status' => 'Active',
        ];

        if ($request->filled('description')) {
            $data['description'] = $request->input('description');
        }

        if ($request->filled('location')) {
            $data['location'] = $request->input('location');
        }

        $listing = ProductListing::create($data);
        $this->saveListingImages($request, $listing);

        return response()->json([
            'message' => 'Product listing created successfully',
            'listing' => $this->serializeListing(
                $listing,
                $this->orderCountsByListing(collect([$listing]))[$listing->listing_id] ?? 0,
                (int) ($listing->views ?? 0)
            ),
        ], 201);
    }

    public function updateProductListing(Request $request, $listingId)
    {
        $user = Auth::user();
        $response = $this->requireVerifiedProducer($user);
        if ($response) {
            return $response;
        }

        $producer = Producer::where('user_id', $user->user_id)->first();
        $listing = ProductListing::where('listing_id', $listingId)
            ->where('producer_id', $producer->producer_id)
            ->firstOrFail();

        $request->validate([
            'product_name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:255',
            'quantity' => 'sometimes|required|numeric|min:0',
            'unit' => 'sometimes|required|string|max:50',
            'price_per_unit' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'harvest_date' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_image_ids' => 'nullable|array|max:10',
            'remove_image_ids.*' => 'integer',
            'remove_legacy_image' => 'nullable|boolean',
        ]);
        $removeIds = collect($request->input('remove_image_ids', []))->map(fn ($id) => (int) $id)->unique();
        $existingImageCount = $listing->images()->count();
        $legacyPath = $listing->image_path;
        $hasUnlinkedLegacy = ($legacyPath || $listing->image_url) && !$listing->images()->where('path', $legacyPath)->exists();
        $existingImageCount += (int) $hasUnlinkedLegacy;
        $removeCount = $listing->images()->whereIn('id', $removeIds)->count();
        if ($request->boolean('remove_legacy_image') && $hasUnlinkedLegacy) $removeCount++;
        $incomingCount = count($request->file('images', [])) + (int) $request->hasFile('image');
        abort_if($existingImageCount - $removeCount + $incomingCount > 10, 422, 'A product listing can have at most 10 images. Remove an image before adding more.');

        $updates = [];

        if ($request->has('product_name')) {
            $updates['product_name'] = $request->input('product_name');
        }

        if ($request->has('category')) {
            $category = $request->input('category');
            $updates['product_category'] = $category;
            $updates['category'] = $category;
        }

        if ($request->has('quantity')) {
            $quantity = (float) $request->input('quantity');
            $updates['quantity_available'] = $quantity;
            $updates['quantity'] = $quantity;
        }

        if ($request->has('unit')) {
            $unit = $request->input('unit');
            $updates['unit_of_measure'] = $unit;
            $updates['unit'] = $unit;
        }

        if ($request->has('price_per_unit')) {
            $price = (float) $request->input('price_per_unit');
            $updates['current_price_per_unit'] = $price;
            $updates['price_per_unit'] = $price;
        }

        if ($request->has('description')) {
            $updates['description'] = $request->input('description');
        }

        if ($request->has('location')) {
            $updates['location'] = $request->input('location');
        }

        if ($request->has('status')) {
            $updates['status'] = $request->input('status');
        }

        if ($request->has('harvest_date')) {
            $updates['harvest_date'] = $request->input('harvest_date');
        }

        $listing->fill($updates);
        $listing->save();
        $this->saveListingImages($request, $listing);

        return response()->json([
            'message' => 'Product listing updated successfully',
            'listing' => $this->serializeListing(
                $listing,
                $this->orderCountsByListing(collect([$listing]))[$listing->listing_id] ?? 0,
                (int) ($listing->views ?? 0)
            ),
        ]);
    }

    public function deleteProductListing($listingId)
    {
        $user = Auth::user();
        $response = $this->requireVerifiedProducer($user);
        if ($response) {
            return $response;
        }

        $producer = Producer::where('user_id', $user->user_id)->first();
        $listing = ProductListing::where('listing_id', $listingId)
            ->where('producer_id', $producer->producer_id)
            ->firstOrFail();

        if ($listing->orderDetails()->exists()) {
            return response()->json([
                'message' => 'This listing is linked to existing orders and cannot be deleted. Transaction history must be preserved.',
            ], 409);
        }

        $imagePaths = $listing->images()->pluck('path')->all();
        $listing->delete();

        foreach ($imagePaths as $imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return response()->json(['message' => 'Product listing deleted successfully']);
    }

    /**
     * An eligible listing is sellable right now: an active or available
     * status with stock still on hand. Legacy duplicate columns are both read
     * so older rows are not silently dropped.
     */
    private function isEligibleListing(ProductListing $listing): bool
    {
        $status = strtolower(trim((string) ($listing->status ?? '')));

        if (!in_array($status, ['active', 'available'], true)) {
            return false;
        }

        return $this->listingQuantity($listing) > 0;
    }

    private function listingQuantity(ProductListing $listing): float
    {
        return (float) ($listing->quantity_available ?? $listing->quantity ?? 0);
    }

    private function listingPrice(ProductListing $listing): float
    {
        $price = $listing->current_price_per_unit ?? $listing->price_per_unit;

        return $price === null ? 0.0 : (float) $price;
    }

    private function listingCategory(ProductListing $listing): string
    {
        $category = $listing->product_category ?? $listing->category;

        if (!is_string($category)) {
            return 'Uncategorized';
        }

        $category = trim($category);

        return $category === '' ? 'Uncategorized' : $category;
    }

    /**
     * Buckets this producer's realized revenue into the last six calendar
     * months, oldest first. Months without revenue are kept as zero so the
     * chart always represents six consecutive calendar months.
     */
    private function revenueByMonth($revenueDetails): array
    {
        $monthly = [];

        foreach ($revenueDetails as $detail) {
            $month = $this->monthKey($detail->order_date);

            if ($month === null) {
                continue;
            }

            $monthly[$month] = ($monthly[$month] ?? 0.0) + (float) $detail->subtotal;
        }

        $months = [];
        $cursor = now()->startOfMonth()->subMonths(self::REVENUE_TREND_MONTHS - 1);

        for ($index = 0; $index < self::REVENUE_TREND_MONTHS; $index++) {
            $month = $cursor->format('Y-m');

            $months[] = [
                'month' => $month,
                'label' => $cursor->format('M'),
                'label_long' => $cursor->format('M Y'),
                'revenue' => round($monthly[$month] ?? 0.0, 2),
            ];

            $cursor = $cursor->copy()->addMonthNoOverflow();
        }

        return $months;
    }

    /**
     * Normalizes an order date into a Y-m bucket key, or null when the value
     * cannot be read as a date.
     */
    private function monthKey($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m');
        }

        $month = substr((string) $value, 0, 7);

        return preg_match('/^\d{4}-\d{2}$/', $month) === 1 ? $month : null;
    }

    /**
     * Builds a month over month comparison.
     *
     * A zero baseline has no meaningful percentage, so it is reported as an
     * unavailable comparison rather than rendering Infinity or NaN.
     */
    private function monthOverMonthChange(float $current, float $previous): array
    {
        $difference = $current - $previous;

        $direction = match (true) {
            $difference > 0 => 'up',
            $difference < 0 => 'down',
            default => 'flat',
        };

        $percent = $previous > 0
            ? round(($difference / $previous) * 100, 1)
            : null;

        return [
            'available' => $percent !== null,
            'current' => $current,
            'previous' => $previous,
            'percent' => $percent,
            'direction' => $direction,
        ];
    }

    /**
     * product_listings records no status and no price history, so a previous
     * month value cannot be rebuilt for listing based metrics. This reports
     * the current value and marks the comparison unavailable instead of
     * inventing a historical figure.
     */
    private function unavailableChange(float $current): array
    {
        return [
            'available' => false,
            'current' => $current,
            'previous' => null,
            'percent' => null,
            'direction' => null,
        ];
    }

    /**
     * Distinct order count per listing.
     *
     * Cancelled and rejected orders are excluded because the fulfillment
     * workflow returns their stock and they never became a sale. One grouped
     * query serves the whole collection, so the dashboard never fans out a
     * query per listing.
     */
    private function orderCountsByListing($listings): array
    {
        $listingIds = $listings->pluck('listing_id')->filter()->unique()->values();

        if ($listingIds->isEmpty()) {
            return [];
        }

        return OrderDetail::query()
            ->join('orders', 'orders.order_id', '=', 'order_details.order_id')
            ->whereIn('order_details.listing_id', $listingIds->all())
            ->where(function ($query) {
                $query
                    ->whereNotIn(
                        'orders.fulfillment_status',
                        self::UNFULFILLED_FULFILLMENT_STATUSES
                    )
                    ->orWhereNull('orders.fulfillment_status');
            })
            ->selectRaw(
                'order_details.listing_id, count(distinct order_details.order_id) as orders_count'
            )
            ->groupBy('order_details.listing_id')
            ->pluck('orders_count', 'listing_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    protected function serializeListing(
        ProductListing $listing,
        ?int $ordersCount = null,
        ?int $viewsCount = null,
        ?array $availabilityTotals = null
    ): array
    {
        $listing->loadMissing('images');
        $category = $listing->product_category ?? $listing->category ?? 'General';
        $unit = $listing->unit_of_measure ?? $listing->unit ?? 'unit';
        $price = $listing->current_price_per_unit ?? $listing->price_per_unit ?? 0;
        $quantity = $listing->quantity_available ?? $listing->quantity ?? 0;

        $images = $listing->images->map(fn ($image) => [
            'id' => $image->id,
            'path' => $image->path,
            'url' => Storage::disk('public')->url($image->path),
            'is_primary' => $image->is_primary,
        ])->values();
        $legacyPath = ltrim(str_replace('public/', '', (string) $listing->image_path), '/');
        if ($legacyPath && Storage::disk('public')->exists($legacyPath) && !$images->contains('path', $listing->image_path)) {
            $images->prepend(['id' => null, 'path' => $legacyPath, 'url' => Storage::disk('public')->url($legacyPath), 'is_primary' => true]);
        }

        $payload = [
            'listing_id' => $listing->listing_id,
            'id' => $listing->listing_id,
            'producer_id' => $listing->producer_id,
            'product_name' => $listing->product_name,
            'product_category' => $category,
            'category' => $category,
            'current_price_per_unit' => (float) $price,
            'price_per_unit' => (float) $price,
            'price' => (float) $price,
            'unit_of_measure' => $unit,
            'unit' => $unit,
            'quantity_available' => (float) $quantity,
            'quantity' => (float) $quantity,
            'status' => $listing->status ?? 'Active',
            'image_path' => $listing->image_path,
            'image_url' => $legacyPath && Storage::disk('public')->exists($legacyPath)
                ? Storage::disk('public')->url($legacyPath)
                : null,
            'images' => $images,
            'description' => $listing->description,
            'location' => $listing->location,
            'harvest_date' => $listing->harvest_date,
            'created_at' => $listing->created_at,
            'updated_at' => $listing->updated_at,
        ];

        // Derived transaction state. Availability/reserved/sold come from real orders;
        // "Delivered" never implies "Paid".
        $payload = array_merge($payload, $this->listingAvailability($listing, $availabilityTotals ?? $this->availabilityTotalsFor((int) $listing->producer_id)));

        /*
        | Product metrics are only present when the caller asked for them, which
        | keeps buyer facing endpoints free of producer only numbers. The order
        | count and the view count are always derived on the server and are
        | never read from a client payload.
        */
        if ($ordersCount !== null) {
            $payload['orders'] = $ordersCount;
        }

        if ($viewsCount !== null) {
            $payload['views'] = $viewsCount;
        }

        return $payload;
    }

    private function saveListingImages(Request $request, ProductListing $listing): void
    {
        foreach ($request->input('remove_image_ids', []) as $imageId) {
            $image = $listing->images()->whereKey($imageId)->first();
            if (!$image) continue;
            Storage::disk('public')->delete($image->path);
            if ($listing->image_path === $image->path) {
                $listing->image_path = null;
                $listing->image_url = null;
                $listing->save();
            }
            $image->delete();
        }

        if ($request->boolean('remove_legacy_image') && ($listing->image_path || $listing->image_url)) {
            if ($listing->image_path && !$listing->images()->where('path', $listing->image_path)->exists()) {
                Storage::disk('public')->delete($listing->image_path);
            }
            $listing->image_path = null;
            $listing->image_url = null;
            $listing->save();
        }

        $files = $request->file('images', []);
        if ($request->hasFile('image')) $files[] = $request->file('image');
        foreach ($files as $file) {
            $path = $file->store('product_images', 'public');
            $primary = $listing->images()->count() === 0 && !$listing->image_path && !$listing->image_url;
            $image = $listing->images()->create(['path' => $path, 'is_primary' => $primary]);
            if ($primary) {
                $listing->image_path = $path;
                $listing->image_url = Storage::disk('public')->url($path);
                $listing->save();
            }
        }

        if (!$listing->image_path && !$listing->image_url && !$listing->images()->where('is_primary', true)->exists() && $listing->images()->exists()) {
            $primary = $listing->images()->first();
            $primary->update(['is_primary' => true]);
            $listing->image_path = $primary->path;
            $listing->image_url = Storage::disk('public')->url($primary->path);
            $listing->save();
        }
    }
}
