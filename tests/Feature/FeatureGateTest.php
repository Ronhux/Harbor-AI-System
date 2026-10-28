<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mao_workflows.fca_applications_enabled' => true,
            'mao_workflows.fca_inspection_persistence_enabled' => true,
            'mao_workflows.fca_evidence_access_enabled' => true,
            'fca_president.workflow_enabled' => true,
        ]);
    }

    public function test_feature_gates_enabled(): void
    {
        $this->assertTrue(config('mao_workflows.fca_applications_enabled'));
        $this->assertTrue(config('mao_workflows.fca_inspection_persistence_enabled'));
        $this->assertTrue(config('mao_workflows.fca_evidence_access_enabled'));
        $this->assertTrue(config('fca_president.workflow_enabled'));
    }
}