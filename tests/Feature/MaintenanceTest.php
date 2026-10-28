<?php
namespace Tests\Feature;

use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    public function test_maintenance_mode(): void
    {
        $this->assertFalse($this->app->maintenanceMode()->active());
    }
}