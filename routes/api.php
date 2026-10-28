<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\RegistryController;
use App\Http\Controllers\ProducerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DemandRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\NegotiationController;
use App\Http\Controllers\ProductAssistantAnalysisController;
use App\Http\Controllers\ProducerMarketInsightsController;
use App\Http\Controllers\BuyerPriceReferenceController;
use App\Http\Middleware\EnsurePoliciesAccepted;
use App\Http\Middleware\EnsureProducerApproved;
use App\Http\Middleware\EnsureUserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/registry/verify-producer', [RegistryController::class, 'verifyProducerRecord']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Active policy versions served to the public registration flow.
Route::get('/policies', [PolicyController::class, 'index']);


// =====================================================
// PROTECTED ROUTES
// =====================================================

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/session', fn (Request $request) => response()->json(['user_type' => $request->user()->user_type])->header('Cache-Control', 'no-store, private'));

    // -------------------------------------------------
    // POLICY AGREEMENT (accessible while the gate is pending)
    // -------------------------------------------------

    Route::get('/policies/acceptance/status', [PolicyController::class, 'status']);
    Route::post('/policies/acceptance', [PolicyController::class, 'accept']);

    // -------------------------------------------------
    // POLICY-GATED FEATURES
    // -------------------------------------------------

    // Base policy acceptance for all authenticated features
    Route::middleware(EnsurePoliciesAccepted::class)->group(function () {

        // -------------------------------------------------
        // PRODUCER (requires Producer approval)
        // -------------------------------------------------

        Route::middleware(EnsureProducerApproved::class)->prefix('producer')->group(function () {
            Route::get('/dashboard', [ProducerController::class, 'dashboard'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::get('/listings', [ProducerController::class, 'listings'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::get('/profile', [ProducerController::class, 'producerProfile'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::put('/profile', [ProducerController::class, 'updateProducerProfile'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::post('/listings', [ProducerController::class, 'createProductListing'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::put('/listings/{listingId}', [ProducerController::class, 'updateProductListing'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::delete('/listings/{listingId}', [ProducerController::class, 'deleteProductListing'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::post('/product-assistant/analyze', [ProductAssistantAnalysisController::class, 'analyze'])
                ->middleware([EnsureUserType::class . ':Farmer,Fisherfolk,Producer', 'throttle:6,1']);

            Route::get('/market-insights', [ProducerMarketInsightsController::class, 'index'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::get('/negotiations', [NegotiationController::class, 'index'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::get('/negotiations/{id}', [NegotiationController::class, 'show'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::post('/negotiations/{id}/respond', [NegotiationController::class, 'respond'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::get('/orders', [NegotiationController::class, 'producerOrders'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::patch('/orders/{id}', [NegotiationController::class, 'updateOrder'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::get('/demand-opportunities', [DemandRequestController::class, 'opportunities'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::get('/offers', [DemandRequestController::class, 'myOffers'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::post('/demands/{id}/offers', [DemandRequestController::class, 'submitOffer'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::patch('/offers/{id}', [DemandRequestController::class, 'reviseOffer'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');

            Route::post('/offers/{id}/withdraw', [DemandRequestController::class, 'withdrawOffer'])
                ->middleware(EnsureUserType::class . ':Farmer,Fisherfolk,Producer');
        });

        // -------------------------------------------------
        // BUYER (no Producer approval required)
        // -------------------------------------------------

        Route::middleware(EnsureUserType::class . ':Buyer')->prefix('buyer')->group(function () {
            Route::get('/profile', [\App\Http\Controllers\BuyerProfileController::class, 'show']);
            Route::put('/profile', [\App\Http\Controllers\BuyerProfileController::class, 'update']);
            Route::post('/products/{listingId}/view', [ProducerController::class, 'recordProductView']);
            Route::get('/negotiations', [NegotiationController::class, 'index']);
            Route::post('/negotiations', [NegotiationController::class, 'store']);
            Route::get('/negotiations/{id}', [NegotiationController::class, 'show']);
            Route::post('/negotiations/{id}/respond', [NegotiationController::class, 'respond']);
            Route::post('/negotiations/{id}/order', [NegotiationController::class, 'createOrder']);
            Route::post('/demand-offers/{offerId}/negotiate', [NegotiationController::class, 'startOffer']);
            Route::patch('/orders/{id}', [NegotiationController::class, 'updateOrder']);
        });

        Route::get('/buyer/producers', [ProducerController::class, 'browseProducers'])->middleware(EnsureUserType::class . ':Buyer');
        Route::get('/buyer/products', [ProducerController::class, 'browseProducts'])
            ->middleware(EnsureUserType::class . ':Buyer');
        Route::post('/buyer/price-trends/references', [BuyerPriceReferenceController::class, 'comparisons'])
            ->middleware(EnsureUserType::class . ':Buyer');

        Route::middleware(EnsureUserType::class . ':Buyer')->prefix('buyer/demands')->group(function () {
            Route::get('/', [DemandRequestController::class, 'index']);
            Route::post('/', [DemandRequestController::class, 'store']);
            Route::get('/{id}', [DemandRequestController::class, 'show']);
            Route::put('/{id}', [DemandRequestController::class, 'update']);
            Route::post('/{id}/cancel', [DemandRequestController::class, 'cancel']);
            Route::get('/{id}/offers', [DemandRequestController::class, 'buyerOffers']);
            Route::post('/{id}/offers/{offerId}/decline', [DemandRequestController::class, 'declineOffer']);
        });

        // -------------------------------------------------
        // NOTIFICATIONS
        // -------------------------------------------------

        Route::get('/notifications', function (Request $request) {
            $query = \App\Models\TransactionNotification::where('recipient_user_id', $request->user()->user_id);
            $unreadCount = (clone $query)->whereNull('read_at')->count();
            return response()->json(['data' => $query->latest()->limit(30)->get(), 'unread_count' => $unreadCount]);
        });
        Route::patch('/notifications/{id}/read', function (Request $request, int $id) {
            $notification = \App\Models\TransactionNotification::where('recipient_user_id', $request->user()->user_id)->findOrFail($id);
            \App\Models\TransactionNotification::whereKey($notification->id)->whereNull('read_at')->update(['read_at'=>now()]);
            $notification->refresh();
            $unreadCount = \App\Models\TransactionNotification::where('recipient_user_id', $request->user()->user_id)->whereNull('read_at')->count();
            return response()->json(['message' => 'Notification marked as read.', 'unread_count' => $unreadCount, 'read_at' => $notification->read_at]);
        });

        // -------------------------------------------------
        // ORDERS
        // -------------------------------------------------

        Route::get('/buyer/orders', [OrderController::class, 'buyerOrders'])->middleware(EnsureUserType::class . ':Buyer');
        Route::post('/orders', [OrderController::class, 'store'])->middleware(EnsureUserType::class . ':Buyer');
        Route::get('/orders/{orderId}', [OrderController::class, 'show'])->middleware(EnsureUserType::class . ':Buyer,Farmer,Fisherfolk,Producer');

        // -------------------------------------------------
        // ADMIN
        // -------------------------------------------------

        Route::prefix('admin')->middleware(EnsureUserType::class . ':Admin')->group(function () {
            Route::get('/analytics/monitoring', [AdminAnalyticsController::class, 'monitoring']);
            Route::get('/analytics/supply-demand', [AdminAnalyticsController::class, 'supplyDemand']);
            Route::get('/reports', [ReportController::class, 'index']);
            Route::get('/reports/{id}', [ReportController::class, 'show']);
            Route::post('/reports/preview', [ReportController::class, 'preview']);
            Route::post('/reports', [ReportController::class, 'generate']);
            Route::get('/reports/{id}/download', [ReportController::class, 'download']);
            Route::get('/reports/{id}/download/{format}', [ReportController::class, 'download']);
            Route::get('/supply', [ReportController::class, 'supply']);
            Route::get('/producers/pending', [ProducerController::class, 'pendingVerificationRequests']);
            Route::patch('/producers/{producerId}/verification', [ProducerController::class, 'updateVerificationStatus']);
            Route::patch('/producers/{producerId}/account-status', [ProducerController::class, 'updateAccountStatus']);
            Route::get('/registry', [RegistryController::class, 'index']);
            Route::get('/registry/search', [RegistryController::class, 'search']);
            Route::get('/demands', [DemandRequestController::class, 'adminIndex']);
            Route::post('/demands/{id}/review', [DemandRequestController::class, 'review']);
        });

        // -------------------------------------------------
        // SUPER ADMIN
        // -------------------------------------------------

        Route::prefix('superadmin')->middleware(EnsureUserType::class . ':Super Admin')->group(function () {
            Route::prefix('backups')->middleware('throttle:10,1,backup-general:')->group(function () {
                Route::get('/', [\App\Http\Controllers\BackupController::class, 'index']);
                Route::get('/instructions', [\App\Http\Controllers\BackupController::class, 'instructions']);
                Route::post('/', [\App\Http\Controllers\BackupController::class, 'store'])->middleware('throttle:2,10,backup-create:');
                Route::post('/{id}/verify', [\App\Http\Controllers\BackupController::class, 'verify'])->whereUuid('id');
                Route::post('/{id}/download', [\App\Http\Controllers\BackupController::class, 'download'])->whereUuid('id')->middleware('throttle:3,10,backup-download:');
                Route::post('/{id}/drill', [\App\Http\Controllers\BackupController::class, 'drill'])->whereUuid('id')->middleware('throttle:2,10,backup-drill:');
            });
            Route::get('/overview', [\App\Http\Controllers\SuperAdminController::class, 'overview']);
            Route::get('/admins', [\App\Http\Controllers\SuperAdminController::class, 'administrators']);
            Route::patch('/admins/{userId}/status', [\App\Http\Controllers\SuperAdminController::class, 'updateAdminStatus']);
            Route::post('/registry/import', [RegistryController::class, 'import']);
        });
    });
});

// =====================================================
// FCA (Farmers Cooperatives Association)
//
// The FCA route definitions were authored under routes/prepared/ and were deliberately left
// unwired. They are registered here, inside the api group, so they carry the same /api prefix and
// the same api middleware stack (ForceJsonApiResponse, RestrictFcaOnboardingToken) as every
// other API route.
//
// Registration grants no access by itself. Both files are closed by feature gates that are
// enforced at request time and abort before the controller runs:
//
//   fca            -> EnsureFcaWorkflowEnabled -> FcaWorkflowPolicy::enabled()
//                     (config/mao_workflows.php: fca_applications_enabled)
//   fca-president  -> EnsureFcaPresidentEnabled
//                     (config/fca_president.php: workflow_enabled)
//
// While those flags are false every route below returns 503. Authentication (auth:sanctum),
// policy acceptance (EnsurePoliciesAccepted), throttling, and the administrator / representative
// authority checks inside the controllers are unchanged and were authored with these routes.
//
// KADIWA is now registered (routes/prepared/mao_kadiwa.php). It is closed by its own gate:
//   kadiwa -> EnsureKadiwaWorkflowEnabled -> KadiwaWorkflowPolicy::enabled()
//             (config/mao_workflows.php: kadiwa_enabled)
// KADIWA facilitates direct producer-to-customer selling at published events. It is not an
// institutional purchasing process and grants no procurement authority. Its authorities are
// separate explicit assignments (manager / coordinator / inquiry reviewer), never a role alone.
//
// Institutional Demand remains intentionally unregistered and is out of scope.
require __DIR__ . '/prepared/mao_fca.php';
require __DIR__ . '/prepared/mao_fca_president.php';
require __DIR__ . '/prepared/mao_kadiwa.php';