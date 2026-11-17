<?php

namespace ItmoSoft\MarketingAutomation\Services;

use Illuminate\Support\Facades\DB;
use ItmoSoft\MarketingAutomation\Enums\ExecutionStatus;
use ItmoSoft\MarketingAutomation\Enums\WorkflowStatus;
use ItmoSoft\MarketingAutomation\Exceptions\InvalidWorkflowDefinitionException;
use ItmoSoft\MarketingAutomation\Models\Workflow;
use ItmoSoft\MarketingAutomation\Models\WorkflowExecution;
use JsonException;

class WorkflowManager
{
    public function create(string $name, string $triggerEvent, array $steps, array $settings = []): Workflow
    {
        $this->validateDefinition($triggerEvent, $steps);

        return $this->workflowModel()::query()->create([
            'name' => $name,
            'trigger_event' => $triggerEvent,
            'status' => WorkflowStatus::Draft,
            'steps' => array_values($steps),
            'settings' => $settings,
        ]);
    }

    public function activate(Workflow $workflow): Workflow
    {
        if (! is_array($workflow->steps) || $workflow->steps === []) {
            throw new InvalidWorkflowDefinitionException('A workflow must contain at least one step before activation.');
        }

        $workflow->activate();

        return $workflow->refresh();
    }

    public function pause(Workflow $workflow): Workflow
    {
        $workflow->pause();

        return $workflow->refresh();
    }

    /**
     * Record queued executions for active workflows matching an event.
     * Dispatching jobs is intentionally left to the host application's queue integration.
     */
    public function trigger(string $event, array $context = []): array
    {
        $maxBytes = (int) config('itmosoft-marketing-automation.max_context_bytes', 65535);
        try {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidWorkflowDefinitionException('Workflow context must be JSON serializable.', previous: $exception);
        }

        if (strlen($encoded) > $maxBytes) {
            throw new InvalidWorkflowDefinitionException("Workflow context exceeds the configured {$maxBytes}-byte limit.");
        }

        $workflows = $this->workflowModel()::query()
            ->where('trigger_event', $event)
            ->where('status', WorkflowStatus::Active->value)
            ->get();

        return DB::transaction(function () use ($workflows, $context): array {
            $executions = [];
            foreach ($workflows as $workflow) {
                $executions[] = $this->executionModel()::query()->create([
                    'workflow_id' => $workflow->getKey(),
                    'status' => ExecutionStatus::Queued,
                    'context' => $context,
                    'current_step' => 0,
                ]);
            }

            return $executions;
        });
    }

    public function markRunning(WorkflowExecution $execution): WorkflowExecution
    {
        if ($execution->status !== ExecutionStatus::Queued) {
            throw new InvalidWorkflowDefinitionException('Only queued executions can start.');
        }

        $execution->forceFill([
            'status' => ExecutionStatus::Running,
            'started_at' => now(),
        ])->save();

        return $execution->refresh();
    }

    public function markCompleted(WorkflowExecution $execution): WorkflowExecution
    {
        if ($execution->status !== ExecutionStatus::Running) {
            throw new InvalidWorkflowDefinitionException('Only running executions can complete.');
        }

        $execution->forceFill([
            'status' => ExecutionStatus::Completed,
            'finished_at' => now(),
        ])->save();

        return $execution->refresh();
    }

    public function markFailed(WorkflowExecution $execution, string $error): WorkflowExecution
    {
        if (! in_array($execution->status, [ExecutionStatus::Queued, ExecutionStatus::Running], true)) {
            throw new InvalidWorkflowDefinitionException('Only queued or running executions can fail.');
        }

        $execution->forceFill([
            'status' => ExecutionStatus::Failed,
            'error' => mb_substr($error, 0, 2000),
            'finished_at' => now(),
        ])->save();

        return $execution->refresh();
    }

    protected function validateDefinition(string $triggerEvent, array $steps): void
    {
        if (trim($triggerEvent) === '') {
            throw new InvalidWorkflowDefinitionException('A trigger event is required.');
        }

        $max = (int) config('itmosoft-marketing-automation.max_steps', 50);
        if ($steps === [] || count($steps) > $max) {
            throw new InvalidWorkflowDefinitionException("A workflow must contain between 1 and {$max} steps.");
        }

        foreach ($steps as $index => $step) {
            if (! is_array($step) || ! isset($step['action']) || ! is_string($step['action']) || trim($step['action']) === '') {
                throw new InvalidWorkflowDefinitionException("Workflow step {$index} must define a non-empty action name.");
            }
            if (isset($step['config']) && ! is_array($step['config'])) {
                throw new InvalidWorkflowDefinitionException("Workflow step {$index} config must be an array.");
            }
        }
    }

    protected function workflowModel(): string
    {
        return config('itmosoft-marketing-automation.models.workflow', Workflow::class);
    }

    protected function executionModel(): string
    {
        return config('itmosoft-marketing-automation.models.execution', WorkflowExecution::class);
    }
}
