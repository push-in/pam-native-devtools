<?php

declare(strict_types=1);

namespace Pam\Native\DevTools;

use Closure;
use OutOfBoundsException;

final class ReplaySession
{
    private int $cursor = -1;

    public function __construct(public readonly TimelineRecording $recording)
    {
    }

    public function cursor(): int
    {
        return $this->cursor;
    }

    public function reset(): void
    {
        $this->cursor = -1;
    }

    /** @param Closure(array<string, mixed>): void $consumer */
    public function next(Closure $consumer): bool
    {
        if ($this->cursor + 1 >= count($this->recording->timeline)) {
            return false;
        }
        $this->cursor++;
        $consumer($this->recording->timeline[$this->cursor]);
        return true;
    }

    /** @param Closure(array<string, mixed>): void $consumer */
    public function replay(Closure $consumer): int
    {
        $count = 0;
        while ($this->next($consumer)) {
            $count++;
        }
        return $count;
    }

    public function seekToId(int $id): void
    {
        foreach ($this->recording->timeline as $index => $row) {
            if ($row['id'] === $id) {
                $this->cursor = $index;
                return;
            }
        }
        throw new OutOfBoundsException("Unknown timeline record {$id}.");
    }

    public function latestSnapshot(string $name): mixed
    {
        for ($index = min($this->cursor, count($this->recording->timeline) - 1); $index >= 0; $index--) {
            $row = $this->recording->timeline[$index];
            if ($row['kind'] === RecordKind::StateSnapshot->value && $row['name'] === $name) {
                return $row['data'];
            }
        }
        return null;
    }
}
