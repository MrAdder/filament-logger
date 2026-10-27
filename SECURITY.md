# Security Policy

## Supported Versions

Security fixes are applied to the latest `1.x` release. Older minor versions are
not backported — please upgrade to the current `1.x` before reporting.

| Version | Supported |
|---------|-----------|
| `1.x`   | ✅ |
| `< 1.0` | ❌ |

The package is supported on the following runtimes. A report against anything
outside this range will be assessed but may not receive a fix.

| Dependency | Supported versions |
|------------|--------------------|
| PHP        | `8.2` and above |
| Laravel    | `11.x`, `12.x`, `13.x` |
| Filament   | `3.x`, `4.3.1` and above, `5.x` |

Filament `4.0`–`4.3.0` are not supported because of an upstream advisory fixed
in [`4.3.1`](https://github.com/filamentphp/filament/security/advisories/GHSA-pvcv-q3q7-266g).

PHP itself must be on a patched release for CVE-2026-109618 and CVE-2026-109619
("PHP2Shell"): `8.2.35`, `8.3.36`, `8.4.27`, `8.5.12` or later, or a
distribution build carrying the backported fix. These are interpreter flaws
that can be triggered before any application code runs, so nothing in this
package can mitigate them. Restart PHP-FPM, Apache, FrankenPHP and queue
workers after upgrading, since running processes keep the old binary. Until a
fix for CVE-2026-115560 ships, set `session.upload_progress.enabled=0`; the
package does not rely on session upload progress, so disabling it is safe.
The `composer.json` constraint stays at `^8.2` so distribution builds with
backported fixes remain installable.

## Reporting a Vulnerability

**Please do not open a public issue for a security vulnerability.**

Report it privately through GitHub's
[security advisory form](https://github.com/MrAdder/filament-logger/security/advisories/new),
or by email to [me@mradder.com](mailto:me@mradder.com).

Please include:

- the package, Filament, Laravel, and PHP versions affected
- the relevant parts of your `config/filament-logger.php`
- steps to reproduce, and the impact you believe it has

### What to expect

- An acknowledgement within 5 working days.
- An assessment and a target fix window within 10 working days.
- Credit in the release notes and advisory, unless you prefer to stay anonymous.

Please give us a reasonable window to ship a fix before disclosing publicly.

## Scope Notes

This package writes an audit trail, so a few classes of report are worth
calling out explicitly.

**In scope:**

- Sensitive values (passwords, tokens, secrets) reaching the activity log
  despite the `sensitive_keys` redaction.
- A user reading activity, or exporting it, without passing the configured
  authorization checks.
- Activity properties leaking to a viewer who lacks the
  `authorization.sensitive_ability` ability.

**Out of scope:**

- Data exposure resulting from setting `authorization.strict` to `false`, or
  from a policy in the host application that grants access too broadly.
- Sensitive values logged through a custom logger that bypasses
  `LogDataSanitizer`.
- Anything requiring an already-privileged panel user with the export or
  preset-management abilities.

See the [security and authorization guide](https://mradder.github.io/filament-logger/security)
for how to configure these controls.
