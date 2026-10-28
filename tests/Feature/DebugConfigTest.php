<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;

class DebugConfigTest extends TestCase
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
        echo "Config in setUp: " . (config('mao_workflows.fca_applications_enabled') ? 'true' : 'false') . PHP_EOL;

        app('router')->prefix('api')->group(base_path('routes/prepared/mao_fca.php'));
    }

    public function test_config_at_test_time(): void
    {
        echo "Config at test start: " . (config('mao_workflows.fca_applications_enabled') ? 'true' : 'false') . PHP_EOL;

        // Check the middleware directly
        $policy = app(\App\Policies\FcaWorkflowPolicy::class);
        try {
            $policy->enabled();
            echo "Policy enabled() passed" . PHP_EOL;
        } catch (\Exception $e) {
            echo "Policy enabled() threw: " . get_class($e) . " - " . $e->getMessage() . PHP_EOL;
        }

        $this->assertTrue(config('mao_workflows.fca_applications_enabled'));
    }
}