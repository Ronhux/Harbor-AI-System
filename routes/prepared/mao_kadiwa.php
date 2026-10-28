<?php

use App\Http\Controllers\KadiwaController;
use App\Http\Middleware\EnsureKadiwaWorkflowEnabled;
use App\Http\Middleware\EnsurePoliciesAccepted;
use Illuminate\Support\Facades\Route;

/*
 * KADIWA market facilitation.
 *
 * Every route below is closed by EnsureKadiwaWorkflowEnabled, which aborts 404 while
 * config('mao_workflows.kadiwa_enabled') is false. Registration grants no access by itself.
 *
 * Authorities inside the controllers are separate and explicit:
 *   - participation requires an Admin-approved producer account that owns a producer record
 *   - event management requires kadiwa_manager_user_ids assignment
 *   - a coordinator reaches only the events named for them in kadiwa_event_coordinators
 *   - inquiry review requires kadiwa_inquiry_reviewer_user_ids assignment
 *
 * KADIWA is a venue for producers to sell directly to customers. No route here is an
 * institutional purchase, procurement award, order, reservation or payment.
 */
Route::prefix('kadiwa')->middleware([EnsureKadiwaWorkflowEnabled::class, 'auth:sanctum', EnsurePoliciesAccepted::class])
    ->group(function () {

        // Projected recurring dates. Not an authorized schedule.
        Route::get('/regular-schedule', [KadiwaController::class, 'regularSchedule']);

        // Explicit authority only; never inferred from a role.
        Route::get('/capabilities', [KadiwaController::class, 'capabilities']);

        // Published events available to producers. Drafts are never included.
        Route::get('/events', [KadiwaController::class, 'index']);
        Route::get('/events/{event}', [KadiwaController::class, 'show'])->whereNumber('event');

        // Producer participation.
        Route::post('/events/{event}/applications', [KadiwaController::class, 'apply'])
            ->whereNumber('event')->middleware('throttle:10,1');
        Route::get('/applications', [KadiwaController::class, 'myApplications']);
        Route::get('/applications/{application}', [KadiwaController::class, 'showApplication'])->whereNumber('application');
        Route::post('/applications/{application}/resubmit', [KadiwaController::class, 'resubmit'])
            ->whereNumber('application')->middleware('throttle:10,1');
        Route::post('/applications/{application}/review', [KadiwaController::class, 'review'])
            ->whereNumber('application')->middleware('throttle:20,1');

        // Authorized event management.
        Route::get('/assigned', [KadiwaController::class, 'assigned']);
        Route::post('/events', [KadiwaController::class, 'store']);
        Route::patch('/events/{event}', [KadiwaController::class, 'update'])->whereNumber('event');
        Route::post('/events/{event}/status', [KadiwaController::class, 'transition'])->whereNumber('event');
        Route::post('/events/{event}/reschedule', [KadiwaController::class, 'reschedule'])->whereNumber('event');
        Route::post('/events/{event}/coordinators', [KadiwaController::class, 'assignCoordinator'])->whereNumber('event');
        Route::get('/events/{event}/queue', [KadiwaController::class, 'queue'])->whereNumber('event');
        Route::get('/events/{event}/audit', [KadiwaController::class, 'audits'])->whereNumber('event');

        // Assisted inquiries. A response is informational and never approves participation.
        Route::get('/inquiries', [KadiwaController::class, 'inquiries']);
        Route::post('/inquiries', [KadiwaController::class, 'inquire'])->middleware('throttle:10,1');
        Route::get('/inquiry-review', [KadiwaController::class, 'inquiryReview']);
        Route::post('/inquiry-review/{inquiry}', [KadiwaController::class, 'answerInquiry'])
            ->whereNumber('inquiry')->middleware('throttle:20,1');
    });