<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('itmosoft-marketing-automation.table_prefix', 'marketing_automation_');
        $workflows = $prefix.config('itmosoft-marketing-automation.tables.workflows', 'workflows');
        $executions = $prefix.config('itmosoft-marketing-automation.tables.executions', 'executions');

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
        $prefix = config('itmosoft-marketing-automation.table_prefix', 'marketing_automation_');
        Schema::dropIfExists($prefix.config('itmosoft-marketing-automation.tables.executions', 'executions'));
        Schema::dropIfExists($prefix.config('itmosoft-marketing-automation.tables.workflows', 'workflows'));
    }
};
