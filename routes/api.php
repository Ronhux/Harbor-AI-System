<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegistryController;
use App\Http\Controllers\ProducerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DemandRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/test-register', function (Request $request) {
    try {
        $user = \App\Models\User::create([
            'email' => $request->email,
            'password_hash' => \Illuminate\Support\Facades\Hash::make($request->password),
            'first_name' => explode(' ', $request->name)[0],
            'last_name' => explode(' ', $request->name)[1] ?? '',
            'user_type' => 'Farmer',
        ]);

        $createdUser = \App\Models\User::where('email', $request->email)->first();

        return response()->json([
            'user_id' => $createdUser->user_id,
            'message' => 'User created'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::post('/test-sql', function (Request $request) {
    try {
        $result = \DB::insert(
            "INSERT INTO users 
            (email, password_hash, first_name, last_name, user_type) 
            VALUES (?, ?, ?, ?, ?)",
            [
                $request->email,
                \Illuminate\Support\Facades\Hash::make($request->password),
                explode(' ', $request->name)[0],
                explode(' ', $request->name)[1] ?? '',
                'Farmer'
            ]
        );

        return response()->json([
            'result' => $result,
            'message' => 'SQL insert successful'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// =====================================================
// PROTECTED ROUTES
// =====================================================

Route::middleware('auth:sanctum')->group(function () {

    // -------------------------------------------------
    // PRODUCER
    // -------------------------------------------------

    Route::get(
        '/producer/dashboard',
        [ProducerController::class, 'dashboard']
    );

    Route::post(
        '/producer/listings',
        [ProducerController::class, 'createProductListing']
    );

    Route::put(
        '/producer/listings/{listingId}',
        [ProducerController::class, 'updateProductListing']
    );

    Route::delete(
        '/producer/listings/{listingId}',
        [ProducerController::class, 'deleteProductListing']
    );


    // -------------------------------------------------
    // BUYER
    // -------------------------------------------------

    Route::get(
        '/buyer/producers',
        [ProducerController::class, 'browseProducers']
    );

    Route::get(
        '/buyer/demands',
        [DemandRequestController::class, 'index']
    );

    Route::post(
        '/buyer/demands',
        [DemandRequestController::class, 'store']
    );


    // -------------------------------------------------
    // ORDERS
    // -------------------------------------------------

    // Get logged-in buyer's orders
    Route::get(
        '/buyer/orders',
        [OrderController::class, 'buyerOrders']
    );

    // Create new order
    Route::post(
        '/orders',
        [OrderController::class, 'store']
    );

    // Get one specific order
    Route::get(
        '/orders/{orderId}',
        [OrderController::class, 'show']
    );


    // -------------------------------------------------
    // ADMIN
    // -------------------------------------------------

    Route::prefix('admin')->group(function () {

        Route::get(
            '/registry',
            [RegistryController::class, 'index']
        );

        Route::get(
            '/registry/search',
            [RegistryController::class, 'search']
        );

        Route::post(
            '/registry/import',
            [RegistryController::class, 'import']
        );

        Route::put(
            '/registry/{id}',
            [RegistryController::class, 'update']
        );

        Route::post(
            '/registry/sync',
            [RegistryController::class, 'sync']
        );
    });
});


// =====================================================
// PUBLIC ADMIN REGISTRY
// =====================================================

Route::get(
    '/admin/registry',
    [RegistryController::class, 'index']
);

Route::get(
    '/admin/registry/search',
    [RegistryController::class, 'search']
);

Route::post(
    '/admin/registry/import',
    [RegistryController::class, 'import']
);

Route::put(
    '/admin/registry/{id}',
    [RegistryController::class, 'update']
);

Route::post(
    '/admin/registry/sync',
    [RegistryController::class, 'sync']
);