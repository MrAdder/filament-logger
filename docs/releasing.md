# Releasing

## Release Flow

The repository now uses a two-step release workflow system:

1. `Release Drafter` keeps a draft GitHub release up to date as changes land on `main`.
2. `Publish Release` is run manually when you are ready to ship a version.

## Draft Release Notes

The draft release workflow is defined in `.github/workflows/release-drafter.yml` and uses `.github/release-drafter.yml` for formatting and version hints.

As pull requests merge into `main`, the draft release is refreshed automatically.

## Publishing a Release

Run the `Publish Release` workflow from the GitHub Actions tab.

Inputs:

- `version`: the semantic version tag to publish, such as `v0.9.1`
- `target`: the branch to release from, usually `main`
- `prerelease`: whether the release should be marked as a prerelease
- `latest`: whether GitHub should mark the release as the latest release

## What the Publish Workflow Does

The publish workflow:

- checks out the requested target
- validates the version format
- ensures the tag does not already exist
- validates Composer metadata
- installs dependencies
- runs Pest
- runs PHPStan
- publishes the GitHub release from the current draft notes
- finalizes the matching `## vX.Y.Z - Unreleased` changelog section when it exists
- otherwise inserts a generated release section near the top of `CHANGELOG.md`
- commits the changelog update back to the target branch

## Changelog Behavior

If you keep an unreleased section such as `## v1.1.0 - Unreleased` at the top of `CHANGELOG.md`, the publish workflow will rename that heading to the release date when you publish `v1.1.0`.

If no matching unreleased section exists, the workflow falls back to inserting the generated release notes from Release Drafter as a new released section near the top of the changelog.

## Recommended PR Labels

Release Drafter groups entries and resolves the next suggested version from pull request labels.

Useful labels include:

- `feature`
- `enhancement`
- `fix`
- `bug`
- `bugfix`
- `security`
- `documentation`
- `docs`
- `tests`
- `ci`
- `chore`
- `dependencies`
- `refactor`
- `major`
- `minor`
- `patch`
- `skip-changelog`

If no release-level label is applied, the draft defaults to a patch increment.
