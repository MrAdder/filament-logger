# Changelog

All notable changes to `filament-logger` will be documented in this file.

## v1.1.1 - 2026-03-19

## Summary

- fix: register activity dashboard widgets as Livewire components by @MrAdder in #36

## Maintenance

- chore(deps): bump actions/cache from 4 to 5 by @[dependabot[bot]](https://github.com/apps/dependabot) in #34
- chore(deps): bump release-drafter/release-drafter from 67e173cadb2fbd3de94f4a861e0c48c913b462ae to 6a93d829887aa2e0748befe2e808c66c0ec6e4c7 by @[dependabot[bot]](https://github.com/apps/dependabot) in #33
- chore(deps): bump actions/upload-pages-artifact from 3 to 4 by @[dependabot[bot]](https://github.com/apps/dependabot) in #32
- chore(deps): bump actions/setup-node from 4 to 6 by @[dependabot[bot]](https://github.com/apps/dependabot) in #31
- chore(deps): bump ramsey/composer-install from 3.2.0 to 4.0.0 by @[dependabot[bot]](https://github.com/apps/dependabot) in #30

## Contributors

@MrAdder, @dependabot[bot] and [dependabot[bot]](https://github.com/apps/dependabot)

**Full Changelog**: https://github.com/MrAdder/filament-logger/compare/v1.1.0...v1.1.1

## v1.0.1 - 2026-03-12

## Summary

- docs: add community guidelines, roadmap docs, and issue planning artifacts by @MrAdder in #23

## Contributors

@MrAdder

**Full Changelog**: https://github.com/MrAdder/filament-logger/compare/v1.0.0...v1.0.1

## v1.1.0 - 2026-03-12

### Highlights

- Raises the minimum supported Filament 4 version to `4.3.1` because earlier `4.x` releases were affected by an upstream security issue fixed in `4.3.1`.
- Updates package constraints, CI coverage, and installation docs to reflect the new Filament 4 support floor.

## v1.0.0 - 2026-03-12

Filament Logger reaches its first stable release.

This milestone marks the package as ready for long-term use across the supported Laravel and Filament versions, with the public package surface now treated as stable for the `1.x` series.

### Highlights

- Declares the package's public API, configuration surface, and extension points as stable going forward.
- Future breaking changes will be reserved for `v2.0.0` rather than introduced in `1.x`.
- Refactors the shared activity resource internals to reduce duplication and simplify ongoing maintenance.
- Deduplicates repeated activity log labels in the Georgian, Korean, and Vietnamese locale files.

**Full Changelog**: https://github.com/MrAdder/filament-logger/compare/v0.11.3...v1.0.0

## v0.11.3 - 2026-03-12

### Summary

- No user-facing changes in this release.

### Contributors

@MrAdder

**Full Changelog**: https://github.com/MrAdder/filament-logger/compare/v0.11.2...v0.11.3

## v0.11.2 - 2026-03-12

### Summary

- No user-facing changes in this release.

### Contributors

@MrAdder

**Full Changelog**: https://github.com/MrAdder/filament-logger/compare/v0.11.1...v0.11.2

## v0.11.1 - 2026-03-12

### Summary

- No user-facing changes in this release.

### Contributors

No contributors

**Full Changelog**: https://github.com/MrAdder/filament-logger/compare/v0.11.0...v0.11.1

## v0.11.0 - 2026-03-12

### Summary

- Added alert cooldowns so repeated sensitive activity does not keep notifying on every matching event.
- Added the VitePress documentation site and tightened release archives to exclude non-runtime files.
- Refreshed release and workflow setup for the maintained fork.

### Highlights

- Added configurable cooldown windows for alert rules, including threshold-based failed-login alerts.
- Added dedicated package docs for installation, configuration, activity review, security, custom events, and releasing.
- Updated packaging so docs and other development-only files are excluded from release archives.

### Contributors

@MrAdder

**Full Changelog**: https://github.com/MrAdder/filament-logger/compare/v0.10.0...v0.11.0

## v0.10.0 - 2026-03-12

### Summary

- Added exports, alerts, dashboards, and custom audit events.
- Refreshed docs and workflows to support the expanded feature set.

### Highlights

- Added CSV and JSON exports for filtered audit activity.
- Added alert dispatching for sensitive and threshold-based activity.
- Added dashboard widgets for activity overview, trends, top users, top events, and high-risk actions.
- Added a custom event API for domain-specific audit logging without building a separate logger.

### Contributors

@MrAdder

**Full Changelog**: https://github.com/MrAdder/filament-logger/compare/v0.9.0...v0.10.0

## v0.9.0 - 2026-03-12

This release expands Filament Logger’s audit coverage, hardens default security behavior, improves the activity UI, and refreshes the package documentation.

### Highlights

- Added retention and pruning support with a new `filament-logger:prune` command.
- Expanded auth logging to cover login, logout, failed login, lockout, password reset, and Fortify recovery-code usage when available.
- Added ignored-field controls globally, per model, and per resource to reduce noisy or sensitive change logs.
- Improved model lifecycle logging for restore, force-delete, replicate, and bulk-action flows.
- Upgraded the activity detail view with a clearer side-by-side diff, pretty-printed JSON values, and collapsing for large payloads.
- Hardened logging defaults to better protect sensitive data and reduce accidental exposure.
- Rewrote the README and removed the temporary banner while a new one is being prepared.

### Security Improvements

- Activity resource access is now strict by default and requires explicit policy support.
- Sensitive values such as passwords, tokens, secrets, and recovery codes are sanitized before being stored or displayed.
- Access logs now anonymize IP addresses and limit stored user-agent size.
- Notification recipient logging is disabled by default, with optional masking when enabled.
- Existing historical records are not automatically rewritten, so older stored descriptions may still contain unsanitized values until scrubbed separately.

### Audit Logging Improvements

- Added structured old/new attribute logging for updates.
- Added support for restore and force-delete lifecycle events without duplicate noise.
- Added replicate logging with source metadata.
- Added broader support for Filament bulk operations through lifecycle event handling.
- Added per-model and per-resource ignore lists for fields like `updated_at`, counters, and session-related values.

### UI Improvements

- Added a richer activity diff viewer in the resource detail page.
- Large payloads are now collapsible for easier inspection.
- JSON-like values are formatted for readability.

### Developer Experience

- Added pruning configuration options by age and log name.
- Improved test coverage for authorization, auth events, model lifecycle logging, pruning, and sanitization.
- Fixed lifecycle snapshot handling so old values are captured reliably across supported Laravel versions.
- Fixed resource observer registration so per-resource logger configuration is preserved correctly.
- Filtered noisy PHP 8.4 PDO deprecation output during tests.

### Documentation

- README was overhauled to reflect the new logging, pruning, security, and configuration options.
- The temporary README banner has been removed until it is redesigned.

### Upgrade Notes

- Add the `filament-logger:prune` command to your application scheduler if you want automatic cleanup.
- If you rely on viewing the activity resource, make sure an `Activity` policy is registered or disable strict authorization explicitly.
- Review the new config defaults for notification recipient logging and auth-event coverage.
- Historical activity entries are not retroactively sanitized.

## v0.8.2 - 2026-03-02

**Full Changelog**: https://github.com/MrAdder/filament-logger/compare/v0.8.1...v0.8.2

## v0.8.1 - 2026-03-02

**Full Changelog**: https://github.com/MrAdder/filament-logger/commits/v0.8.1

## v0.8.0 - 2025-03-04

### What's Changed

* Bump dependabot/fetch-metadata from 2.2.0 to 2.3.0 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/130
* Add Armenian Translation by @arshaviras in https://github.com/Z3d0X/filament-logger/pull/134
* Laravel 12 compatilibity by @siegerhansma in https://github.com/Z3d0X/filament-logger/pull/135

### New Contributors

* @arshaviras made their first contribution in https://github.com/Z3d0X/filament-logger/pull/134
* @siegerhansma made their first contribution in https://github.com/Z3d0X/filament-logger/pull/135

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.7.3...v0.8.0

## v0.7.3 - 2025-01-08

### What's Changed

* Slovak translation by @hamrak in https://github.com/Z3d0X/filament-logger/pull/106
* Bump dependabot/fetch-metadata from 2.1.0 to 2.2.0 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/118
* Czech translation by @robertjunek in https://github.com/Z3d0X/filament-logger/pull/125
* Adding resource cluster and navigation from config file by @abdosaeedelhassan in https://github.com/Z3d0X/filament-logger/pull/123
* Ability to control the ActivityResource navigation sort and if scoped to tenant by @silviugd in https://github.com/Z3d0X/filament-logger/pull/117

### New Contributors

* @hamrak made their first contribution in https://github.com/Z3d0X/filament-logger/pull/106
* @robertjunek made their first contribution in https://github.com/Z3d0X/filament-logger/pull/125
* @abdosaeedelhassan made their first contribution in https://github.com/Z3d0X/filament-logger/pull/123
* @silviugd made their first contribution in https://github.com/Z3d0X/filament-logger/pull/117

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.7.2...v0.7.3

## v0.7.2 - 2024-06-09

### What's Changed

* Refactor: remove extended EventServiceProvider by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/99
* Bump dependabot/fetch-metadata from 2.0.0 to 2.1.0 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/102
* Add timezone to created_at row by @emildayan in https://github.com/Z3d0X/filament-logger/pull/110

### New Contributors

* @emildayan made their first contribution in https://github.com/Z3d0X/filament-logger/pull/110

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.7.1...v0.7.2

## v0.7.1 - 2024-04-07

### What's Changed

* Bump dependabot/fetch-metadata from 1.6.0 to 2.0.0 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/94
* [lang] Add Dutch translations by @Daniel-H123 in https://github.com/Z3d0X/filament-logger/pull/96
* Korean translations add by @corean in https://github.com/Z3d0X/filament-logger/pull/95

### New Contributors

* @Daniel-H123 made their first contribution in https://github.com/Z3d0X/filament-logger/pull/96
* @corean made their first contribution in https://github.com/Z3d0X/filament-logger/pull/95

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.7.0...v0.7.1

## v0.7.0 - 2024-03-12

### Laravel 11.x compatibility added

#### What's Changed

* Bump ramsey/composer-install from 2 to 3 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/90
* docs: missing translation for pt_BR by @marcosmarcolin in https://github.com/Z3d0X/filament-logger/pull/88
* Laravel 11.x Compatibility by @laravel-shift in https://github.com/Z3d0X/filament-logger/pull/89

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.6.3...v0.7.0

## v0.6.2 - 2023-11-29

### What's Changed

* Bump actions/checkout from 3 to 4 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/79
* Bump stefanzweifel/git-auto-commit-action from 4 to 5 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/81
* Fix language typo by @lakuapik in https://github.com/Z3d0X/filament-logger/pull/83

### New Contributors

* @lakuapik made their first contribution in https://github.com/Z3d0X/filament-logger/pull/83

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.6.1...v0.6.2

## v0.6.1 - 2023-09-04

### What's Changed

- Fixed nav icon in locales by @Z3d0X  in https://github.com/Z3d0X/filament-logger/commit/fd1641ac4f86742be01beda8f10d8582adefc03c

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.6.0...v0.6.1

## v0.6.0 - 2023-08-13

### What's Changed

- Filament v3 support by @thapaPrabhat in https://github.com/Z3d0X/filament-logger/pull/74

### New Contributors

- @thapaPrabhat made their first contribution in https://github.com/Z3d0X/filament-logger/pull/74

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.5.6...v0.6.0

## v0.5.6 - 2023-07-11

### What's Changed

- Fix #71  by @Z3d0X  in 4a3dda731c8a04429cb4d31b935ecbc68f9688e1

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.5.5...v0.5.6

## v0.5.5 - 2023-07-08

### What's Changed

- Bump dependabot/fetch-metadata from 1.5.1 to 1.6.0 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/68
- Fix: prevent hidden attributes from being logged by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/70

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.5.4...v0.5.5

## v0.5.4 - 2023-06-10

### What's Changed

- Bump dependabot/fetch-metadata from 1.3.6 to 1.4.0 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/49
- add Persian language by @AmirAghaee in https://github.com/Z3d0X/filament-logger/pull/50
- Fixes typo in README file. by @fsamapoor in https://github.com/Z3d0X/filament-logger/pull/53
- Adds Arabic translations. by @fsamapoor in https://github.com/Z3d0X/filament-logger/pull/55
- Adds localization improvements to the table filters. by @fsamapoor in https://github.com/Z3d0X/filament-logger/pull/54
- Bump dependabot/fetch-metadata from 1.4.0 to 1.5.1 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/62
- Adds missing FA translations. by @fsamapoor in https://github.com/Z3d0X/filament-logger/pull/58
- Georgian Translation by @ngfw in https://github.com/Z3d0X/filament-logger/pull/63
- Adds Indonesian translation by @ruswan in https://github.com/Z3d0X/filament-logger/pull/60
- fix: view activity mobile responsiveness by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/64

### New Contributors

- @AmirAghaee made their first contribution in https://github.com/Z3d0X/filament-logger/pull/50
- @fsamapoor made their first contribution in https://github.com/Z3d0X/filament-logger/pull/53
- @ruswan made their first contribution in https://github.com/Z3d0X/filament-logger/pull/60

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.5.3...v0.5.4

## v0.5.3 - 2023-04-16

### What's Changed

- add tr language by @mnurullahsaglam in https://github.com/Z3d0X/filament-logger/pull/47

### New Contributors

- @mnurullahsaglam made their first contribution in https://github.com/Z3d0X/filament-logger/pull/47

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.5.2...v0.5.3

## v0.5.2 - 2023-04-05

### What's Changed

- Automatic Activity Model resolution by @marcoguido in https://github.com/Z3d0X/filament-logger/pull/45

### New Contributors

- @marcoguido made their first contribution in https://github.com/Z3d0X/filament-logger/pull/45

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.5.1...v0.5.2

## v0.5.1 - 2023-03-13

### What's Changed

- Fix: old/new value filter label translation by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/42

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.5.0...v0.5.1

## v0.5.0 - 2023-02-15

### What's Changed

- Bump dependabot/fetch-metadata from 1.3.5 to 1.3.6 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/34
- Laravel 10 support by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/38

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.4.2...v0.5.0

## v0.4.2 - 2022-12-22

### What's Changed

- Bump ramsey/composer-install from 1 to 2 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/29
- Fix: `old` & `new` properties keys always displayed by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/33
- fix: translate for Settings by @castellani8 in https://github.com/Z3d0X/filament-logger/pull/30

### New Contributors

- @castellani8 made their first contribution in https://github.com/Z3d0X/filament-logger/pull/30

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.4.1...v0.4.2

## v0.4.1 - 2022-11-13

### What's Changed

- Bump dependabot/fetch-metadata from 1.3.3 to 1.3.4 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/22
- Bump dependabot/fetch-metadata from 1.3.4 to 1.3.5 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/25
- Feature: Install Command by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/26

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.4.0...v0.4.1

## v0.4.0 - 2022-09-27

### What's Changed

- Make date formatting configurable by @cweagans in https://github.com/Z3d0X/filament-logger/pull/19
- Proper display of Model Attributes diff by @thyseus in https://github.com/Z3d0X/filament-logger/pull/21

### New Contributors

- @cweagans made their first contribution in https://github.com/Z3d0X/filament-logger/pull/19
- @thyseus made their first contribution in https://github.com/Z3d0X/filament-logger/pull/21

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.3.5...v0.4.0

## v0.3.5 - 2022-09-16

### What's Changed

- Fix: `NotificationLogger` getRecipient by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/20

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.3.4...v0.3.5

## v0.3.4 - 2022-08-26

### What's Changed

- ru & ukr translate by @HomaEEE in https://github.com/Z3d0X/filament-logger/pull/17
- Fix: PHPStan & TestCase by @Z3d0X in https://github.com/Z3d0X/filament-logger/commit/0865a33c31c1735913390ef7b0d5f17c0017a000 & https://github.com/Z3d0X/filament-logger/commit/2ffd846b769b38944a23d307d77d792ba7454fe9

### New Contributors

- @HomaEEE made their first contribution in https://github.com/Z3d0X/filament-logger/pull/17

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.3.3...v0.3.4

## v0.3.3 - 2022-08-13

### What's Changed

- Vietnamese translations by @datlechin in https://github.com/Z3d0X/filament-logger/pull/16

### New Contributors

- @datlechin made their first contribution in https://github.com/Z3d0X/filament-logger/pull/16

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.3.2...v0.3.3

## v0.3.2 - 2022-08-04

### What's Changed

- Brazilian portuguse translation by @gapfranco in https://github.com/Z3d0X/filament-logger/pull/15

### New Contributors

- @gapfranco made their first contribution in https://github.com/Z3d0X/filament-logger/pull/15

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.3.1...v0.3.2

## v0.3.1 - 2022-07-19

### What's Changed

- [FR] Create filament-logger.php by @nicolasbaud in https://github.com/Z3d0X/filament-logger/pull/14

### New Contributors

- @nicolasbaud made their first contribution in https://github.com/Z3d0X/filament-logger/pull/14

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.3.0...v0.3.1

## v0.3.0 - 2022-07-16

### What's Changed

- Bump dependabot/fetch-metadata from 1.3.1 to 1.3.3 by @dependabot in https://github.com/Z3d0X/filament-logger/pull/12
- Added keys for missing translation labels and Spanish translations by @pathros in https://github.com/Z3d0X/filament-logger/pull/13

### New Contributors

- @dependabot made their first contribution in https://github.com/Z3d0X/filament-logger/pull/12
- @pathros made their first contribution in https://github.com/Z3d0X/filament-logger/pull/13

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.2.3...v0.3.0

## v0.2.3 - 2022-06-16

### What's Changed

- fix getUserName GenericUser by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/11

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.2.2...v0.2.3

## v0.2.2 - 2022-05-19

## What's Changed

- Feature: Customizable Labels by @cntabana in https://github.com/Z3d0X/filament-logger/pull/7

## New Contributors

- @cntabana made their first contribution in https://github.com/Z3d0X/filament-logger/pull/7

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.2.1...v0.2.2

## v0.2.1 - 2022-05-08

## What's Changed

- Fix: remove str() helper usage by @ngfw in https://github.com/Z3d0X/filament-logger/pull/6

## New Contributors

- @ngfw made their first contribution in https://github.com/Z3d0X/filament-logger/pull/6

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.2.0...v0.2.1

## v0.2.0 - 2022-05-01

## What's Changed

- Bugfix/fix notification logger by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/4
- Feature/model logger by @Z3d0X in https://github.com/Z3d0X/filament-logger/pull/5

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.1.1...v0.2.0

## v0.1.1 - 2022-04-28

## What's Changed

- Configurable ActivityResource by @robertorinaldi-dev in https://github.com/Z3d0X/filament-logger/pull/3

## New Contributors

- @robertorinaldi-dev made their first contribution in https://github.com/Z3d0X/filament-logger/pull/3

**Full Changelog**: https://github.com/Z3d0X/filament-logger/compare/v0.1.0...v0.1.1
