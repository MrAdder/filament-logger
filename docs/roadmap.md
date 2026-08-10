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

| Milestone | Theme | Status |
| --- | --- | --- |
| `v1.1.0` | Foundation and confidence | Delivered |
| `v1.4.0` | Exports and operations | Delivered |
| `v1.5.0` | Alerts and integrations | Delivered |
| `v1.6.0` | Extensibility | Delivered |
| `v1.2.0` | Activity review UX | Partly delivered |
| `v1.7.x` | Scale and polish | Partly delivered |
| `v1.3.0` | Search and productivity | Planned |

## Delivered

### Foundation and confidence

- Support matrix for PHP, Laravel, and Filament versions.
- Issue templates for bug reports, feature requests, and maintenance.
- Laravel Pint with a CI style gate, and PHPStan raised to level 6.
- A dedicated PHPStan run for the Filament 3 compatibility shims.
- Expanded coverage across filters, widgets, exports, alerts, and the dashboard.

### Exports and operations

- Export metadata carrying filter and date-range context.
- Reusable export presets and saved column sets, in config or the database.
- Queued exports for large datasets, with notification and retention.
- A dedicated export ability, and CSV formula neutralisation.

### Alerts and integrations

- A generic webhook channel alongside mail, Slack, and Discord.
- Per-rule title and message templates.
- Digest alerts, released by a scheduled command or opportunistically.
- Additional risk heuristics for permission, credential, two-factor, and account status changes.
- Queued alert delivery so webhooks cannot block the audited action.

### Extensibility

- Display hooks for subject labels, causer labels, table columns, and detail entries.
- Extension points for review tabs, dashboard widgets, and filters.
- Programmatic alert rule registration.
- End-to-end examples for multi-panel, tenancy, alerts, and custom events.

## In progress

### Activity review UX

- [x] Drill-down links from dashboard widgets into pre-filtered activity views.
- [ ] More built-in saved filter presets for common review workflows.
- [ ] Improved diff rendering for large JSON payloads and deeply nested changes.

### Scale and polish

- [x] Queued exports for large activity datasets.
- [x] End-to-end examples for multi-panel, tenancy, alerts, and custom event setups.
- [x] Caching for the filter option scans, and an optional activity log index migration.
- [ ] Translation review across the non-English locale files.
- [ ] Benchmarks against a multi-million row activity table.

## Planned

### Search and productivity

- Full-text activity search across description, subject, causer, and tags.
- A generated or indexed `risk` column so risk filtering avoids a JSON scan.
- Clearer dry-run summaries and reporting for pruning.

## Live backlog

For the latest task-level backlog and implementation work, use the GitHub issue tracker:

- [View open issues](https://github.com/MrAdder/filament-logger/issues)

## Out of scope for 1.x

These kinds of changes are better reserved for `v2.0.0` unless a strong compatibility path is found:

- Breaking configuration changes.
- Renaming or removing established public classes, methods, or extension points.
- Behavioral changes that would require existing consumers to rewrite integrations or panel setup.
