<?php

declare(strict_types=1);

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Pam\\Native\\DevTools\\';
    if (str_starts_with($class, $prefix)) {
        require $root.'/src/'.substr($class, strlen($prefix)).'.php';
    }
});

use Pam\Native\DevTools\DevTools;
use Pam\Native\DevTools\TimelineRecording;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$tools = new DevTools(32);
$snapshot = $tools->snapshot('auth', ['token' => 'secret', 'user' => 1]);
$tools->mutation('auth', ['user' => 1], ['user' => 2]);
$tools->frame('home', ['refreshRateHz' => 120, 'frameMs' => 7.2, 'missed' => false]);
$tools->mark('start');
$tools->measure('boot', 'start');
$network = $tools->network('get', 'https://example.test', ['authorization' => 'Bearer x']);
$network->complete(200, body: ['ok' => true]);

$json = $tools->exportJson();
$assert(!str_contains($json, 'secret') && !str_contains($json, 'Bearer x'), 'Exports must redact secrets.');
$recording = TimelineRecording::fromJson($json);
$assert($recording->toJson() === $json, 'Timeline export and import must be byte-stable.');

$seen = [];
$replay = $tools->replay();
$assert($replay->replay(static function (array $row) use (&$seen): void {
    $seen[] = $row['kind'];
}) === 5, 'Replay must visit every record exactly once.');
$replay->seekToId($snapshot);
$state = $replay->latestSnapshot('auth');
$assert(is_array($state) && ($state['user'] ?? null) === 1, 'Time travel must recover the latest snapshot at the cursor.');

for ($index = 0; $index < 40; $index++) {
    $tools->event('tick', $index);
}
$assert(count($tools->timeline()) === 32 && $tools->metrics()['dropped'] === 13, 'Timeline capacity must remain bounded.');

echo "PASS temporal replay, redaction, frames, mutations and bounds\n1 tests, 0 failures\n";
