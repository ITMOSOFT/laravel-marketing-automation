<?php

return [
    'table_prefix' => 'marketing_automation_',
    'tables' => [
        'workflows' => 'workflows',
        'executions' => 'executions',
    ],
    'models' => [
        'workflow' => ItmoSoft\MarketingAutomation\Models\Workflow::class,
        'execution' => ItmoSoft\MarketingAutomation\Models\WorkflowExecution::class,
    ],
    'max_steps' => 50,
    'max_context_bytes' => 65535,
];
