# Roadmap

This roadmap outlines the planned direction for Filament Logger after `v1.0.0`.

It is focused on improvements that can land during the `1.x` series without requiring a breaking release. Specific items and release boundaries may shift as feedback comes in, but the overall intent is to keep `1.x` additive, stable, and predictable.

## Roadmap goals

- Improve the day-to-day audit review experience.
- Expand operational value through exports, alerts, and integrations.
- Strengthen extensibility without destabilizing the public API.
- Keep documentation, testing, and release quality aligned with a stable `1.x` package.

## Versioning promise

The `1.x` series is intended for backward-compatible improvements.

- New features and integrations may be added in minor releases.
- Fixes and maintenance updates may be added in patch releases.
- Breaking changes are expected to be reserved for `v2.0.0`.

## Planned milestones

### v1.1.0 Foundation and confidence

This release focuses on trust, polish, and a stronger contributor experience around the stable `1.x` line.

- Publish a dedicated support matrix for supported PHP, Laravel, and Filament versions.
- Add issue templates for bug reports, feature requests, and release follow-ups.
- Keep local tooling aligned with CI behavior for PHPStan and release checks.
- Expand test coverage around filters, widgets, exports, and dashboard behavior.
- Reduce remaining duplication warnings around the resource layer.

### v1.2.0 Activity review UX

This release improves the core review experience inside the activity resource.

- Add more built-in saved filter presets for common review workflows.
- Add drill-down links from dashboard widgets into pre-filtered activity views.
- Improve diff rendering for large JSON payloads and deeply nested changes.

### v1.3.0 Search and review productivity

This release focuses on helping teams find the right audit trail faster.

- Add full-text activity search across description, subject, causer, and tags.
- Continue polishing review workflows around filtering and discoverability.

### v1.4.0 Exports and operations

This release expands reporting and operational support for audit workflows.

- Improve export metadata so generated files include filter and date-range context.
- Add reusable export presets and saved column sets.
- Expand pruning feedback with clearer dry-run summaries and reporting.

### v1.5.0 Alerts and integrations

This release builds on the alerting foundation with more flexible delivery and tuning.

- Add a generic webhook alert channel for services beyond mail, Slack, and Discord.
- Add richer alert message customization with per-rule titles and message templates.
- Add more built-in risk heuristics for permission changes, role changes, and auth anomalies.
- Add digest or summary alerts to reduce noise from repeated events.

### v1.6.0 Extensibility

This release focuses on giving developers more safe ways to tailor Filament Logger without forking it.

- Add public hooks for customizing subject labels, causer labels, and activity row display.
- Add clearer extension points for custom resource tabs, widgets, and filters.
- Add more configuration hooks for alert rule registration and risk classification.

### v1.7.x Scale and polish

This stage focuses on larger-scale usage, documentation depth, and preparing a healthy path toward `v2.0.0`.

- Add queued exports for large activity datasets.
- Add end-to-end examples for multi-panel, tenancy, alerts, and custom event setups.
- Review translation completeness and consistency across locale files.
- Track enhancements, deprecations, and upgrade notes throughout the `1.x` series.

## Out of scope for 1.x

The following kinds of changes are better reserved for `v2.0.0` unless a strong compatibility path is found:

- Breaking configuration changes.
- Renaming or removing established public classes, methods, or extension points.
- Behavioral changes that would require existing consumers to rewrite integrations or panel setup.
