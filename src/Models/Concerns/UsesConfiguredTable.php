<?php

namespace ItmoSoft\MarketingAutomation\Models\Concerns;

use ItmoSoft\MarketingAutomation\Exceptions\InvalidWorkflowDefinitionException;

trait UsesConfiguredTable
{
    abstract protected function tableConfigKey(): string;

    public function getTable(): string
    {
        $key = $this->tableConfigKey();
        $prefix = (string) config('itmosoft-marketing-automation.table_prefix', 'marketing_automation_');
        $table = (string) config("itmosoft-marketing-automation.tables.{$key}", $key);
        $identifier = $prefix.$table;

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidWorkflowDefinitionException('Configured table names may contain only letters, numbers, and underscores, and must start with a letter.');
        }

        return $identifier;
    }
}
