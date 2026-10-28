<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\Negotiation;
use App\Models\NegotiationEvent;
use App\Models\NegotiationVersion;
use App\Models\DemandRequest;
use App\Models\DemandDetail;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\TransactionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NegotiationController extends Controller
{
private const CLOSED_STATUSES = ['Rejected', 'Cancelled', 'Expired'];

private function side(Request $request): array
{
    $user = $request->user();
    $buyer = Buyer::where('user_id', $user->user_id)->first();
    $producer = Producer::where('user_id', $user->user_id)->first();
    abort_unless($buyer || $producer, 403, 'A buyer or producer account is required.');
    return [$user, $buyer, $producer];
}

public function index(Request $request)
{
    [$user, $buyer, $producer] = $this->side($request);
    $side = $buyer ? 'buyer' : 'producer';
    $query = Negotiation::with(['listing.images','versions','events','order'])->orderByDesc('updated_at');
    if ($buyer) $query->where('buyer_id', $buyer->buyer_id);
    else $query->where('producer_id', $producer->producer_id);
    return response()->json(['data' => $query->get()->map(fn ($n) => $this->present($n, $side))]);
}

public function show(Request $request, int $id)
{
    [, $buyer] = $this->side($request);
    $n = $this->authorizedNegotiation($request, $id);
    return response()->json(['data' => $this->present($n, $buyer ? 'buyer' : 'producer')]);
}

public function store(Request $request)
{
    [$user, $buyer] = $this->side($request);
    abort_unless($buyer, 403, 'Only institutional buyers can submit proposals.');
    $data = $request->validate([
        'listing_id' => 'required|integer|exists:product_listings,listing_id',
        'unit_price' => 'required|numeric|min:0.01', 'quantity' => 'required|numeric|min:0.001',
        'delivery_date' => 'nullable|date|after_or_equal:today', 'delivery_arrangement' => 'nullable|string|max:2000',
        'additional_charges' => 'nullable|numeric|min:0', 'notes' => 'nullable|string|max:4000',
        'demand_request_id' => 'nullable|integer|exists:demand_requests,request_id',
    ]);
    $listing = ProductListing::with('producer')->findOrFail($data['listing_id']);
    abort_unless(in_array(strtolower($listing->status ?? ''), ['active','available']) && (float)($listing->quantity_available ?? $listing->quantity ?? 0) > 0, 422, 'This product is not currently available.');
    abort_if((float)$data['quantity'] > (float)($listing->quantity_available ?? $listing->quantity), 422, 'The requested quantity exceeds current availability.');
    $producerUser = $listing->producer?->user_id;
    abort_unless($producerUser, 422, 'The listing producer profile is unavailable.');
    if (!empty($data['demand_request_id'])) abort_unless(DemandRequest::published()->where('request_id', $data['demand_request_id'])->where('buyer_id', $buyer->buyer_id)->exists(), 422, 'The selected demand request does not belong to your buyer account.');
    $n = DB::transaction(function () use ($request, $user, $buyer, $listing, $data, $producerUser) {
        // Serialize submissions for one listing before checking active negotiations.
        ProductListing::whereKey($listing->listing_id)->lockForUpdate()->firstOrFail();
        $existing = Negotiation::with(['listing.images','versions','events'])
            ->where('buyer_id', $buyer->buyer_id)->where('listing_id', $listing->listing_id)
            ->whereNotIn('status', ['Agreement Confirmed','Rejected','Cancelled','Expired'])
            ->latest('updated_at')->first();
        if ($existing) return $existing;
        $n = Negotiation::create(['reference' => 'HBR-' . strtoupper(Str::random(8)), 'buyer_id' => $buyer->buyer_id, 'producer_id' => $listing->producer_id, 'listing_id' => $listing->listing_id, 'demand_request_id' => $data['demand_request_id'] ?? null, 'status' => 'Awaiting Producer Review', 'current_version' => 1]);
        $v = $n->versions()->create(['version' => 1, 'proposed_by_user_id' => $user->user_id, 'proposed_by_role' => 'buyer', 'listed_price' => $listing->current_price_per_unit ?? $listing->price_per_unit ?? 0, 'unit_price' => $data['unit_price'], 'quantity' => $data['quantity'], 'unit' => $listing->unit_of_measure ?? $listing->unit ?? 'unit', 'delivery_date' => $data['delivery_date'] ?? null, 'delivery_arrangement' => $data['delivery_arrangement'] ?? null, 'additional_charges' => $data['additional_charges'] ?? 0, 'notes' => $data['notes'] ?? null, 'buyer_confirmed_at' => now()]);
        $this->event($n, $v, $user->user_id, 'proposal_submitted', $n->status);
        $this->notify($producerUser, $n, null, 'New negotiated terms', 'A buyer submitted terms for ' . $listing->product_name . '.');
        return $n;
    });
    $created = $n->wasRecentlyCreated;
    return response()->json(['message' => $created ? 'Proposal submitted. Waiting for the producer to review your terms.' : 'An active negotiation already exists for this product.', 'data' => $this->present($n->load(['listing.images','versions','events','order']), 'buyer')], $created ? 201 : 200);
}

public function startOffer(Request $request, int $offerId)
{
    [$user, $buyer] = $this->side($request);
    abort_unless($buyer, 403, 'Only institutional buyers can negotiate supply offers.');
    $offer = DemandDetail::with(['demandRequest', 'listing.producer'])->findOrFail($offerId);
    abort_unless((int) $offer->demandRequest?->buyer_id === (int) $buyer->buyer_id, 404);
    abort_unless(in_array($offer->status, ['Submitted', 'Under Review'], true), 409, 'This offer is no longer available for negotiation.');
    $listing = $offer->listing;
    abort_unless($listing && (int) $listing->producer_id === (int) $offer->producer_id, 409, 'The producer listing for this offer is unavailable.');
    $n = DB::transaction(function () use ($user, $buyer, $offer, $listing) {
        $reviewedDemand = DemandRequest::whereKey($offer->demand_request_id)->lockForUpdate()->firstOrFail();
        abort_unless($reviewedDemand->currentPublicationReview()?->decision === 'Approved', 409, 'Demand publication review is required.');
        $existing = Negotiation::where('demand_detail_id', $offer->id)->whereNotIn('status', ['Rejected', 'Cancelled', 'Expired'])->first();
        if ($existing) return $existing->load(['listing.images', 'versions', 'events']);
        $n = Negotiation::create([
            'reference' => 'HBR-' . strtoupper(Str::random(8)), 'buyer_id' => $buyer->buyer_id,
            'producer_id' => $offer->producer_id, 'listing_id' => $listing->listing_id,
            'demand_request_id' => $offer->demand_request_id, 'demand_detail_id' => $offer->id,
            'status' => 'Awaiting Producer Confirmation', 'current_version' => 1,
        ]);
        $v = $n->versions()->create([
            'version' => 1, 'proposed_by_user_id' => $user->user_id, 'proposed_by_role' => 'buyer',
            'listed_price' => $listing->current_price_per_unit ?? $listing->price_per_unit ?? 0,
            'unit_price' => $offer->proposed_price, 'quantity' => $offer->quantity_offered ?? $offer->proposed_quantity,
            'unit' => $offer->demandRequest->unit ?? $listing->unit_of_measure ?? 'unit',
            'delivery_date' => $offer->availability_date ?? $offer->demandRequest->preferred_delivery_date,
            'delivery_arrangement' => $offer->delivery_terms ?? $offer->demandRequest->location,
            'notes' => trim(implode("\n", array_filter([$offer->quality_grade, $offer->notes, $offer->demandRequest->special_requirements]))),
            'buyer_confirmed_at' => now(),
        ]);
        $this->event($n, $v, $user->user_id, 'offer_negotiation_started', $n->status);
        $offer->status = 'Negotiating';
        $offer->save();
        $producerUserId = Producer::find($offer->producer_id)?->user_id;
        $this->notify($producerUserId, $n, null, 'Demand offer negotiation started', 'Review buyer terms for ' . $listing->product_name . ' (' . $n->reference . ').');
        return $n->load(['listing.images', 'versions', 'events']);
    });
    return response()->json(['message' => 'Negotiation started from this supply offer.', 'data' => $this->present($n, 'buyer')]);
}

public function respond(Request $request, int $id)
{
    [$user, $buyer, $producer] = $this->side($request);
    // An account can hold both a buyer and a producer profile. The acting side
    // must be decided the same way authorization, index and show decide it, so
    // a dual-role buyer is never mistaken for the producer and told that its
    // own confirmation already exists.
    $isProducer = ! $buyer && $producer;
    $n = $this->authorizedNegotiation($request, $id, true);
    $data = $request->validate([
        'action' => 'required|in:accept,confirm,revise,reject,cancel', 'version' => 'required|integer|min:1', 'unit_price' => 'required_if:action,revise|nullable|numeric|min:0.01',
        'quantity' => 'required_if:action,revise|nullable|numeric|min:0.001', 'delivery_date' => 'nullable|date|after_or_equal:today',
        'delivery_arrangement' => 'nullable|string|max:2000', 'additional_charges' => 'nullable|numeric|min:0', 'notes' => 'nullable|string|max:4000', 'remarks' => 'nullable|string|max:2000',
    ]);
    $recipient = $isProducer ? Buyer::find($n->buyer_id)?->user_id : Producer::find($n->producer_id)?->user_id;
    $result = DB::transaction(function () use ($n, $data, $user, $buyer, $producer, $recipient, $isProducer) {
        $n = Negotiation::whereKey($n->id)->lockForUpdate()->firstOrFail();
        abort_unless((int)$data['version'] === (int)$n->current_version, 409, 'This proposal version is no longer active. Reload the negotiation and review the latest terms.');
        $v = $n->versions()->where('version', $n->current_version)->lockForUpdate()->firstOrFail();
        $action = $data['action'];
        abort_unless(!in_array($n->status, ['Rejected','Cancelled','Expired']), 409, 'This negotiation is already closed.');
        $myConfirmation = $isProducer ? $v->producer_confirmed_at : $v->buyer_confirmed_at;
        if (in_array($action, ['accept','confirm']) && $myConfirmation) {
            // Repeated submissions for this exact version are safe and return current state.
            // Stale versions are rejected above, before reaching this idempotent path.
            // The same path repairs a status that never caught up with the saved
            // confirmation and backfills an order that a failed run left missing.
            $this->reconcileStatus($n, $v);
            $order = $n->status === 'Agreement Confirmed' ? Order::where('negotiation_id', $n->id)->first() : null;
            if ($n->status === 'Agreement Confirmed' && !$order) $order = $this->createOrderForConfirmedNegotiation($n, $buyer);
            return ['negotiation' => $n->fresh(['listing.images','versions','events']), 'order' => $order, 'idempotent' => true];
        }
        abort_unless($n->status !== 'Agreement Confirmed', 409, 'This negotiation is already closed.');
        if ($action === 'revise') {
            $listing = $n->listing;
            abort_unless($listing && in_array(strtolower($listing->status ?? ''), ['active','available']), 422, 'This product is no longer available for negotiation.');
            abort_if((float)$data['quantity'] > (float)($listing->quantity_available ?? $listing->quantity ?? 0), 422, 'The revised quantity exceeds current product availability.');
        }
        if ($action === 'revise') {
            $version = (int)$n->current_version + 1;
            $listing = $n->listing;
            $v = $n->versions()->create(['version' => $version, 'proposed_by_user_id' => $user->user_id, 'proposed_by_role' => $isProducer ? 'producer' : 'buyer', 'listed_price' => $v->listed_price, 'unit_price' => $data['unit_price'], 'quantity' => $data['quantity'], 'unit' => $listing->unit_of_measure ?? $listing->unit ?? 'unit', 'delivery_date' => $data['delivery_date'] ?? $v->delivery_date, 'delivery_arrangement' => $data['delivery_arrangement'] ?? $v->delivery_arrangement, 'additional_charges' => $data['additional_charges'] ?? $v->additional_charges, 'notes' => $data['notes'] ?? $v->notes, 'remarks' => $data['remarks'] ?? null, $isProducer ? 'producer_confirmed_at' : 'buyer_confirmed_at' => now()]);
            $n->current_version = $version;
            $n->status = $isProducer ? 'Revised Terms Submitted' : 'Revised Proposal';
        } elseif ($action === 'reject') { $n->status = 'Rejected'; }
        elseif ($action === 'cancel') { $n->status = 'Cancelled'; }
        else {
            if ($isProducer) $v->producer_confirmed_at = now(); else $v->buyer_confirmed_at = now();
            $v->save();
            if ($v->buyer_confirmed_at && $v->producer_confirmed_at) {
                $n->status = 'Agreement Confirmed';
                $n->agreement_snapshot = $this->agreementSnapshot($n, $v);
            } else $n->status = $this->statusForVersion($v);
        }
        if ($data['action'] === 'reject') $this->event($n, $v, $user->user_id, 'proposal_rejected', $n->status, $data['remarks'] ?? null);
        elseif ($data['action'] === 'cancel') $this->event($n, $v, $user->user_id, 'negotiation_cancelled', $n->status, $data['remarks'] ?? null);
        elseif ($data['action'] === 'revise') $this->event($n, $v, $user->user_id, 'revised_terms_submitted', $n->status, $data['remarks'] ?? null);
        else $this->event($n, $v, $user->user_id, $n->status === 'Agreement Confirmed' ? 'agreement_confirmed' : 'terms_confirmed', $n->status);
        $n->save();
        if ($n->supplyOffer && $n->status === 'Agreement Confirmed') {
            $n->supplyOffer->status = 'Agreement Confirmed';
            $n->supplyOffer->save();
            if ($n->demandRequest) $this->updateDemandWorkflowStatus($n->demandRequest, 'Agreement Confirmed');
        } elseif ($n->supplyOffer && in_array($n->status, ['Rejected', 'Cancelled'], true)) {
            $n->supplyOffer->status = $n->status === 'Rejected' ? 'Declined' : 'Withdrawn';
            $n->supplyOffer->save();
        }
        if ($n->status === 'Agreement Confirmed') {
            $message = 'Both parties confirmed version ' . $v->version . ' of ' . $n->reference . '. An official order has been submitted for producer acceptance.';
            $this->notify(Buyer::find($n->buyer_id)?->user_id, $n, null, 'Agreement confirmed', $message);
            $this->notify(Producer::find($n->producer_id)?->user_id, $n, null, 'Agreement confirmed', $message);
            $order = $this->createOrderForConfirmedNegotiation($n, $buyer);
        } else $this->notify($recipient, $n, null, 'Negotiation updated', $n->reference . ' is now ' . $n->status . '.');
        return ['negotiation' => $n, 'order' => $order ?? null, 'idempotent' => false];
    });
    $negotiation = $result['negotiation']->load(['listing.images','versions','events','order']);
    return response()->json([
        'message' => $result['idempotent'] ? 'This proposal version was already confirmed. Current status has been loaded.' : 'Negotiation updated.',
        'data' => $this->present($negotiation, $isProducer ? 'producer' : 'buyer'),
        'order' => $result['order']?->load(['details.listing','negotiation']),
    ]);
}

public function createOrder(Request $request, int $id)
{
    [$user, $buyer] = $this->side($request);
    abort_unless($buyer, 403);
    $n = $this->authorizedNegotiation($request, $id, true);
    abort_unless($n->status === 'Agreement Confirmed' && $n->agreement_snapshot, 409, 'Both parties must confirm the agreement before ordering.');
    $data = $request->validate(['shipping_address' => 'nullable|string|max:500']);
    $order = DB::transaction(function () use ($n, $buyer, $data) {
        $locked = Negotiation::whereKey($n->id)->lockForUpdate()->firstOrFail();
        abort_unless($locked->status === 'Agreement Confirmed', 409, 'Agreement is no longer available.');
        $existing = Order::where('negotiation_id', $locked->id)->first();
        return $existing ?: $this->createOrderForConfirmedNegotiation($locked, $buyer, $data['shipping_address'] ?? null);
    });
    return response()->json(['message' => 'Official order submitted.', 'order' => $order->load(['details.listing','negotiation'])], 201);
}

private function createOrderForConfirmedNegotiation(Negotiation $n, ?Buyer $buyer, ?string $shippingAddress = null): Order
{
    $existing = Order::where('negotiation_id', $n->id)->first();
    if ($existing) return $existing;
    $terms = $n->agreement_snapshot;
    abort_unless(is_array($terms) && isset($terms['quantity'], $terms['unit_price']), 409, 'The agreed terms for this negotiation are not available yet. Ask the other party to confirm the current proposal version.');
    $listing = ProductListing::where('listing_id', $n->listing_id)->lockForUpdate()->firstOrFail();
    $available = (float)($listing->quantity_available ?? $listing->quantity ?? 0);
    $expectedSupplyDate = $n->supplyOffer?->availability_date;
    $futureSupply = $expectedSupplyDate && $expectedSupplyDate->isFuture();
    abort_if($available < (float)$terms['quantity'] && !$futureSupply, 422, 'The product no longer has enough available quantity.');
    $buyer ??= Buyer::findOrFail($n->buyer_id);
    $order = Order::create(['buyer_id' => $buyer->buyer_id, 'negotiation_id' => $n->id, 'order_date' => now()->toDateString(), 'delivery_date' => $terms['delivery_date'], 'total_amount' => $terms['total'], 'shipping_address' => $terms['delivery_arrangement'] ?: $shippingAddress, 'fulfillment_status' => 'Order Submitted', 'payment_status' => 'Pending']);
    OrderDetail::create(['order_id' => $order->order_id, 'listing_id' => $listing->listing_id, 'quantity' => $terms['quantity'], 'unit_price' => $terms['unit_price'], 'subtotal' => $terms['subtotal']]);
    // Reserve on confirmed order creation, never when the producer merely submits an offer.
    // Future harvest offers are allowed to reach Orders without subtracting nonexistent stock.
    if (!$futureSupply) {
        if ($listing->quantity_available !== null) $listing->quantity_available = max(0, $available - (float)$terms['quantity']);
        if ($listing->quantity !== null) $listing->quantity = max(0, (float)$listing->quantity - (float)$terms['quantity']);
        $listing->save();
    }
    $this->event($n, null, request()->user()->user_id, 'official_order_submitted', $n->status);
    $this->notify(Producer::find($n->producer_id)?->user_id, $n, $order->order_id, 'Official order submitted', 'Order #' . $order->order_id . ' was created from ' . $n->reference . ' and is waiting for your acceptance.');
    $this->notify(Buyer::find($n->buyer_id)?->user_id, $n, $order->order_id, 'Official order submitted', 'Order #' . $order->order_id . ' is now in Orders and is waiting for producer acceptance.');
    if ($n->supplyOffer) { $n->supplyOffer->status = 'Order Created'; $n->supplyOffer->save(); }
    if ($n->demandRequest) $this->updateDemandWorkflowStatus($n->demandRequest, 'Order Created');
    return $order;
}

public function producerOrders(Request $request)
{
    [, , $producer] = $this->side($request);
    abort_unless($producer, 403);

    $producerId = (int) $producer->producer_id;

    /*
    | One order can hold line items from more than one producer. The details
    | eager load is constrained to this producer's own lines so another
    | producer's product, quantity and price are never loaded in the first
    | place, rather than being fetched and then filtered away afterwards.
    */
    $orders = Order::with([
        'buyer.user',
        'negotiation',
        'details' => function ($query) use ($producerId) {
            $query->with('listing')
                ->whereHas('listing', function ($listing) use ($producerId) {
                    $listing->where('producer_id', $producerId);
                });
        },
    ])
        ->whereHas('details.listing', fn ($q) => $q->where('producer_id', $producerId))
        ->orderByDesc('order_date')
        ->get();

    return response()->json($orders->map(function (Order $order) use ($producerId) {
        // Re-checked on the loaded rows so the scoping also holds if the
        // eager load constraint is ever relaxed.
        $ownDetails = $order->details
            ->filter(fn (OrderDetail $detail) => (int) $detail->listing?->producer_id === $producerId)
            ->values();

        $buyer = $order->buyer;
        $buyerUser = $buyer?->user;

        return [
            'order_id' => $order->order_id,
            'order_date' => $order->order_date,
            'delivery_date' => $order->delivery_date,
            'fulfillment_status' => $order->fulfillment_status,
            'payment_status' => $order->payment_status,
            /*
            | Only the subtotal built from this producer's own lines. The order
            | total is deliberately not returned because it may include other
            | producers' products.
            */
            'producer_subtotal' => (float) $ownDetails->sum('subtotal'),
            'buyer' => [
                'buyer_id' => $buyer?->buyer_id,
                'name' => trim(
                    ($buyerUser?->first_name ?? '') . ' ' .
                    ($buyerUser?->last_name ?? '')
                ) ?: 'Buyer',
                'organization_name' => $buyer?->organization_name,
            ],
            'details' => $ownDetails->map(fn (OrderDetail $detail) => [
                'order_detail_id' => $detail->order_detail_id,
                'listing_id' => $detail->listing_id,
                'product_name' => $detail->listing?->product_name ?? 'Product',
                'quantity' => (float) $detail->quantity,
                'unit' => $detail->listing?->unit_of_measure ?? $detail->listing?->unit,
                'unit_price' => (float) $detail->unit_price,
                'subtotal' => (float) $detail->subtotal,
            ])->values(),
            'negotiation' => $order->negotiation ? [
                'reference' => $order->negotiation->reference,
                'status' => $order->negotiation->status,
                'agreement_snapshot' => $order->negotiation->agreement_snapshot,
            ] : null,
        ];
    })->values());
}

public function updateOrder(Request $request, int $id)
{
    [, $buyer, $producer] = $this->side($request);
    $data = $request->validate(['status' => 'required|in:Order Confirmed,Preparing Order,Ready for Shipment,Shipped,Delivered,Completed,Cancelled,Rejected']);

    $order = DB::transaction(function () use ($request, $id, $buyer, $producer, $data) {
        $order = Order::with(['details.listing', 'negotiation.supplyOffer', 'negotiation.demandRequest'])
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
        $isProducerOwner = ! $buyer && $producer && $order->details->contains(
            fn ($detail) => (int) $detail->listing?->producer_id === (int) $producer->producer_id
        );
        abort_unless(
            ($buyer && (int) $order->buyer_id === (int) $buyer->buyer_id) || $isProducerOwner,
            403
        );

        $allowed = $isProducerOwner
            ? [
                'Order Submitted' => ['Order Confirmed', 'Rejected', 'Cancelled'],
                'Order Confirmed' => ['Preparing Order', 'Cancelled'],
                'Preparing Order' => ['Ready for Shipment'],
                'Ready for Shipment' => ['Shipped'],
                'Shipped' => ['Delivered'],
            ]
            : [
                'Delivered' => ['Completed'],
                'Order Submitted' => ['Cancelled'],
            ];
        abort_unless(
            in_array($data['status'], $allowed[$order->fulfillment_status] ?? [], true),
            409,
            'That order status change is not allowed.'
        );

        $isReleasingInventory = in_array($data['status'], ['Cancelled', 'Rejected'], true);
        $availabilityDate = $order->negotiation?->supplyOffer?->availability_date;
        $orderDate = $order->order_date;
        // Future supply orders never reserve current listing stock. Compare to
        // the order date (not today) so a later cancellation cannot add stock
        // that was never subtracted, even after the promised date has passed.
        $inventoryWasReserved = ! $availabilityDate || ! $orderDate || ! $availabilityDate->gt($orderDate);

        if ($isReleasingInventory && $inventoryWasReserved) {
            $listingIds = $order->details->pluck('listing_id')->unique()->sort()->values();
            $lockedListings = ProductListing::whereIn('listing_id', $listingIds)
                ->orderBy('listing_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('listing_id');

            foreach ($order->details as $detail) {
                $listing = $lockedListings->get($detail->listing_id);
                if (! $listing) continue;

                if ($listing->quantity_available !== null) {
                    $listing->quantity_available = (float) $listing->quantity_available + (float) $detail->quantity;
                }
                if ($listing->quantity !== null) {
                    $listing->quantity = (float) $listing->quantity + (float) $detail->quantity;
                }
                $listing->save();
            }
        }

        $order->fulfillment_status = $data['status'];
        $order->save();

        $negotiation = $order->negotiation;
        $demand = null;
        if ($negotiation?->demand_request_id) {
            $demand = DemandRequest::whereKey($negotiation->demand_request_id)
                ->lockForUpdate()
                ->first();
        }

        if ($negotiation?->demand_detail_id && in_array($data['status'], ['Completed', 'Cancelled', 'Rejected'], true)) {
            $offer = DemandDetail::whereKey($negotiation->demand_detail_id)
                ->lockForUpdate()
                ->first();
            if ($offer) {
                $offer->status = match ($data['status']) {
                    'Completed' => 'Completed',
                    'Rejected' => 'Declined',
                    default => 'Withdrawn',
                };
                $offer->save();
            }
        }

        if ($demand && $data['status'] === 'Completed') {
            $this->updateDemandWorkflowStatus($demand, 'Completed');
        } elseif ($demand && $isReleasingInventory && ! in_array($demand->status, ['Cancelled', 'Completed', 'Expired'], true)) {
            $this->updateDemandWorkflowStatus($demand, 'Offers Received');
        }

        if ($negotiation) {
            $this->event($negotiation, null, $request->user()->user_id, 'order_status_changed', $negotiation->status, $data['status']);
        }

        $producerUserId = Producer::find($order->details->first()?->listing?->producer_id)?->user_id;
        $buyerUserId = Buyer::find($order->buyer_id)?->user_id;
        if ($data['status'] === 'Completed') {
            $this->notify($producerUserId, $negotiation, $order->order_id, 'Receipt confirmed', 'The buyer confirmed receipt for order #' . $order->order_id . '.');
        } elseif ($isProducerOwner) {
            $this->notify($buyerUserId, $negotiation, $order->order_id, 'Order updated', 'Order #' . $order->order_id . ' is now ' . $data['status'] . '.');
        } else {
            $this->notify($producerUserId, $negotiation, $order->order_id, 'Order updated', 'Order #' . $order->order_id . ' is now ' . $data['status'] . '.');
        }

        return $order->fresh(['details.listing', 'negotiation']);
    });

    return response()->json(['message' => 'Order updated.', 'order' => $order]);
}

private function authorizedNegotiation(Request $request, int $id, bool $lock = false): Negotiation
{
    [, $buyer, $producer] = $this->side($request);
    $q = Negotiation::with(['listing.images','versions','events','order'])->whereKey($id);
    if ($buyer) $q->where('buyer_id', $buyer->buyer_id); else $q->where('producer_id', $producer->producer_id);
    return $q->firstOrFail();
}

private function event(Negotiation $n, ?NegotiationVersion $v, int $userId, string $event, string $status, ?string $remarks = null): void
{
    NegotiationEvent::create(['negotiation_id' => $n->id, 'version_id' => $v?->id, 'actor_user_id' => $userId, 'event' => $event, 'status' => $status, 'remarks' => $remarks, 'terms' => $v ? $v->only(['version','unit_price','quantity','unit','delivery_date','delivery_arrangement','additional_charges','notes']) : null]);
}
private function notify(?int $userId, ?Negotiation $n, ?int $orderId, string $title, string $body): void
{
    if ($userId) TransactionNotification::create(['recipient_user_id'=>$userId,'negotiation_id'=>$n?->id,'order_id'=>$orderId,'title'=>$title,'body'=>$body]);
}

private function updateDemandWorkflowStatus(DemandRequest $demand, string $finalStatus): void
{
    if ($demand->partial_fulfillment && !in_array($finalStatus, ['Cancelled', 'Completed'], true)) {
        $coveredQuantity = (float) $demand->demandDetails()
            ->whereIn('status', ['Agreement Confirmed', 'Order Created', 'Completed'])
            ->sum(DB::raw('COALESCE(quantity_offered, proposed_quantity)'));
        if ($coveredQuantity < (float) $demand->quantity_needed) $finalStatus = 'Offers Received';
    }
    $demand->status = $finalStatus;
    $demand->save();
}
/**
 * Status that the saved confirmation records actually justify for a version.
 */
private function statusForVersion(NegotiationVersion $v): string
{
    if ($v->buyer_confirmed_at && $v->producer_confirmed_at) return 'Agreement Confirmed';
    if ($v->buyer_confirmed_at) return 'Awaiting Producer Confirmation';
    if ($v->producer_confirmed_at) return 'Awaiting Buyer Confirmation';
    return 'Awaiting Producer Review';
}

/**
 * Immutable record of the terms both parties agreed on. It is rebuilt from the
 * saved version whenever a mutual approval has to be (re)confirmed, so the
 * order workflow always has the totals it needs.
 */
private function agreementSnapshot(Negotiation $n, NegotiationVersion $v): array
{
    return ['version' => $v->version, 'product' => $n->listing->product_name, 'unit_price' => (float)$v->unit_price, 'quantity' => (float)$v->quantity, 'unit' => $v->unit, 'subtotal' => round((float)$v->unit_price * (float)$v->quantity, 2), 'additional_charges' => (float)$v->additional_charges, 'total' => round((float)$v->unit_price * (float)$v->quantity + (float)$v->additional_charges, 2), 'delivery_date' => $v->delivery_date?->toDateString(), 'delivery_arrangement' => $v->delivery_arrangement, 'notes' => $v->notes, 'buyer_confirmed_at' => $v->buyer_confirmed_at, 'producer_confirmed_at' => $v->producer_confirmed_at];
}

/**
 * Brings a stale workflow status back in line with the confirmations that are
 * already stored, so the interface never keeps reporting "Revision Pending"
 * after the buyer saved a confirmation.
 */
private function reconcileStatus(Negotiation $n, NegotiationVersion $v): void
{
    if (in_array($n->status, self::CLOSED_STATUSES, true)) return;
    $expected = $this->statusForVersion($v);
    $missingSnapshot = $expected === 'Agreement Confirmed' && !$n->agreement_snapshot;
    if ($n->status === $expected && !$missingSnapshot) return;
    $n->status = $expected;
    if ($missingSnapshot) $n->agreement_snapshot = $this->agreementSnapshot($n, $v);
    $n->save();
    $this->event($n, $v, request()->user()->user_id, 'status_reconciled', $n->status);
}

private function present(Negotiation $n, ?string $side = null): array
{
    $listing = $n->listing; $producer = $listing?->producer; $buyer = Buyer::with('user')->find($n->buyer_id); $v = $n->versions->firstWhere('version', $n->current_version);
    $buyerConfirmed = (bool) $v?->buyer_confirmed_at;
    $producerConfirmed = (bool) $v?->producer_confirmed_at;
    $closed = in_array($n->status, self::CLOSED_STATUSES, true);
    // Confirmation timestamps are historical records. Once the negotiation is
    // rejected, cancelled, or expired, they no longer mean that the party has
    // an effective confirmation pending in the current workflow.
    if ($closed) {
        $buyerConfirmed = false;
        $producerConfirmed = false;
    }
    $mutuallyApproved = $n->status === 'Agreement Confirmed' && $buyerConfirmed && $producerConfirmed;
    $awaiting = $closed || $mutuallyApproved || $n->status === 'Agreement Confirmed'
        ? null
        : ($buyerConfirmed ? 'producer' : 'buyer');
    $statusLabel = match (true) {
        $closed => $n->status,
        $mutuallyApproved => 'Approved',
        $awaiting === 'producer' => 'Buyer Confirmed',
        default => 'Awaiting Buyer Confirmation',
    };
    $confirmation = [
        'version' => $v?->version,
        'buyer_confirmed_at' => $closed ? null : $v?->buyer_confirmed_at?->toISOString(),
        'producer_confirmed_at' => $closed ? null : $v?->producer_confirmed_at?->toISOString(),
        'buyer_confirmed' => $buyerConfirmed,
        'producer_confirmed' => $producerConfirmed,
        'mutually_approved' => $mutuallyApproved,
        'awaiting' => $awaiting,
        'status_label' => $statusLabel,
        'you_confirmed' => $side === 'buyer' ? $buyerConfirmed : ($side === 'producer' ? $producerConfirmed : false),
        'other_party_confirmed' => $side === 'buyer' ? $producerConfirmed : ($side === 'producer' ? $buyerConfirmed : false),
        'your_action_required' => !$closed && $mutuallyApproved === false && ($side === 'buyer' ? !$buyerConfirmed : ($side === 'producer' ? !$producerConfirmed : false)),
    ];
    $order = $n->order;
    $rejectionEvent = $n->events->last(fn ($event) => $event->event === 'proposal_rejected');
    $rejectedBy = null;
    if ($rejectionEvent) {
        if ((int) $rejectionEvent->actor_user_id === (int) $buyer?->user_id) {
            $rejectedBy = 'buyer';
        } elseif ((int) $rejectionEvent->actor_user_id === (int) $producer?->user_id) {
            $rejectedBy = 'producer';
        }
    }
    return ['id'=>$n->id,'reference'=>$n->reference,'status'=>$n->status,'status_label'=>$statusLabel,'awaiting'=>$awaiting,'confirmation'=>$confirmation,'rejected_by'=>$rejectedBy,'rejected_version_id'=>$rejectionEvent?->version_id,'current_version'=>$n->current_version,'buyer'=>['name'=>trim(($buyer?->user?->first_name ?? '').' '.($buyer?->user?->last_name ?? '')),'organization'=>$buyer?->organization_name],'producer'=>['name'=>trim(($producer?->user?->first_name ?? '').' '.($producer?->user?->last_name ?? '')),'location'=>$producer?->location],'product'=>['listing_id'=>$listing?->listing_id,'name'=>$listing?->product_name,'category'=>$listing?->product_category ?? $listing?->category,'images'=>$listing?->images?->map(fn($i)=>['url'=>Storage::disk('public')->url($i->path),'is_primary'=>$i->is_primary])->values(),'image_url'=>$listing?->image_url],'demand_request_id'=>$n->demand_request_id,'demand_detail_id'=>$n->demand_detail_id,'version'=>$v,'versions'=>$n->versions,'events'=>$n->events,'agreement_snapshot'=>$n->agreement_snapshot,'order'=>$order ? ['order_id'=>$order->order_id,'fulfillment_status'=>$order->fulfillment_status,'payment_status'=>$order->payment_status] : null,'updated_at'=>$n->updated_at];
}
}
