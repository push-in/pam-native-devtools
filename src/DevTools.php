<?php

declare(strict_types=1);

namespace Pam\Native\DevTools;

use InvalidArgumentException;
use Throwable;

final class DevTools
{
    /** @var list<array{id: int, kind: int, name: string, timestampNs: int, durationMs: float, data: mixed}> */
    private array $timeline = [];
    /** @var array<string, int> */
    private array $marks = [];
    private int $nextId = 1;
    private int $dropped = 0;

    public function __construct(
        private readonly int $capacity = 1024,
        private readonly Redactor $redactor = new Redactor(),
    ) {
        if ($capacity < 32 || $capacity > 10_000) {
            throw new InvalidArgumentException('DevTools capacity must be between 32 and 10000.');
        }
    }

    public function event(string $name, mixed $data = null): int
    {
        return $this->record(RecordKind::Event, $name, $data);
    }

    public function snapshot(string $name, mixed $state): int
    {
        return $this->record(RecordKind::StateSnapshot, $name, $state);
    }

    public function mutation(string $name, mixed $previous, mixed $next): int
    {
        return $this->record(RecordKind::Mutation, $name, ['previous' => $previous, 'next' => $next]);
    }

    /** @param array<string, int|float|bool> $metrics */
    public function frame(string $name, array $metrics): int
    {
        return $this->record(RecordKind::Frame, $name, $metrics);
    }

    /** @param array<string, mixed> $context */
    public function error(Throwable $error, array $context = []): int
    {
        return $this->record(RecordKind::Error, $error::class, [
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => mb_substr($error->getTraceAsString(), 0, 16_000),
            'context' => $context,
        ]);
    }

    public function mark(string $name): void
    {
        self::assertName($name);
        $this->marks[$name] = self::timestamp();
    }

    public function measure(string $name, string $from, ?string $to = null): float
    {
        self::assertName($name);
        if (!isset($this->marks[$from])) {
            throw new InvalidArgumentException('Unknown performance mark: '.$from);
        }
        $end = $to === null ? self::timestamp() : ($this->marks[$to] ?? throw new InvalidArgumentException('Unknown performance mark: '.$to));
        $duration = max(0.0, ($end - $this->marks[$from]) / 1_000_000);
        $this->record(RecordKind::Performance, $name, ['from' => $from, 'to' => $to, 'durationMs' => $duration]);
        return $duration;
    }

    /** @param array<string, mixed> $headers */
    public function network(string $method, string $url, array $headers = [], mixed $body = null): NetworkTransaction
    {
        $method = strtoupper($method);
        if (preg_match('/^[A-Z]{3,12}$/D', $method) !== 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Invalid network transaction.');
        }
        return new NetworkTransaction($this, $this->nextId++, $method, $url, self::timestamp(), ['headers' => $headers, 'body' => $body]);
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $response
     */
    public function finishNetwork(NetworkTransaction $transaction, int $start, int $end, NetworkState $state, array $request, array $response): void
    {
        $this->append([
            'id' => $transaction->id,
            'kind' => RecordKind::Network->value,
            'name' => $transaction->method,
            'timestampNs' => $start,
            'durationMs' => max(0.0, ($end - $start) / 1_000_000),
            'data' => $this->redactor->redact(['url' => $transaction->url, 'state' => $state->value, 'request' => $request, 'response' => $response]),
        ]);
    }

    /** @return list<array{id: int, kind: int, name: string, timestampNs: int, durationMs: float, data: mixed}> */
    public function timeline(): array
    {
        return $this->timeline;
    }

    public function recording(): TimelineRecording
    {
        return new TimelineRecording($this->timeline, $this->dropped);
    }

    public function replay(): ReplaySession
    {
        return new ReplaySession($this->recording());
    }

    public function clear(): void
    {
        $this->timeline = [];
        $this->marks = [];
        $this->dropped = 0;
    }

    /** @return array<string, int> */
    public function metrics(): array
    {
        $kinds = array_fill_keys(array_map(static fn (RecordKind $kind): int => $kind->value, RecordKind::cases()), 0);
        foreach ($this->timeline as $row) {
            $kinds[$row['kind']]++;
        }
        return [
            'records' => count($this->timeline),
            'dropped' => $this->dropped,
            'events' => $kinds[1],
            'snapshots' => $kinds[2],
            'network' => $kinds[3],
            'performance' => $kinds[4],
            'errors' => $kinds[5],
            'mutations' => $kinds[6],
            'frames' => $kinds[7],
        ];
    }

    public function exportJson(): string
    {
        return $this->recording()->toJson();
    }

    private function record(RecordKind $kind, string $name, mixed $data): int
    {
        self::assertName($name);
        $id = $this->nextId++;
        $this->append(['id' => $id, 'kind' => $kind->value, 'name' => $name, 'timestampNs' => self::timestamp(), 'durationMs' => 0.0, 'data' => $this->redactor->redact($data)]);
        return $id;
    }

    /** @param array{id: int, kind: int, name: string, timestampNs: int, durationMs: float, data: mixed} $row */
    private function append(array $row): void
    {
        while (count($this->timeline) >= $this->capacity) {
            array_shift($this->timeline);
            $this->dropped++;
        }
        $this->timeline[] = $row;
    }

    private static function assertName(string $name): void
    {
        if (preg_match('/^[^\x00-\x1f]{1,160}$/u', $name) !== 1) {
            throw new InvalidArgumentException('Invalid DevTools record name.');
        }
    }

    private static function timestamp(): int
    {
        $timestamp = hrtime(true);
        if (!is_int($timestamp)) {
            throw new \RuntimeException('The platform does not expose integer nanosecond timestamps.');
        }
        return $timestamp;
    }
}
