<?php

namespace ItmoSoft\MarketingAutomation\Tests\Feature;

use ItmoSoft\MarketingAutomation\Enums\ExecutionStatus;
use ItmoSoft\MarketingAutomation\Enums\WorkflowStatus;
use ItmoSoft\MarketingAutomation\Exceptions\InvalidWorkflowDefinitionException;
use ItmoSoft\MarketingAutomation\Services\WorkflowManager;
use ItmoSoft\MarketingAutomation\Tests\TestCase;

class WorkflowManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_it_creates_a_draft_workflow(): void
    {
        $workflow = app(WorkflowManager::class)->create('Welcome flow', 'subscriber.created', [
            ['action' => 'send_email', 'config' => ['template' => 'welcome']],
        ]);

        $this->assertSame(WorkflowStatus::Draft, $workflow->status);
    }

    public function test_it_rejects_empty_step_definitions(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        app(WorkflowManager::class)->create('Empty', 'event', []);
    }

    public function test_it_activates_and_pauses_a_workflow(): void
    {
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create('Welcome', 'subscriber.created', [['action' => 'send_email']]);
        $workflow = $manager->activate($workflow);
        $this->assertSame(WorkflowStatus::Active, $workflow->status);

        $workflow = $manager->pause($workflow);
        $this->assertSame(WorkflowStatus::Paused, $workflow->status);
    }

    public function test_it_creates_executions_for_active_matching_workflows_only(): void
    {
        $manager = app(WorkflowManager::class);
        $active = $manager->create('Active flow', 'subscriber.created', [['action' => 'send_email']]);
        $manager->activate($active);
        $manager->create('Draft flow', 'subscriber.created', [['action' => 'send_email']]);

        $executions = $manager->trigger('subscriber.created', ['subscriber_id' => 42]);

        $this->assertCount(1, $executions);
        $this->assertSame(ExecutionStatus::Queued, $executions[0]->status);
        $this->assertSame(['subscriber_id' => 42], $executions[0]->context);
    }

    public function test_it_tracks_execution_lifecycle(): void
    {
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create('Welcome', 'subscriber.created', [['action' => 'send_email']]);
        $manager->activate($workflow);
        $execution = $manager->trigger('subscriber.created')[0];

        $execution = $manager->markRunning($execution);
        $this->assertSame(ExecutionStatus::Running, $execution->status);
        $execution = $manager->markCompleted($execution);
        $this->assertSame(ExecutionStatus::Completed, $execution->status);
        $this->assertNotNull($execution->finished_at);
    }

    public function test_it_records_execution_failure(): void
    {
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create('Welcome', 'subscriber.created', [['action' => 'send_email']]);
        $manager->activate($workflow);
        $execution = $manager->trigger('subscriber.created')[0];

        $execution = $manager->markFailed($execution, 'Provider unavailable');

        $this->assertSame(ExecutionStatus::Failed, $execution->status);
        $this->assertSame('Provider unavailable', $execution->error);
    }
}
