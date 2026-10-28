<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\ImportPsaMajorCropsFarmgatePrices::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList(\Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class, \App\Http\Middleware\ForceJsonApiResponse::class);

        $middleware->alias([
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'fca.workflow' => \App\Http\Middleware\EnsureFcaWorkflowEnabled::class,
            'fca.president' => \App\Http\Middleware\EnsureFcaPresidentEnabled::class,
        ]);

        $middleware->api(append: [\App\Http\Middleware\BackupWriteBarrier::class]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonApiResponse::class,
            \App\Http\Middleware\RestrictFcaOnboardingToken::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Authentication can fail before route middleware normalizes Accept.
        $exceptions->shouldRenderJsonWhen(fn ($request, $exception) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (
            \Illuminate\Session\TokenMismatchException $e,
            $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'CSRF token mismatch',
                    'errors' => [
                        '_token' => [
                            'CSRF token verification failed'
                        ]
                    ]
                ], 419);
            }
        });

    })->create();
