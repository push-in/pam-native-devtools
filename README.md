# PAM Native DevTools

A deterministic, bounded diagnostic recorder for PAM Native development: application events, serializable state snapshots, performance marks, errors, and complete network transactions. Export one JSON artifact for bug reports or CI regressions without coupling production apps to a visual UI.

```bash
pam add devtools
pam doctor
```

```php
$tools->snapshot('session', $session);
$tools->mark('feed.start');
$request = $tools->network('GET', $url, $headers);
try { $request->complete(200, $responseHeaders, $body); }
catch (Throwable $e) { $request->fail($e->getMessage()); throw $e; }
$tools->measure('feed.load', 'feed.start');
```

Sensitive keys are redacted recursively, object depth and collection size are bounded, strings are truncated, and the oldest records are discarded under pressure. The package complements PAM's native debug overlay and navigation timeline; it does not ship UI into production applications.


## What installation does

`pam add devtools` resolves the official compatible package, performs a non-mutating Composer preflight, updates the normal `composer.json` and `composer.lock`, refreshes generated native integration when required, and leaves the project ready for `pam doctor` validation.

Use `pam packages` to inspect availability and `pam remove devtools` to uninstall the capability safely. Direct Composer commands are an advanced interoperability path; PAM is the supported application workflow.

## API guide

| API | Responsibility |
| --- | --- |
| `DevTools` | Record events, snapshots, marks, measures, errors, and network work. |
| `NetworkTransaction` | Complete or fail a captured request lifecycle. |
| `Redactor` | Bound and recursively redact diagnostic values. |
| `RecordKind` / `NetworkState` | Typed diagnostic record states. |

All coded states, kinds, and variants are sequential integer-backed enums. Use enum cases in application code; do not depend on raw wire numbers.

## Production checklist

- Keep DevTools in development and diagnostic builds.
- Add product-specific sensitive keys to redaction rules.
- Attach exported JSON to reproducible bug reports, never public issue bodies without review.
- Run `pam doctor`, `pam test`, and a signed release build on every supported platform.
- Exercise denial, cancellation, backgrounding, process restart, and offline behavior before release.

## Troubleshooting

- **Records disappear:** the buffer intentionally drops oldest entries under pressure.
- **Values are truncated:** increase detail only when the privacy and size trade-off is acceptable.
- **A request remains open:** always call `complete()` or `fail()`.
- **Native integration is stale:** run `pam doctor --fix`, rebuild the native host, and inspect the first reported diagnostic.

## Compatibility and support

This package targets PAM Native `0.6.x`, Android API 26+, and iOS 15+ unless a platform-specific section above states a stricter requirement. Platform SDKs, credentials, entitlements, physical hardware, and store configuration remain application responsibilities.

- [PAM documentation](https://push-in.github.io/pam-docs/introduction/)
- [PAM Native overview](https://push-in.github.io/pam-docs/native/overview/)
- [Plugin and native capability model](https://push-in.github.io/pam-docs/native/plugins/)
- [Report an issue](https://github.com/push-in/pam-native-devtools/issues)

Security vulnerabilities should be reported through the repository security policy or GitHub private vulnerability reporting, not a public issue.
