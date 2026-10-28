<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConfigAfterSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mao_workflows.fca_applications_enabled' => true]);
    }

    public function test_config_is_set_after_setup(): void
    {
        $this->assertTrue(config('mao_workflows.fca_applications_enabled'));
    }
}