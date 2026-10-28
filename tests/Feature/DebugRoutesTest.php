<?php
namespace Tests\Feature;

use Tests\TestCase;

class DebugRoutesTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:'
            || is_file(dirname(__DIR__, 2) . '/bootstrap/cache/config.php')) {
            throw new \LogicException('FCA tests require uncached SQLite memory configuration.');
        }
        parent::setUp();

        config(['mao_workflows.fca_applications_enabled' => true]);
        $this->assertTrue(config('mao_workflows.fca_applications_enabled'));

        // Re-register FCA routes
        app('router')->prefix('api')->group(base_path('routes/prepared/mao_fca.php'));

        $routes = app('router')->getRoutes();
        $fcaRoutes = [];
        foreach ($routes as $route) {
            if (str_contains($route->uri(), 'fca')) {
                $fcaRoutes[] = $route->methods()[0] . ' ' . $route->uri();
            }
        }
        echo "Routes registered in setUp: " . count($fcaRoutes) . PHP_EOL;
        foreach ($fcaRoutes as $r) echo "  $r" . PHP_EOL;
    }

    public function test_routes_exist_at_test_time(): void
    {
        $routes = app('router')->getRoutes();
        $fcaRoutes = [];
        foreach ($routes as $route) {
            if (str_contains($route->uri(), 'fca')) {
                $fcaRoutes[] = $route->methods()[0] . ' ' . $route->uri();
            }
        }
        echo "Routes at test time: " . count($fcaRoutes) . PHP_EOL;
        foreach ($fcaRoutes as $r) echo "  $r" . PHP_EOL;

        $this->assertGreaterThan(0, count($fcaRoutes), 'FCA routes should exist');
    }
}