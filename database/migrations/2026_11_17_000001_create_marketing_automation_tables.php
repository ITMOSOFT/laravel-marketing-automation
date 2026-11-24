<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItmoSoft\MarketingAutomation\Exceptions\InvalidWorkflowDefinitionException;

return new class extends Migration
{
    protected function tableName(string $key, string $fallback): string
    {
        $prefix = (string) config('itmosoft-marketing-automation.table_prefix', 'marketing_automation_');
        $table = (string) config("itmosoft-marketing-automation.tables.{$key}", $fallback);
        $name = $prefix.$table;

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidWorkflowDefinitionException('Configured table names may contain only letters, numbers, and underscores, and must start with a letter.');
        }

        return $name;
    }

    public function up(): void
    {
        $workflows = $this->tableName('workflows', 'workflows');
        $executions = $this->tableName('executions', 'executions');

        Schema::create($workflows, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('trigger_event', 150)->index();
            $table->string('status', 20)->default('draft')->index();
            $table->json('steps');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create($executions, function (Blueprint $table) use ($workflows): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained($workflows)->cascadeOnDelete();
            $table->string('status', 20)->default('queued')->index();
            $table->json('context')->nullable();
            $table->unsignedInteger('current_step')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['workflow_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('executions', 'executions'));
        Schema::dropIfExists($this->tableName('workflows', 'workflows'));
    }
};
