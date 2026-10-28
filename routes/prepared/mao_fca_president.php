<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FcaPresidentController as C;
// NOT IMPORTED. Separate controlled activation approval required.
Route::prefix('fca-president')->middleware(\App\Http\Middleware\EnsureFcaPresidentEnabled::class)->group(function(){
 Route::post('/onboarding',[C::class,'onboarding'])->middleware('throttle:5,1');
 Route::middleware(['auth:sanctum',\App\Http\Middleware\EnsurePoliciesAccepted::class,'throttle:10,1'])->group(function(){
  Route::get('/directory',[C::class,'directory']);Route::get('/claims',[C::class,'mine']);
  Route::post('/claims/{claim}/resubmit',[C::class,'resubmit'])->whereNumber('claim');
  Route::post('/directory/{fca}/claim',[C::class,'match'])->whereNumber('fca');Route::get('/claims/{claim}',[C::class,'show'])->whereNumber('claim');
  Route::post('/claims/{claim}/send',[C::class,'send'])->whereNumber('claim');Route::post('/challenges/{challenge}/verify',[C::class,'verify'])->whereUuid('challenge');
  Route::post('/claims/{claim}/application',[C::class,'submit'])->whereNumber('claim');
 });
});

// Explicitly scoped Admin claim review; never an OTP or authority-approval bypass.
Route::prefix('fca-president-review')->middleware([\App\Http\Middleware\EnsureFcaPresidentEnabled::class,'auth:sanctum',\App\Http\Middleware\EnsurePoliciesAccepted::class])->group(function(){
 Route::get('/claims',[\App\Http\Controllers\FcaPresidentReviewController::class,'pending']);
 Route::get('/claims/{claim}',[\App\Http\Controllers\FcaPresidentReviewController::class,'show'])->whereNumber('claim');
 Route::post('/claims/{claim}',[\App\Http\Controllers\FcaPresidentReviewController::class,'decide'])->whereNumber('claim')->middleware('throttle:10,1');
 Route::post('/claims/{claim}/trusted-contact',[\App\Http\Controllers\FcaPresidentReviewController::class,'contact'])->whereNumber('claim')->middleware('throttle:5,1');
 Route::post('/claims/{claim}/manual-verification',[\App\Http\Controllers\FcaPresidentReviewController::class,'verifyManually'])->whereNumber('claim')->middleware('throttle:10,1');
});
