<?php

declare(strict_types=1);

namespace Pam\Native\DevTools;

use InvalidArgumentException;
use JsonException;

final readonly class TimelineRecording
{
    private const MAX_BYTES = 8_388_608;
    private const MAX_RECORDS = 10_000;

    /** @param list<array{id: int, kind: int, name: string, timestampNs: int, durationMs: float, data: mixed}> $timeline */
    public function __construct(public array $timeline, public int $dropped = 0)
    {
        if (count($timeline) > self::MAX_RECORDS || $dropped < 0) {
            throw new InvalidArgumentException('Timeline recording exceeds its bounds.');
        }
        $previousId = 0;
        foreach ($timeline as $row) {
            if ($row['id'] <= $previousId || RecordKind::tryFrom($row['kind']) === null || preg_match('/^[^\x00-\x1f]{1,160}$/u', $row['name']) !== 1 || $row['timestampNs'] < 0 || !is_finite($row['durationMs']) || $row['durationMs'] < 0.0) {
                throw new InvalidArgumentException('Timeline recording contains an invalid row.');
            }
            $previousId = $row['id'];
        }
    }

    public function toJson(): string
    {
        return json_encode(['schemaVersion' => 2, 'metrics' => ['records' => count($this->timeline), 'dropped' => $this->dropped], 'timeline' => $this->timeline], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function fromJson(string $json): self
    {
        if ($json === '' || strlen($json) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Timeline recording has an invalid size.');
        }
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Timeline recording is not valid JSON.', previous: $error);
        }
        $metrics = is_array($decoded) ? ($decoded['metrics'] ?? null) : null;
        if (!is_array($decoded) || ($decoded['schemaVersion'] ?? null) !== 2 || !is_array($decoded['timeline'] ?? null) || !is_array($metrics) || !is_int($metrics['dropped'] ?? null)) {
            throw new InvalidArgumentException('Timeline recording uses an invalid contract.');
        }
        $rows = [];
        foreach ($decoded['timeline'] as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Timeline recording contains an invalid row.');
            }
            [$id, $kind, $name, $timestamp, $duration] = [$row['id'] ?? null, $row['kind'] ?? null, $row['name'] ?? null, $row['timestampNs'] ?? null, $row['durationMs'] ?? null];
            if (!is_int($id) || !is_int($kind) || !is_string($name) || !is_int($timestamp) || (!is_int($duration) && !is_float($duration))) {
                throw new InvalidArgumentException('Timeline recording contains an invalid row.');
            }
            $rows[] = ['id' => $id, 'kind' => $kind, 'name' => $name, 'timestampNs' => $timestamp, 'durationMs' => (float) $duration, 'data' => $row['data'] ?? null];
        }
        return new self($rows, $metrics['dropped']);
    }
}
