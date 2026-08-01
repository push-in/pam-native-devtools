# PAM Native DevTools

A deterministic, bounded diagnostic recorder for PAM Native development: application events, serializable state snapshots, performance marks, errors, and complete network transactions. Export one JSON artifact for bug reports or CI regressions without coupling production apps to a visual UI.

```php
$tools->snapshot('session', $session);
$tools->mark('feed.start');
$request = $tools->network('GET', $url, $headers);
try { $request->complete(200, $responseHeaders, $body); }
catch (Throwable $e) { $request->fail($e->getMessage()); throw $e; }
$tools->measure('feed.load', 'feed.start');
```

Sensitive keys are redacted recursively, object depth and collection size are bounded, strings are truncated, and the oldest records are discarded under pressure. The package complements PAM's native debug overlay and navigation timeline; it does not ship UI into production applications.
