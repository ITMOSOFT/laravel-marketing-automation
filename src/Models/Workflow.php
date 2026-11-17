<?php

namespace ItmoSoft\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ItmoSoft\MarketingAutomation\Enums\WorkflowStatus;
use ItmoSoft\MarketingAutomation\Models\Concerns\UsesConfiguredTable;

class Workflow extends Model
{
    use UsesConfiguredTable;

    protected $guarded = [];

    protected function tableConfigKey(): string { return 'workflows'; }

    protected function casts(): array
    {
        return ['status' => WorkflowStatus::class, 'steps' => 'array', 'settings' => 'array'];
    }

    public function executions(): HasMany
    {
        return $this->hasMany(config('itmosoft-marketing-automation.models.execution'), 'workflow_id');
    }

    public function activate(): void
    {
        $this->forceFill(['status' => WorkflowStatus::Active])->save();
    }

    public function pause(): void
    {
        $this->forceFill(['status' => WorkflowStatus::Paused])->save();
    }
}
