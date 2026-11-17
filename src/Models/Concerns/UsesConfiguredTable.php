<?php

namespace ItmoSoft\MarketingAutomation\Models\Concerns;

trait UsesConfiguredTable
{
    abstract protected function tableConfigKey(): string;

    public function getTable(): string
    {
        $key = $this->tableConfigKey();

        return config('itmosoft-marketing-automation.table_prefix', 'marketing_automation_')
            .config("itmosoft-marketing-automation.tables.{$key}", $key);
    }
}
