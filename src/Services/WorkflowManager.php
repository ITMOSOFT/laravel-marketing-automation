<?php

namespace ItmoSoft\MarketingAutomation\Services;

use Illuminate\Database\Eloquent\Model;
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
        $name = trim($name);
        $triggerEvent = trim($triggerEvent);

        if ($name === '') {
            throw new InvalidWorkflowDefinitionException('A workflow name is required.');
        }

        if ($triggerEvent === '' || strlen($triggerEvent) > 150) {
            throw new InvalidWorkflowDefinitionException('A trigger event between 1 and 150 characters is required.');
        }

        $this->validateDefinition($steps);
        $this->assertJsonSerializable($settings, 'Workflow settings');

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
        return DB::transaction(function () use ($workflow): Workflow {
            $fresh = $this->lockWorkflow($workflow);

            if (! in_array($fresh->status, [WorkflowStatus::Draft, WorkflowStatus::Paused], true)) {
                throw new InvalidWorkflowDefinitionException('Only draft or paused workflows can be activated.');
            }

            if (! is_array($fresh->steps) || $fresh->steps === []) {
                throw new InvalidWorkflowDefinitionException('A workflow must contain at least one step before activation.');
            }

            $fresh->forceFill(['status' => WorkflowStatus::Active])->save();

            return $fresh->refresh();
        });
    }

    public function pause(Workflow $workflow): Workflow
    {
        return DB::transaction(function () use ($workflow): Workflow {
            $fresh = $this->lockWorkflow($workflow);

            if ($fresh->status !== WorkflowStatus::Active) {
                throw new InvalidWorkflowDefinitionException('Only active workflows can be paused.');
            }

            $fresh->forceFill(['status' => WorkflowStatus::Paused])->save();

            return $fresh->refresh();
        });
    }

    /**
     * Persist queued execution records for active workflows matching an event.
     * Dispatching jobs is intentionally left to the host application's queue integration.
     *
     * @return array<int, WorkflowExecution>
     */
    public function trigger(string $event, array $context = []): array
    {
        $event = trim($event);
        if ($event === '' || strlen($event) > 150) {
            throw new InvalidWorkflowDefinitionException('A trigger event between 1 and 150 characters is required.');
        }

        $this->assertJsonSerializable($context, 'Workflow context');
        $encoded = json_encode($context, JSON_THROW_ON_ERROR);
        $maxBytes = (int) config('itmosoft-marketing-automation.max_context_bytes', 65535);

        if ($maxBytes < 1 || strlen($encoded) > $maxBytes) {
            throw new InvalidWorkflowDefinitionException('Workflow context exceeds the configured byte limit.');
        }

        return DB::transaction(function () use ($event, $context): array {
            $workflows = $this->workflowModel()::query()
                ->where('trigger_event', $event)
                ->where('status', WorkflowStatus::Active->value)
                ->lockForUpdate()
                ->get();

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
        return $this->transitionExecution(
            $execution,
            [ExecutionStatus::Queued],
            [
                'status' => ExecutionStatus::Running,
                'started_at' => now(),
                'finished_at' => null,
                'error' => null,
            ]
        );
    }

    public function markCompleted(WorkflowExecution $execution): WorkflowExecution
    {
        return $this->transitionExecution(
            $execution,
            [ExecutionStatus::Running],
            [
                'status' => ExecutionStatus::Completed,
                'finished_at' => now(),
                'error' => null,
            ]
        );
    }

    public function markFailed(WorkflowExecution $execution, string $error): WorkflowExecution
    {
        return $this->transitionExecution(
            $execution,
            [ExecutionStatus::Queued, ExecutionStatus::Running],
            [
                'status' => ExecutionStatus::Failed,
                'error' => substr($error, 0, 2000),
                'finished_at' => now(),
            ]
        );
    }

    /**
     * @param array<int, ExecutionStatus> $allowed
     * @param array<string, mixed> $attributes
     */
    protected function transitionExecution(
        WorkflowExecution $execution,
        array $allowed,
        array $attributes
    ): WorkflowExecution {
        return DB::transaction(function () use ($execution, $allowed, $attributes): WorkflowExecution {
            $modelClass = $this->executionModel();
            /** @var WorkflowExecution $fresh */
            $fresh = $modelClass::query()->lockForUpdate()->findOrFail($execution->getKey());

            if (! in_array($fresh->status, $allowed, true)) {
                throw new InvalidWorkflowDefinitionException('The execution is not in a state that permits this transition.');
            }

            $fresh->forceFill($attributes)->save();

            return $fresh->refresh();
        });
    }

    protected function lockWorkflow(Workflow $workflow): Workflow
    {
        $modelClass = $this->workflowModel();

        /** @var Workflow $fresh */
        $fresh = $modelClass::query()->lockForUpdate()->findOrFail($workflow->getKey());

        return $fresh;
    }

    protected function validateDefinition(array $steps): void
    {
        $max = (int) config('itmosoft-marketing-automation.max_steps', 50);

        if ($max < 1 || $steps === [] || count($steps) > $max) {
            throw new InvalidWorkflowDefinitionException("A workflow must contain between 1 and {$max} steps.");
        }

        foreach (array_values($steps) as $index => $step) {
            if (! is_array($step) || ! isset($step['action']) || ! is_string($step['action']) || trim($step['action']) === '') {
                throw new InvalidWorkflowDefinitionException("Workflow step {$index} must define a non-empty action name.");
            }

            if (isset($step['config']) && ! is_array($step['config'])) {
                throw new InvalidWorkflowDefinitionException("Workflow step {$index} config must be an array.");
            }

            $this->assertJsonSerializable($step, "Workflow step {$index}");
        }
    }

    protected function assertJsonSerializable(array $value, string $label): void
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidWorkflowDefinitionException("{$label} must be JSON serializable.", previous: $exception);
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
