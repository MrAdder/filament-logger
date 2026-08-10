# TODO

This backlog is focused on improvements that can land during the `1.x` series without requiring a breaking release.

## Activity review UX

- [ ] Add full-text activity search across description, subject, causer, and tags.
- [ ] Add more built-in saved filter presets for common review workflows.
- [ ] Add drill-down links from dashboard widgets into pre-filtered activity views.
- [ ] Improve diff rendering for large JSON payloads and deeply nested changes.

## Alerts and integrations

- [x] Add a generic webhook alert channel for services beyond mail, Slack, and Discord.
- [x] Add digest or summary alerts to reduce noise from repeated events.
- [x] Add richer alert message customization with per-rule titles and message templates.
- [x] Add more built-in risk heuristics for permission changes, role changes, and auth anomalies.
- [x] Deliver alerts through the queue so webhooks cannot block the audited action.

## Exports and scale

- [x] Add queued exports for large activity datasets.
- [x] Add reusable export presets and saved column sets.
- [x] Improve export metadata so generated files include filter and date-range context.
- [ ] Expand pruning feedback with clearer dry-run summaries and reporting.
- [x] Gate exports behind their own ability rather than resource view access.
- [x] Neutralize spreadsheet formulas in CSV exports.

## Extensibility

- [x] Add public hooks for customizing activity descriptions (`FilamentLogger::describeUsing()`).
- [x] Add public hooks for customizing subject labels, causer labels, and activity row display.
- [x] Add clearer extension points for custom resource tabs, widgets, and filters.
- [x] Add more configuration hooks for alert rule registration and risk classification.

## Documentation and ecosystem

- [x] Publish a dedicated support matrix for PHP, Laravel, and Filament compatibility in `1.x`.
- [x] Add end-to-end examples for multi-panel, tenancy, alerts, and custom event setups.
- [ ] Translate the newer review UI strings into the non-English locale files. All strings are
      translatable and fall back to English; `TranslationsTest` guards against stale keys.
- [x] Add issue templates for bugs, feature requests, and release follow-ups.

## Quality and maintainability

- [x] Expand test coverage around filters, widgets, exports, and dashboard behavior.
- [ ] Reduce remaining duplication warnings around the resource layer.
- [x] Keep local tooling aligned with CI behavior for PHPStan and release checks.
- [x] Adopt Laravel Pint with a CI style gate.
- [x] Raise PHPStan to level 6 and analyse the Filament 3 shims in a dedicated job.
- [ ] Start a lightweight `1.x` roadmap to track enhancements, deprecations, and upgrade notes ahead of `v2.0.0`.

## Performance

- [x] Cache the distinct log name / subject type scans behind the table filters.
- [x] Ship an optional migration adding `created_at` indexes to the activity log table.
- [ ] Benchmark the activity resource against a multi-million row activity table.
- [ ] Consider a generated/indexed `risk` column so risk filtering avoids a JSON scan.
