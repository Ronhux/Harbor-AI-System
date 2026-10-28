<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class RouteMiddlewareTest extends TestCase
{
    public function test_fca_route_middleware(): void
    {
        $routes = Route::getRoutes();
        foreach ($routes as $route) {
            if (str_contains($route->uri(), 'fca/directory') && in_array('POST', $route->methods())) {
                $middleware = $route->gatherMiddleware();
                $this->assertContains(\App\Http\Middleware\EnsureFcaWorkflowEnabled::class, $middleware);
                $this->assertContains('auth:sanctum', $middleware);
                $this->assertContains(\App\Http\Middleware\EnsurePoliciesAccepted::class, $middleware);
                break;
            }
        }
    }
}