# Release guide — v1.0.1

Repository: `ITMOSOFT/laravel-marketing-automation`

Composer package: `itmosoft/laravel-marketing-automation`

## Suggested repository description

Event-triggered marketing workflows, execution tracking, and pluggable action handlers for Laravel applications.

## User-run Git commands

Apply and review the replacement files locally. Do not run these until local tests pass and the changes are committed and pushed by the repository owner.

```bash
composer validate --strict
composer install
composer test
git status
```

After the changes are committed and pushed to `main`, and GitHub Actions passes:

```bash
git fetch origin --tags
git checkout main
git pull origin main
git tag -a v1.0.1 -m "Release v1.0.1"
git push origin v1.0.1
```

## GitHub Release

Title: `v1.0.1 - Workflow Execution Reliability and Validation`

Description:

- Made execution transitions transactional and row-locked to prevent stale workers from overwriting completed or failed states.
- Protected workflow activation and pause transitions against stale model instances.
- Added validation for workflow names, event names, step definitions, JSON-serializable settings and context, and context-size limits.
- Removed the undeclared mbstring runtime dependency from failure handling.
- Validated configured table identifiers.
- Expanded tests for lifecycle transitions, stale execution states, invalid definitions, and context validation.

Compatibility:
- PHP 8.3+
- Laravel 12 and 13

Validation:
- Run the complete local PHPUnit suite.
- Confirm the GitHub Actions PHP/Laravel matrix is green before tagging.

## Packagist

After publishing the GitHub release, confirm Packagist detects `v1.0.1` and verify installation with:

```bash
composer require itmosoft/laravel-marketing-automation:^1.0
```
