<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\DemandDetail;
use App\Models\DemandRequest;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\TransactionNotification;
use App\Services\Notifications\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DemandRequestController extends Controller
{
    public function adminIndex()
    {
        $demands = DemandRequest::with('buyer.user')->withCount('demandDetails')->latest('request_id')->get();
        return response()->json(['data' => $demands->map(fn ($demand) => $this->present($demand))->values()]);
    }

    public function review(Request $request, int $id)
    {
        abort_unless(DemandRequest::publicationReviewAvailable(), 503, 'Demand review is temporarily unavailable. Please contact the administrator.');
        abort_unless(Auth::user()?->user_type === 'Admin', 403);
        $data = $request->validate(['decision' => 'required|in:Approved,Rejected', 'reason' => 'required_if:decision,Rejected|nullable|string|max:2000', 'expected_review_id' => 'required|integer|min:0']);
        $demand = DB::transaction(function () use ($id, $data) {
            $demand = DemandRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($demand->deadline && $demand->deadline->isPast() && !$demand->deadline->isToday(), 409, 'Expired demands cannot be reviewed.');
            abort_if((int) $demand->buyer?->user_id === (int) Auth::id(), 403);
            abort_unless(in_array($demand->status, ['Active', 'Offers Received', 'open'], true), 409, 'Closed demands cannot be published.');
            $current = $demand->currentPublicationReview();
            if ($current?->decision === $data['decision']) return $demand;
            abort_unless((int) ($current?->id ?? 0) === (int) $data['expected_review_id'], 409, 'The demand changed. Reload before reviewing.');
            abort_unless(!$current || $current->decision === 'Pending Review', 409, 'This submission has already been reviewed.');
            $review = $demand->publicationReviews()->create(['actor_user_id' => Auth::id(), 'decision' => $data['decision'], 'reason' => $data['reason'] ?? null]);
            // The existing database inbox is committed atomically with the decision/audit.
            // Failure rolls everything back; retrying cannot duplicate a committed notice.
            TransactionNotification::create([
                'recipient_user_id' => $demand->buyer->user_id,
                'title' => 'Demand request ' . strtolower($review->decision),
                'body' => 'Demand decision #' . $review->id . ' for request #' . $demand->request_id . ': ' . $review->decision . '. ' . $demand->product_name
                    . ($review->reason ? ' — ' . $review->reason : ''),
            ]);
            return $demand->fresh();
        });
        return response()->json(['message' => 'Publication review recorded.', 'data' => $this->present($demand)]);
    }

    public function index()
    {
        $buyer = $this->buyer();
        abort_unless($buyer, 404, 'Buyer profile not found.');
        $demands = $buyer->demandRequests()->withCount('demandDetails')->latest('request_id')->get();
        return response()->json(['submission_idempotency_available' => DemandRequest::publicationReviewAvailable() && \App\Services\Demand\BuyerDemandSubmission::available(), 'data' => $demands->map(fn ($demand) => $this->present($demand))->values()]);
    }

    public function show(int $id)
    {
        $demand = $this->ownedDemand($id)->loadCount('demandDetails');
        return response()->json(['data' => $this->present($demand)]);
    }

    public function store(Request $request)
    {
        abort_unless(DemandRequest::publicationReviewAvailable(), 503, 'Demand review is temporarily unavailable. Please contact the administrator.');
        $buyer = $this->buyer();
        abort_unless($buyer, 404, 'Buyer profile not found.');
        abort_unless($buyer->verification_status === 'Verified', 403, 'Only verified buyers can post demand requests.');
        $data = $this->validatedDemand($request, true);
        $key=$request->header('Idempotency-Key');
        $key=is_string($key)&&$key!==''?strtolower($key):null;
        validator(['key'=>$key],['key'=>'nullable|uuid'])->validate();
        $result=app(\App\Services\Demand\BuyerDemandSubmission::class)->submit($buyer,$data,$key,(int)Auth::id());
        return response()->json(['message'=>'Demand submitted for admin review.','data'=>$this->present($result['demand']),'replayed'=>$result['replayed']],$result['replayed']?200:201);
    }

    public function update(Request $request, int $id)
    {
        abort_unless(DemandRequest::publicationReviewAvailable(), 503, 'Demand review is temporarily unavailable. Please contact the administrator.');
        $demand = $this->ownedDemand($id);
        abort_unless(in_array($demand->status, ['Active', 'open', 'Offers Received'], true), 409, 'This demand request is no longer editable.');
        $data = $this->validatedDemand($request, false);
        DB::transaction(function () use ($demand, $data) {
            $locked = DemandRequest::whereKey($demand->request_id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['Active', 'open', 'Offers Received'], true), 409, 'This demand request is no longer editable.');
            $locked->fill($data);
            if ($locked->isDirty()) {
                $locked->save();
                $locked->publicationReviews()->create(['actor_user_id' => Auth::id(), 'decision' => 'Pending Review', 'reason' => 'Buyer edited demand; publication requires a new review.']);
            }
            $locked->demandDetails()->whereNotIn('status', ['Withdrawn', 'Declined', 'Expired'])->update(['status' => 'Under Review']);
            $producerIds = $locked->demandDetails()->with('producer')->get()->pluck('producer.user_id')->filter()->unique();
            foreach ($producerIds as $userId) {
                TransactionNotification::create(['recipient_user_id' => $userId, 'title' => 'Demand request updated', 'body' => $locked->product_name . ' was updated by the buyer. Review your offer.']);
            }
        });
        return response()->json(['message' => 'Demand request updated.', 'data' => $this->present($demand->fresh()->loadCount('demandDetails'))]);
    }

    public function cancel(int $id)
    {
        $demand = $this->ownedDemand($id);
        abort_unless(!in_array($demand->status, ['Cancelled', 'Completed'], true), 409, 'This demand request is already closed.');
        DB::transaction(function () use ($demand) {
            $demand = DemandRequest::whereKey($demand->request_id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($demand->status, ['Cancelled', 'Completed'], true), 409, 'This demand request is already closed.');
            $demand->status = 'Cancelled';
            $demand->save();
            $offers = $demand->demandDetails()->with('producer')->get();
            foreach ($offers as $offer) {
                if ($offer->producer?->user_id) TransactionNotification::create(['recipient_user_id' => $offer->producer->user_id, 'title' => 'Demand request cancelled', 'body' => $demand->product_name . ' is no longer accepting offers.']);
                if (!in_array($offer->status, ['Agreement Confirmed', 'Order Created', 'Completed'], true)) { $offer->status = 'Expired'; $offer->save(); }
            }
        });
        return response()->json(['message' => 'Demand request cancelled.']);
    }

    public function buyerOffers(int $id)
    {
        $demand = $this->ownedDemand($id);
        $offers = $demand->demandDetails()->with(['producer.user', 'listing.images'])->latest('id')->get();
        return response()->json(['data' => $offers->map(fn ($offer) => $this->presentOffer($offer))->values()]);
    }

    public function opportunities(Request $request)
    {
        $producer = $this->producer();
        abort_unless($producer, 404, 'Producer profile not found.');
        $query = DemandRequest::published()->with('buyer.user')->withCount('demandDetails')
            ->whereIn('status', ['Active', 'Offers Received', 'open'])
            ->where(function ($q) { $q->whereNull('deadline')->orWhereDate('deadline', '>=', today()); });
        if ($request->filled('search')) $query->where('product_name', 'like', '%' . $request->string('search') . '%');
        if ($request->filled('category')) $query->where('category', $request->string('category'));
        if ($request->filled('location')) $query->where('location', 'like', '%' . $request->string('location') . '%');
        if ($request->filled('minimum_budget')) $query->where('max_price_per_unit', '>=', (float) $request->input('minimum_budget'));
        if ($request->filled('deadline_before')) $query->whereDate('deadline', '<=', $request->input('deadline_before'));
        if ($request->input('sort') === 'deadline') $query->orderByRaw('deadline is null')->orderBy('deadline');
        elseif ($request->input('sort') === 'category') $query->orderBy('category')->latest('request_id');
        else $query->latest('request_id');
        $demands = $query->get()->map(function ($demand) use ($producer) {
            $demand->already_offered = $demand->demandDetails()->where('producer_id', $producer->producer_id)->whereNotIn('status', ['Withdrawn', 'Expired'])->exists();
            return $this->present($demand);
        });
        return response()->json(['data' => $demands]);
    }

    public function myOffers()
    {
        $producer = $this->producer();
        abort_unless($producer, 404, 'Producer profile not found.');
        $offers = DemandDetail::with(['demandRequest.buyer.user', 'listing.images'])->whereHas('demandRequest', fn ($q) => $q->published())->where('producer_id', $producer->producer_id)->latest('id')->get();
        return response()->json(['data' => $offers->map(fn ($offer) => $this->presentOffer($offer))->values()]);
    }

    public function submitOffer(Request $request, int $id)
    {
        $producer = $this->producer();
        abort_unless($producer, 404, 'Producer profile not found.');
        $demand = DemandRequest::published()->findOrFail($id);
        abort_unless(in_array($demand->status, ['Active', 'Offers Received', 'open'], true) && (!$demand->deadline || $demand->deadline->isToday() || $demand->deadline->isFuture()), 409, 'This request is no longer accepting offers.');
        $data = $request->validate([
            'listing_id' => 'required|integer|exists:product_listings,listing_id',
            'quantity_offered' => 'required|numeric|min:0.001', 'proposed_price' => 'required|numeric|min:0.01',
            'availability_date' => 'nullable|date|after_or_equal:today', 'delivery_terms' => 'nullable|string|max:2000',
            'quality_grade' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:4000',
        ]);
        $listing = ProductListing::where('listing_id', $data['listing_id'])->where('producer_id', $producer->producer_id)->firstOrFail();
        abort_unless(in_array(strtolower($listing->status ?? ''), ['active', 'available']), 422, 'Choose an active listing you own.');
        abort_if((float) $data['quantity_offered'] > (float) ($listing->quantity_available ?? $listing->quantity ?? 0) && empty($data['availability_date']), 422, 'If the full quantity is not currently listed as available, provide a realistic availability date.');
        $offer = DB::transaction(function () use ($demand, $producer, $listing, $data) {
            $locked = DemandRequest::whereKey($demand->request_id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->currentPublicationReview()?->decision === 'Approved', 404);
            abort_unless(in_array($locked->status, ['Active', 'Offers Received', 'open'], true) && (!$locked->deadline || $locked->deadline->isToday() || $locked->deadline->isFuture()), 409, 'This request is no longer accepting offers.');
            $offer = DemandDetail::where('demand_request_id', $locked->request_id)->where('producer_id', $producer->producer_id)->where('listing_id', $listing->listing_id)->whereNotIn('status', ['Withdrawn', 'Expired', 'Declined'])->first();
            if (!$offer) $offer = new DemandDetail(['demand_request_id' => $locked->request_id, 'producer_id' => $producer->producer_id]);
            elseif ($offer->exists) $offer->revisions = array_merge($offer->revisions ?? [], [['submitted_at' => $offer->updated_at?->toISOString(), 'quantity_offered' => $offer->quantity_offered ?? $offer->proposed_quantity, 'proposed_price' => $offer->proposed_price, 'availability_date' => $offer->availability_date?->toDateString(), 'delivery_terms' => $offer->delivery_terms, 'quality_grade' => $offer->quality_grade, 'notes' => $offer->notes]]);
            $offer->fill($data);
            $offer->proposed_quantity = (int) floor((float) $data['quantity_offered']);
            $offer->status = 'Submitted';
            $offer->save();
            if ($locked->status === 'Active' || $locked->status === 'open') { $locked->status = 'Offers Received'; $locked->save(); }
            if ($locked->buyer?->user_id) TransactionNotification::create(['recipient_user_id' => $locked->buyer->user_id, 'title' => 'New supply offer', 'body' => 'A producer submitted an offer for ' . $locked->product_name . '.']);
            return $offer;
        });
        return response()->json(['message' => 'Supply offer submitted.', 'data' => $this->presentOffer($offer->load(['demandRequest.buyer.user', 'listing.images', 'producer.user']))], 201);
    }

    public function reviseOffer(Request $request, int $id)
    {
        $producer = $this->producer();
        $offer = DemandDetail::with('demandRequest')->where('producer_id', $producer?->producer_id)->findOrFail($id);
        abort_unless(in_array($offer->status, ['Submitted', 'Under Review'], true), 409, 'This offer can no longer be revised.');
        abort_unless($offer->demandRequest?->currentPublicationReview()?->decision === 'Approved', 404);
        abort_unless($offer->demandRequest && in_array($offer->demandRequest->status, ['Active', 'Offers Received', 'open'], true) && (!$offer->demandRequest->deadline || $offer->demandRequest->deadline->isToday() || $offer->demandRequest->deadline->isFuture()), 409, 'This demand request is no longer accepting revised offers.');
        $request->validate(['quantity_offered' => 'required|numeric|min:0.001', 'proposed_price' => 'required|numeric|min:0.01', 'availability_date' => 'nullable|date|after_or_equal:today', 'delivery_terms' => 'nullable|string|max:2000', 'quality_grade' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:4000']);
        $listing = ProductListing::where('listing_id', $offer->listing_id)->where('producer_id', $producer->producer_id)->firstOrFail();
        abort_if((float) $request->quantity_offered > (float) ($listing->quantity_available ?? $listing->quantity ?? 0) && !$request->filled('availability_date'), 422, 'If the full quantity is not currently listed as available, provide a realistic availability date.');
        $offer->fill($request->only(['quantity_offered', 'proposed_price', 'availability_date', 'delivery_terms', 'quality_grade', 'notes']));
        $offer->proposed_quantity = (int) floor((float) $request->quantity_offered);
        $offer->status = 'Submitted';
        $offer->save();
        if ($offer->demandRequest?->buyer?->user_id) TransactionNotification::create(['recipient_user_id' => $offer->demandRequest->buyer->user_id, 'title' => 'Supply offer revised', 'body' => 'A producer updated an offer for ' . $offer->demandRequest->product_name . '.']);
        return response()->json(['message' => 'Offer revised and sent to the buyer.', 'data' => $this->presentOffer($offer->fresh(['demandRequest.buyer.user', 'listing.images', 'producer.user']))]);
    }

    public function withdrawOffer(int $id)
    {
        $producer = $this->producer();
        $offer = DemandDetail::where('producer_id', $producer?->producer_id)->findOrFail($id);
        abort_unless(in_array($offer->status, ['Submitted', 'Under Review'], true), 409, 'This offer can no longer be withdrawn.');
        $offer->status = 'Withdrawn';
        $offer->save();
        return response()->json(['message' => 'Offer withdrawn.']);
    }

    public function declineOffer(int $id, int $offerId)
    {
        $demand = $this->ownedDemand($id);
        $offer = $demand->demandDetails()->findOrFail($offerId);
        abort_unless(!in_array($offer->status, ['Agreement Confirmed', 'Order Created', 'Completed'], true), 409, 'This offer is already part of a confirmed agreement.');
        $offer->status = 'Declined';
        $offer->save();
        if ($offer->producer?->user_id) TransactionNotification::create(['recipient_user_id' => $offer->producer->user_id, 'title' => 'Supply offer declined', 'body' => 'Your offer for ' . $demand->product_name . ' was declined.']);
        return response()->json(['message' => 'Offer declined.']);
    }

    private function validatedDemand(Request $request, bool $required): array
    {
        $rule = $required ? 'required' : 'sometimes|required';
        return $request->validate([
            'product_name' => "$rule|string|max:255", 'category' => 'nullable|string|max:255',
            'quantity_needed' => "$rule|numeric|min:0.001", 'unit' => "$rule|string|max:50",
            'max_price_per_unit' => 'nullable|numeric|min:0', 'description' => 'nullable|string|max:4000',
            'location' => 'nullable|string|max:255', 'deadline' => "$rule|date|after_or_equal:today",
            'preferred_delivery_date' => 'nullable|date', 'quality_requirements' => 'nullable|string|max:2000',
            'special_requirements' => 'nullable|string|max:4000', 'partial_fulfillment' => 'sometimes|boolean',
        ]);
    }

    private function present(DemandRequest $demand): array
    {
        $status = $demand->status;
        if (in_array($status, ['Active', 'Offers Received', 'open'], true) && $demand->deadline && $demand->deadline->isPast() && !$demand->deadline->isToday()) $status = 'Expired';
        return [
            'id' => $demand->request_id, 'buyer_id' => $demand->buyer_id,
            'buyer' => $demand->buyer?->organization_name ?? trim(($demand->buyer?->user?->first_name ?? '') . ' ' . ($demand->buyer?->user?->last_name ?? '')),
            'product_name' => $demand->product_name, 'product' => $demand->product_name,
            'category' => $demand->category, 'quantity_needed' => $demand->quantity_needed, 'quantity' => $demand->quantity_needed,
            'unit' => $demand->unit, 'max_price_per_unit' => $demand->max_price_per_unit, 'target_price' => $demand->max_price_per_unit,
            'description' => $demand->description, 'location' => $demand->location,
            'deadline' => $demand->deadline?->format('Y-m-d'), 'preferred_delivery_date' => $demand->preferred_delivery_date?->format('Y-m-d'),
            'quality_requirements' => $demand->quality_requirements, 'special_requirements' => $demand->special_requirements,
            'partial_fulfillment' => (bool) $demand->partial_fulfillment, 'status' => $status,
            'review_status' => $demand->currentPublicationReview()?->decision ?? 'Legacy Unreviewed',
            'review_id' => $demand->currentPublicationReview()?->id ?? 0,
            'review_reason' => $demand->currentPublicationReview()?->reason,
            'review_available' => DemandRequest::publicationReviewAvailable(),
            'review_history' => DemandRequest::publicationReviewAvailable() && Auth::user()?->user_type === 'Admin' ? $demand->publicationReviews()->orderByDesc('id')->get(['id', 'actor_user_id', 'decision', 'reason', 'created_at']) : [],
            'offer_count' => $demand->demand_details_count ?? $demand->demandDetails()->count(),
            'created_at' => $demand->created_at, 'updated_at' => $demand->updated_at,
        ];
    }

    private function presentOffer(DemandDetail $offer): array
    {
        $offer->loadMissing(['demandRequest.buyer.user', 'producer.user', 'listing.images']);
        $producer = Producer::with('user')->find($offer->producer_id);
        return [
            'id' => $offer->id, 'demand_request_id' => $offer->demand_request_id,
            'listing_id' => $offer->listing_id, 'producer_name' => trim(($producer?->user?->first_name ?? '') . ' ' . ($producer?->user?->last_name ?? '')) ?: 'Producer',
            'listing_name' => $offer->listing?->product_name, 'quantity_offered' => $offer->quantity_offered ?? $offer->proposed_quantity,
            'proposed_price' => $offer->proposed_price, 'availability_date' => $offer->availability_date?->format('Y-m-d'),
            'delivery_terms' => $offer->delivery_terms, 'quality_grade' => $offer->quality_grade, 'notes' => $offer->notes,
            'status' => $offer->status, 'revisions' => $offer->revisions ?? [], 'created_at' => $offer->created_at,
            'updated_at' => $offer->updated_at, 'demand_request' => $offer->demandRequest ? $this->present($offer->demandRequest) : null,
        ];
    }

    private function ownedDemand(int $id): DemandRequest
    {
        $buyer = $this->buyer();
        abort_unless($buyer, 404, 'Buyer profile not found.');
        $demand = DemandRequest::with('buyer.user')->where('buyer_id', $buyer->buyer_id)->findOrFail($id);
        if (in_array($demand->status, ['Active', 'Offers Received', 'open'], true) && $demand->deadline && $demand->deadline->isPast() && !$demand->deadline->isToday()) {
            $demand->status = 'Expired';
            $demand->save();
        }
        return $demand;
    }

    private function buyer(): ?Buyer
    {
        return Buyer::where('user_id', Auth::user()?->user_id)->first();
    }

    private function producer(): ?Producer
    {
        return Producer::where('user_id', Auth::user()?->user_id)->first();
    }
}
