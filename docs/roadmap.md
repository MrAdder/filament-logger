# Roadmap

This page outlines the planned direction for Filament Logger after `v1.0.0`.

The goal for the `1.x` series is to keep improvements additive and stable while expanding the package's value for audit review, alerting, exports, and extensibility.

> Plans can evolve as feedback comes in, but breaking changes are intended to be reserved for `v2.0.0`.

## Roadmap themes

- Improve the day-to-day audit review experience.
- Expand operational value through exports, alerts, and integrations.
- Strengthen extensibility without destabilizing the public API.
- Keep documentation, testing, and release quality aligned with a stable `1.x` package.

## Planned milestones

| Milestone | Theme | Focus |
| --- | --- | --- |
| `v1.1.0` | Foundation and confidence | Support matrix, issue templates, test coverage, and tooling alignment |
| `v1.2.0` | Activity review UX | Better presets, drill-down workflows, and improved diff rendering |
| `v1.3.0` | Search and productivity | Full-text activity search and smoother review workflows |
| `v1.4.0` | Exports and operations | Export metadata, reusable export presets, and better pruning feedback |
| `v1.5.0` | Alerts and integrations | Generic webhooks, message customization, richer risk heuristics, and digest alerts |
| `v1.6.0` | Extensibility | Safer hooks for labels, row display, tabs, widgets, filters, and alert registration |
| `v1.7.x` | Scale and polish | Queued exports, deeper examples, translation review, and `1.x` deprecation tracking |

## Near term

### v1.1.0 Foundation and confidence

This phase focuses on trust, polish, and a stronger contributor experience around the stable `1.x` line.

- Publish a dedicated support matrix for supported PHP, Laravel, and Filament versions.
- Add issue templates for bug reports, feature requests, and release follow-ups.
- Keep local tooling aligned with CI behavior for PHPStan and release checks.
- Expand test coverage around filters, widgets, exports, and dashboard behavior.
- Reduce remaining duplication warnings around the resource layer.

### v1.2.0 Activity review UX

This phase improves the core review experience inside the activity resource.

- Add more built-in saved filter presets for common review workflows.
- Add drill-down links from dashboard widgets into pre-filtered activity views.
- Improve diff rendering for large JSON payloads and deeply nested changes.

## Mid-term

### v1.3.0 Search and review productivity

- Add full-text activity search across description, subject, causer, and tags.
- Continue polishing review workflows around filtering and discoverability.

### v1.4.0 Exports and operations

- Improve export metadata so generated files include filter and date-range context.
- Add reusable export presets and saved column sets.
- Expand pruning feedback with clearer dry-run summaries and reporting.

### v1.5.0 Alerts and integrations

- Add a generic webhook alert channel for services beyond mail, Slack, and Discord.
- Add richer alert message customization with per-rule titles and message templates.
- Add more built-in risk heuristics for permission changes, role changes, and auth anomalies.
- Add digest or summary alerts to reduce noise from repeated events.

## Longer term

### v1.6.0 Extensibility

- Add public hooks for customizing subject labels, causer labels, and activity row display.
- Add clearer extension points for custom resource tabs, widgets, and filters.
- Add more configuration hooks for alert rule registration and risk classification.

### v1.7.x Scale and polish

- Add queued exports for large activity datasets.
- Add end-to-end examples for multi-panel, tenancy, alerts, and custom event setups.
- Review translation completeness and consistency across locale files.
- Track enhancements, deprecations, and upgrade notes throughout the `1.x` series.

## Live backlog

For the latest task-level backlog and implementation work, use the GitHub issue tracker:

- [View open issues](https://github.com/MrAdder/filament-logger/issues)

## Out of scope for 1.x

These kinds of changes are better reserved for `v2.0.0` unless a strong compatibility path is found:

- Breaking configuration changes.
- Renaming or removing established public classes, methods, or extension points.
- Behavioral changes that would require existing consumers to rewrite integrations or panel setup.
