<?php

use App\Http\Controllers\FcaApplicationController;
use App\Http\Middleware\EnsureFcaWorkflowEnabled;
use App\Http\Middleware\EnsurePoliciesAccepted;
use Illuminate\Support\Facades\Route;

/*
 | Public preliminary FCA intake.
 | Anonymous prospective applicants can submit a preliminary registration request
 | without an account or identity document. This route is deliberately outside the
 | protected group: no auth:sanctum, no EnsurePoliciesAccepted. It remains behind
 | EnsureFcaWorkflowEnabled and throttling for abuse protection.
 */
Route::prefix('fca')->middleware(['throttle:30,1'])->group(function () {
    Route::get('/associations', [FcaApplicationController::class, 'publicAssociations']);
    Route::post('/applications/preliminary', [FcaApplicationController::class, 'preliminary']);
});

/*
 | Protected FCA workflows.
 | Submission with evidence, evidence upload, review, president verification and the
 | administrative registry view all keep their workflow gate, their authentication
 | and their policy-acceptance gate. None of it was loosened.
 */
Route::prefix('fca')->middleware(['auth:sanctum', EnsurePoliciesAccepted::class, 'throttle:60,1'])->group(function () {
    Route::get('/registry-records', [FcaApplicationController::class, 'associations']);
});

/*
 | Protected FCA workflows.
 | Submission, evidence, review and president verification all keep their workflow gate,
 | their authentication and their policy-acceptance gate. None of it was loosened.
 */
Route::prefix('fca')->middleware([EnsureFcaWorkflowEnabled::class, 'auth:sanctum', EnsurePoliciesAccepted::class])->group(function () {
    // Eligibility path for an actual submission: verified directory entries only.
    Route::get('/directory', [FcaApplicationController::class, 'directory']);

    Route::post('/evidence', [FcaApplicationController::class, 'upload'])->middleware('throttle:10,1');
    Route::get('/evidence/{receipt}', [FcaApplicationController::class, 'evidence'])->whereUuid('receipt');
    Route::get('/evidence/{receipt}/status', [FcaApplicationController::class, 'evidenceStatus'])->whereUuid('receipt');
    Route::post('/directory/{fca}/applications', [FcaApplicationController::class, 'store'])->whereNumber('fca');
    Route::get('/applications/{application}', [FcaApplicationController::class, 'show'])->whereNumber('application');
    Route::get('/applications/{application}/evidence', [FcaApplicationController::class, 'applicationEvidence'])->whereNumber('application');
    Route::get('/review/pending', [FcaApplicationController::class, 'pending']);
    Route::post('/review/{application}', [FcaApplicationController::class, 'review'])->whereNumber('application');
});