<?php

namespace ItmoSoft\MarketingAutomation\Contracts;

use ItmoSoft\MarketingAutomation\Models\WorkflowExecution;

interface WorkflowActionHandler
{
    /**
     * Execute one configured action.
     *
     * Implementations should be idempotent where possible because queue jobs
     * may be retried after a worker interruption.
     */
    public function handle(array $configuration, array $context, WorkflowExecution $execution): void;
}
