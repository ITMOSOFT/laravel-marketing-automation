# ITMOSOFT Laravel Marketing Automation

Event-triggered marketing workflow definitions and execution tracking for Laravel 12 and 13.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require itmosoft/laravel-marketing-automation
php artisan vendor:publish --tag=itmosoft-marketing-automation-config
php artisan migrate
```

## Quick start

```php
use ItmoSoft\MarketingAutomation\Services\WorkflowManager;

$manager = app(WorkflowManager::class);

$workflow = $manager->create(
    'Welcome new subscriber',
    'subscriber.created',
    [
        ['action' => 'send_email', 'config' => ['template' => 'welcome']],
        ['action' => 'add_tag', 'config' => ['tag' => 'onboarding']],
    ],
);

$manager->activate($workflow);

// Call this from your domain event listener:
$executions = $manager->trigger('subscriber.created', [
    'subscriber_id' => 123,
]);
```

The `trigger()` method persists queued execution records for active workflows matching the event name. Your application should dispatch queue jobs for those records, resolve action names to trusted `WorkflowActionHandler` implementations, execute the configured steps, and update execution state.

## Lifecycle

Workflow: `draft` → `active` ↔ `paused` → `archived` (archiving is not implemented by this release).

Execution: `queued` → `running` → `completed` or `failed`.

State transitions are performed against freshly loaded, row-locked records inside database transactions. This prevents stale model instances from overwriting a later execution state. Database engines with effective row-level locking should be used for concurrent production workers.

## Action handlers

Implement `ItmoSoft\MarketingAutomation\Contracts\WorkflowActionHandler` for each supported action. Maintain an explicit allowlist of action names in your application. Treat workflow definitions and context as untrusted input if users can edit them. Handlers should be idempotent to tolerate queue retries.

## Configuration

`config/marketing-automation.php` supports table prefixes, table names, model class overrides, maximum step count, and maximum serialized context size. Configured table identifiers are validated before use.

## Security and operational notes

- Authorize workflow creation, editing, activation, and execution in your application.
- Never evaluate PHP or execute arbitrary code from workflow definitions.
- Use allowlisted action handlers and validate each handler's configuration.
- Add queue dispatch, retries, idempotency keys, and stale-execution recovery in the host application before production use.
- Tenant isolation is not automatic; scope workflows and executions in your application.
- Avoid storing secrets or unnecessary personal data in execution context.
- This package does not directly integrate with email campaigns, notifications, CRM leads, or external providers.

## Testing

```bash
composer install
composer test
```

## Scope boundaries

This package owns workflow definitions, event matching, queued execution records, and lifecycle state. It does not include a workflow visual editor, autonomous scheduler/worker, condition/branch evaluator, delay steps, provider integrations, or a built-in campaign/notification adapter.

## License

MIT.
