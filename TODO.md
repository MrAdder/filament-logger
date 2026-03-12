# TODO

This backlog is focused on improvements that can land during the `1.x` series without requiring a breaking release.

## Activity review UX

- [ ] Add full-text activity search across description, subject, causer, and tags.
- [ ] Add more built-in saved filter presets for common review workflows.
- [ ] Add drill-down links from dashboard widgets into pre-filtered activity views.
- [ ] Improve diff rendering for large JSON payloads and deeply nested changes.

## Alerts and integrations

- [ ] Add a generic webhook alert channel for services beyond mail, Slack, and Discord.
- [ ] Add digest or summary alerts to reduce noise from repeated events.
- [ ] Add richer alert message customization with per-rule titles and message templates.
- [ ] Add more built-in risk heuristics for permission changes, role changes, and auth anomalies.

## Exports and scale

- [ ] Add queued exports for large activity datasets.
- [ ] Add reusable export presets and saved column sets.
- [ ] Improve export metadata so generated files include filter and date-range context.
- [ ] Expand pruning feedback with clearer dry-run summaries and reporting.

## Extensibility

- [ ] Add public hooks for customizing subject labels, causer labels, and activity row display.
- [ ] Add clearer extension points for custom resource tabs, widgets, and filters.
- [ ] Add more configuration hooks for alert rule registration and risk classification.

## Documentation and ecosystem

- [ ] Publish a dedicated support matrix for PHP, Laravel, and Filament compatibility in `1.x`.
- [ ] Add end-to-end examples for multi-panel, tenancy, alerts, and custom event setups.
- [ ] Review translation completeness and consistency across locale files.
- [ ] Add issue templates for bugs, feature requests, and release follow-ups.

## Quality and maintainability

- [ ] Expand test coverage around filters, widgets, exports, and dashboard behavior.
- [ ] Reduce remaining duplication warnings around the resource layer.
- [ ] Keep local tooling aligned with CI behavior for PHPStan and release checks.
- [ ] Start a lightweight `1.x` roadmap to track enhancements, deprecations, and upgrade notes ahead of `v2.0.0`.
