<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mao_workflows.fca_applications_enabled' => true]);
        $this->assertTrue(config('mao_workflows.fca_applications_enabled'));
    }

    public function test_config_is_set(): void
    {
        $this->assertTrue(config('mao_workflows.fca_applications_enabled'));
    }
}