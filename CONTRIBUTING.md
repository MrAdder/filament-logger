# Contributing

Thanks for your interest in contributing to Filament Logger.

This project is a maintained fork of the original package by Z3d0X, and contributions are welcome across code, docs, tests, design, and issue triage.

Please read this guide before opening a pull request.

## Ground rules

- Be respectful and constructive in all interactions.
- Follow the guidance in [CODE_OF_CONDUCT.md](./CODE_OF_CONDUCT.md).
- Keep `1.x` changes backward-compatible unless maintainers explicitly decide otherwise.
- Prefer small, focused pull requests over large mixed changes.

## Before you start

- Search existing issues before opening a new one.
- Use the issue form for bugs, features, documentation, or maintenance work.
- Review [ROADMAP.md](./ROADMAP.md) and [TODO.md](./TODO.md) if you want to work on planned `1.x` improvements.
- If you want to work on an existing issue, leave a comment so effort does not overlap unnecessarily.

## Security issues

Please do not open public issues for sensitive security problems.

Use the project's security reporting channel instead.

## Local setup

Install dependencies:

```bash
composer install
npm install
```

Useful commands:

```bash
composer validate --strict
composer lint      # vendor/bin/pint --test
composer format    # vendor/bin/pint
composer analyse   # vendor/bin/phpstan analyse
composer test      # vendor/bin/pest
composer check     # lint + analyse + test
npm run docs:build
```

Code style is enforced with [Laravel Pint](https://laravel.com/docs/pint) using the config in `pint.json`. Run `composer format` before pushing — CI runs `pint --test` and will fail on unformatted code.

Pint is pinned to an **exact** version in `composer.json`. This package does not commit a `composer.lock`, so an unpinned Pint would let CI resolve a newer release than you have locally and fail on code you never touched — a Pint minor release can add rules to the Laravel preset. Dependabot raises the upgrade as its own PR, where any resulting reformatting is reviewed deliberately. If you bump Pint, run `composer format` in the same PR.

Static analysis runs at PHPStan level 6. The Filament 3 compatibility shims (`ActivityResourceV3`, `ListActivitiesV3`) use an API that no longer exists in Filament 4 and 5, so they are analysed separately:

```bash
composer require "filament/filament:3.*" --with-all-dependencies
vendor/bin/phpstan analyse -c phpstan-filament3.neon.dist
```

`phpstan-baseline.neon` is reserved for documented tool false positives. Do not add real findings to it.

### Code quality reporting

The quality gate badge comes from SonarQube Cloud. Configuration lives in `sonar-project.properties`, and the coverage job in `run-tests.yml` publishes the analysis.

Two things must be true for coverage to appear on the dashboard:

1. A `SONAR_TOKEN` repository secret exists. Without it the publish step logs and skips rather than failing, and it is always skipped for pull requests from forks.
2. **Automatic Analysis is turned off** in the SonarQube Cloud project settings, under *Administration → Analysis Method*. Automatic Analysis cannot import coverage reports, and leaving it enabled makes the CI scan fail with `You are running manual analysis while Automatic Analysis is enabled`.

## Pull request expectations

When opening a pull request:

- keep the scope focused
- include or update tests when behavior changes
- update docs when user-facing behavior or configuration changes
- make sure the relevant quality checks pass locally
- write a clear title and description that explain the change

If your change affects package behavior, please also note any upgrade or migration impact in the PR description.

## Coding guidelines

- Follow the existing project structure and naming conventions.
- Prefer clear, maintainable code over clever abstractions.
- Keep compatibility across supported Filament and Laravel versions in mind.
- Avoid introducing breaking changes during the `1.x` series unless they are explicitly approved.

## Documentation changes

Documentation contributions are welcome.

If you update docs:

- keep examples accurate and copyable
- run `npm run docs:build`
- make sure links and navigation still work as expected

## Release notes and roadmap

Release notes are managed by maintainers during the release process, but PRs should still provide enough context to write clear changelog entries later.

If your work changes roadmap direction or adds a meaningful new capability, mention that in the PR so maintainers can keep [ROADMAP.md](./ROADMAP.md) current.

## Questions

If you are unsure whether something is worth working on, open an issue first. That is usually the fastest way to align on scope and direction.
