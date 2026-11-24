<?php

namespace ItmoSoft\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ItmoSoft\MarketingAutomation\Enums\ExecutionStatus;
use ItmoSoft\MarketingAutomation\Models\Concerns\UsesConfiguredTable;

class WorkflowExecution extends Model
{
    use UsesConfiguredTable;

    protected $guarded = [];

    protected function tableConfigKey(): string
    {
        return 'executions';
    }

    protected function casts(): array
    {
        return [
            'status' => ExecutionStatus::class,
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            config('itmosoft-marketing-automation.models.workflow', Workflow::class),
            'workflow_id'
        );
    }
}
