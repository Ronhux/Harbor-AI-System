<?php

namespace App\Http\Controllers;

use App\Services\Mao\KadiwaEventService;
use App\Services\Mao\KadiwaInquiryService;
use App\Services\Mao\KadiwaParticipationService;
use App\Services\Mao\KadiwaScheduleProjection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * KADIWA endpoints.
 *
 * Response shapes are explicit so no producer identity, reviewer identity, submission key,
 * payload hash or internal column can leak into a client payload. A KADIWA approval is a
 * permission to sell at a venue - never a purchase, order, award, procurement decision or
 * payment, and no endpoint here touches pricing, orders or procurement records.
 */
class KadiwaController extends Controller
{
    public function __construct(
        private KadiwaEventService $events,
        private KadiwaParticipationService $participation,
        private KadiwaInquiryService $inquiries,
        private KadiwaScheduleProjection $schedule,
    ) {}

    // -----------------------------------------------------------------
    // Reference
    // -----------------------------------------------------------------

    /** Projected recurring dates only. This never confirms an event, venue or coordinator. */
    public function regularSchedule()
    {
        return response()->json($this->schedule->projected());
    }

    public function capabilities(Request $request)
    {
        return response()->json($this->events->capabilities($request->user()));
    }

    // -----------------------------------------------------------------
    // Events
    // -----------------------------------------------------------------

    public function index(Request $request)
    {
        $events = $this->events->published($request->user(), $request->boolean('upcoming'));

        return response()->json($events);
    }

    public function show(Request $request, int $event)
    {
        return response()->json($this->events->present([$this->events->show($request->user(), $event)])[0]);
    }

    /** Manager-only. Always persists a draft; publication is a separate confirmed action. */
    public function store(Request $request)
    {
        $event = $this->guard(fn () => $this->events->store($request->user(), $request->all()), 'event_store');

        return response()->json($this->events->present([$event])[0], 201);
    }

    public function update(Request $request, int $event)
    {
        $input = $request->all();
        $updated = $this->guard(fn () => $this->events->update($request->user(), $event, $input), 'event_update');

        return response()->json($this->events->present([$updated])[0]);
    }

    public function transition(Request $request, int $event)
    {
        $input = $request->all();
        $updated = $this->guard(fn () => $this->events->transition($request->user(), $event, $input), 'event_status');

        return response()->json($this->events->present([$updated])[0]);
    }

    /** Returns the event to draft so producers must re-read republished dates. */
    public function reschedule(Request $request, int $event)
    {
        $input = $request->all();
        $updated = $this->guard(fn () => $this->events->reschedule($request->user(), $event, $input), 'event_reschedule');

        return response()->json($this->events->present([$updated])[0]);
    }

    public function assigned(Request $request)
    {
        return response()->json($this->events->assigned($request->user()));
    }

    public function assignCoordinator(Request $request, int $event)
    {
        $this->guard(fn () => $this->events->assignCoordinator($request->user(), $event, $request->all()), 'event_coordinator');

        return response()->json(['assigned' => true]);
    }

    public function queue(Request $request, int $event)
    {
        return response()->json($this->events->queue($request->user(), $event));
    }

    public function audits(Request $request, int $event)
    {
        return response()->json(['data' => $this->events->audits($request->user(), $event)]);
    }

    // -----------------------------------------------------------------
    // Participation
    // -----------------------------------------------------------------

    public function myApplications(Request $request)
    {
        return response()->json($this->participation->mine($request->user()));
    }

    public function showApplication(Request $request, int $application)
    {
        return response()->json($this->participation->show($request->user(), $application));
    }

    public function apply(Request $request, int $event)
    {
        $created = $this->guard(fn () => $this->participation->apply($request->user(), $event, $request->all()), 'participation_apply');

        return response()->json($this->participation->presentApplication($created), 201);
    }

    public function resubmit(Request $request, int $application)
    {
        $updated = $this->guard(fn () => $this->participation->resubmit($request->user(), $application, $request->all()), 'participation_resubmit');

        return response()->json($this->participation->presentApplication($updated));
    }

    /** Reviewer decisions and producer withdrawals share one guarded entry point. */
    public function review(Request $request, int $application)
    {
        $updated = $this->guard(fn () => $this->participation->review($request->user(), $application, $request->all()), 'participation_review');

        return response()->json($this->participation->presentApplication($updated));
    }

    // -----------------------------------------------------------------
    // Assisted inquiries (never an application or an approval)
    // -----------------------------------------------------------------

    public function inquiries(Request $request)
    {
        return response()->json($this->inquiries->forOwner($request->user(), $this->page($request)));
    }

    public function inquiryReview(Request $request)
    {
        return response()->json($this->inquiries->forReviewer($request->user(), $this->page($request)));
    }

    public function inquire(Request $request)
    {
        $created = $this->guard(fn () => $this->inquiries->store($request->user(), $request->all()), 'inquiry_store');

        return response()->json($this->inquiries->present($created), 201);
    }

    public function answerInquiry(Request $request, int $inquiry)
    {
        $answered = $this->guard(fn () => $this->inquiries->answer($request->user(), $inquiry, $request->all()), 'inquiry_answer');

        return response()->json($this->inquiries->present($answered));
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    /** A driver error never reaches the client as a schema or credential message. */
    private function guard(callable $operation, string $context)
    {
        try {
            return $operation();
        } catch (UniqueConstraintViolationException) {
            abort(409, 'That KADIWA operation was already recorded. Reload the current state.');
        } catch (QueryException $e) {
            // Exception class only: the driver message can contain schema and binding detail.
            Log::warning('KADIWA database operation stopped.', ['context' => $context, 'driver' => $e->getCode()]);

            abort(503, 'The KADIWA operation could not complete. No automatic retry.');
        }
    }
}