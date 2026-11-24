# Changelog

## 1.0.1 - 2026-11-24

- Made execution state transitions transactional and row-locked to prevent stale workers from overwriting newer states.
- Applied the same transactional state protection to workflow activation and pause operations.
- Validated workflow names, trigger event lengths, JSON serializability, step definitions, and context size.
- Removed the undeclared mbstring runtime dependency from failure-message truncation.
- Validated configured database table identifiers.
- Expanded feature coverage for invalid transitions, stale execution instances, context limits, and configuration.

## 1.0.0 - 2026-11-17

- Initial release of event-triggered workflow definitions, activation controls, queued execution records, and pluggable action handler contracts.
