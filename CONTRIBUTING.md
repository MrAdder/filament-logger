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
vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=512M
npm run docs:build
```

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
