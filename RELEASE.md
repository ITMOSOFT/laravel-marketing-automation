# Release guide — v1.0.0

Repository: `ITMOSOFT/laravel-marketing-automation`

Composer package: `itmosoft/laravel-marketing-automation`

## Repository description

Event-triggered marketing workflows, execution tracking, and pluggable action handlers for Laravel applications.

## User-run Git commands

Run locally from the extracted package directory. Review staged files before committing.

```bash
git init
git branch -M main
git add .
git status
git commit -m "chore: initial Laravel marketing automation package"
git remote add origin https://github.com/ITMOSOFT/laravel-marketing-automation.git
git push -u origin main
```

After local tests and GitHub Actions pass:

```bash
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

## GitHub Release

Title: `v1.0.0 - Initial Release`

Description:

Initial release of ITMOSOFT Laravel Marketing Automation.

- Define event-triggered workflows with validated action steps.
- Activate and pause workflows.
- Match application events to active workflows.
- Persist queued workflow execution records and JSON context.
- Track queued, running, completed, and failed execution states.
- Pluggable action handler contract.
- Configurable database tables, models, step limits, and context size.
- Laravel 12/13 and PHP 8.3+ support.

Queue workers, action execution, delay/branch evaluation, and provider integrations remain application responsibilities in this release.

## Packagist

1. Open https://packagist.org/packages/submit
2. Submit `https://github.com/ITMOSOFT/laravel-marketing-automation`
3. Configure the Packagist GitHub webhook if prompted.
4. Confirm Packagist detects tag `v1.0.0`.
5. Verify installation with `composer require itmosoft/laravel-marketing-automation`.

Do not publish until local tests and the GitHub Actions matrix pass.
