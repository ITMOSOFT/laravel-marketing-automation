<?php

namespace ItmoSoft\MarketingAutomation\Tests\Feature;

use ItmoSoft\MarketingAutomation\Enums\ExecutionStatus;
use ItmoSoft\MarketingAutomation\Enums\WorkflowStatus;
use ItmoSoft\MarketingAutomation\Exceptions\InvalidWorkflowDefinitionException;
use ItmoSoft\MarketingAutomation\Models\WorkflowExecution;
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
        $workflow = app(WorkflowManager::class)->create(' Welcome flow ', 'subscriber.created', [
            ['action' => 'send_email', 'config' => ['template' => 'welcome']],
        ]);

        $this->assertSame('Welcome flow', $workflow->name);
        $this->assertSame(WorkflowStatus::Draft, $workflow->status);
    }

    public function test_it_rejects_empty_name_event_and_steps(): void
    {
        $manager = app(WorkflowManager::class);

        foreach ([
            fn () => $manager->create(' ', 'event', [['action' => 'send_email']]),
            fn () => $manager->create('Name', ' ', [['action' => 'send_email']]),
            fn () => $manager->create('Name', 'event', []),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected invalid workflow definition exception.');
            } catch (InvalidWorkflowDefinitionException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_it_rejects_invalid_step_configuration(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        app(WorkflowManager::class)->create('Flow', 'event', [
            ['action' => 'send_email', 'config' => 'not-an-array'],
        ]);
    }

    public function test_it_rejects_non_json_serializable_steps(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        app(WorkflowManager::class)->create('Flow', 'event', [
            ['action' => 'send_email', 'config' => ['resource' => fopen('php://memory', 'r')]],
        ]);
    }

    public function test_it_activates_and_pauses_a_workflow(): void
    {
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create('Welcome', 'subscriber.created', [['action' => 'send_email']]);

        $workflow = $manager->activate($workflow);
        $this->assertSame(WorkflowStatus::Active, $workflow->status);

        $workflow = $manager->pause($workflow);
        $this->assertSame(WorkflowStatus::Paused, $workflow->status);

        $workflow = $manager->activate($workflow);
        $this->assertSame(WorkflowStatus::Active, $workflow->status);
    }

    public function test_it_rejects_invalid_workflow_state_transitions(): void
    {
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create('Welcome', 'subscriber.created', [['action' => 'send_email']]);

        $this->expectException(InvalidWorkflowDefinitionException::class);
        $manager->pause($workflow);
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

    public function test_it_rejects_oversized_context(): void
    {
        config(['itmosoft-marketing-automation.max_context_bytes' => 10]);

        $this->expectException(InvalidWorkflowDefinitionException::class);
        app(WorkflowManager::class)->trigger('subscriber.created', ['value' => str_repeat('x', 100)]);
    }

    public function test_it_rejects_invalid_context_data(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        app(WorkflowManager::class)->trigger('subscriber.created', ['resource' => fopen('php://memory', 'r')]);
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

    public function test_it_records_execution_failure_without_mbstring_dependency(): void
    {
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create('Welcome', 'subscriber.created', [['action' => 'send_email']]);
        $manager->activate($workflow);
        $execution = $manager->trigger('subscriber.created')[0];

        $execution = $manager->markFailed($execution, 'Provider unavailable');

        $this->assertSame(ExecutionStatus::Failed, $execution->status);
        $this->assertSame('Provider unavailable', $execution->error);
    }

    public function test_stale_execution_instance_cannot_overwrite_completed_state(): void
    {
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create('Welcome', 'subscriber.created', [['action' => 'send_email']]);
        $manager->activate($workflow);
        $execution = $manager->trigger('subscriber.created')[0];
        $stale = WorkflowExecution::query()->findOrFail($execution->id);

        $manager->markRunning($execution);
        $manager->markCompleted($execution);

        try {
            $manager->markFailed($stale, 'Late worker error');
            $this->fail('Expected stale transition to be rejected.');
        } catch (InvalidWorkflowDefinitionException) {
            $this->assertSame(ExecutionStatus::Completed, $stale->fresh()->status);
        }
    }

    public function test_it_rejects_starting_an_execution_more_than_once(): void
    {
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create('Welcome', 'subscriber.created', [['action' => 'send_email']]);
        $manager->activate($workflow);
        $execution = $manager->trigger('subscriber.created')[0];
        $stale = WorkflowExecution::query()->findOrFail($execution->id);

        $manager->markRunning($execution);

        $this->expectException(InvalidWorkflowDefinitionException::class);
        $manager->markRunning($stale);
    }

    public function test_it_rejects_empty_trigger_event(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        app(WorkflowManager::class)->trigger(' ');
    }

    public function test_it_uses_configured_table_names(): void
    {
        config([
            'itmosoft-marketing-automation.table_prefix' => 'custom_',
            'itmosoft-marketing-automation.tables.workflows' => 'flows',
            'itmosoft-marketing-automation.tables.executions' => 'runs',
        ]);

        $this->assertSame('custom_flows', (new \ItmoSoft\MarketingAutomation\Models\Workflow())->getTable());
        $this->assertSame('custom_runs', (new WorkflowExecution())->getTable());
    }
}
