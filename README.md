# PAM Native DevTools

## Start here

This is a Composer extension for PAM Native. Install the PAM Runtime, create a native project, and then add this package through PAM’s verified Composer toolchain:

```bash
curl --proto '=https' --proto-redir '=https' --tlsv1.2 \
    --connect-timeout 15 --max-time 60 --max-filesize 1048576 -fsSL \
    https://github.com/push-in/pam/releases/latest/download/install.sh | sh

pam init my-app --template native
cd my-app
pam composer require pushinbr/pam-native-devtools
pam doctor --fix
```


A deterministic, bounded diagnostic recorder for PAM Native development: application events, serializable state snapshots, performance marks, errors, and complete network transactions. Export one JSON artifact for bug reports or CI regressions without coupling production apps to a visual UI.

```bash
pam composer require pushinbr/pam-native-devtools
pam doctor --fix
```

```php
$tools->snapshot('session', $session);
$tools->mutation('session', $previousSession, $session);
$tools->frame('feed', ['refreshRateHz' => 120, 'frameMs' => 7.4, 'missed' => false]);
$tools->mark('feed.start');
$request = $tools->network('GET', $url, $headers);
try { $request->complete(200, $responseHeaders, $body); }
catch (Throwable $e) { $request->fail($e->getMessage()); throw $e; }
$tools->measure('feed.load', 'feed.start');

$artifact = $tools->exportJson();
$recording = TimelineRecording::fromJson($artifact);
$replay = new ReplaySession($recording);
$replay->replay(static function (array $record): void {
    // Feed the exact, ordered diagnostic event into a test or inspector.
});
```

Sensitive keys are redacted recursively, object depth and collection size are bounded, strings are truncated, and the oldest records are discarded under pressure. The package complements PAM's native debug overlay and navigation timeline; it does not ship UI into production applications.


## What installation does

`pam composer require pushinbr/pam-native-devtools` installs the package through the project's normal `composer.json` and `composer.lock`. Run `pam doctor --fix` afterward to validate the environment and regenerate native integration when required.

Use `pam packages` to inspect direct installed Composer dependencies and `pam composer remove pushinbr/pam-native-devtools` to uninstall the capability.

## API guide

| API | Responsibility |
| --- | --- |
| `DevTools` | Record events, mutations, snapshots, frames, marks, measures, errors, and network work. |
| `NetworkTransaction` | Complete or fail a captured request lifecycle. |
| `Redactor` | Bound and recursively redact diagnostic values. |
| `TimelineRecording` | Validate and serialize the bounded, versioned replay artifact. |
| `ReplaySession` | Step, seek, time travel to state, or replay the timeline deterministically. |
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

This package targets PAM Native `0.8.x`, PHP 8.5, Android API 26+, and iOS 15+ unless a platform-specific section above states a stricter requirement. Platform SDKs, credentials, entitlements, physical hardware, and store configuration remain application responsibilities.

- [PAM documentation](https://push-in.github.io/pam-docs/introduction/)
- [PAM Native overview](https://push-in.github.io/pam-docs/native/overview/)
- [Plugin and native capability model](https://push-in.github.io/pam-docs/native/plugins/)
- [Report an issue](https://github.com/push-in/pam-native-devtools/issues)

Security vulnerabilities should be reported through the repository security policy or GitHub private vulnerability reporting, not a public issue.
