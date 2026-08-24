<?php

declare(strict_types=1);

namespace Pam\Native\DevTools;

use RuntimeException;

final class NetworkTransaction
{
    private NetworkState $state = NetworkState::Pending;

    /** @param array<string, mixed> $request */
    public function __construct(
        private readonly DevTools $owner,
        public readonly int $id,
        public readonly string $method,
        public readonly string $url,
        private readonly int $startedNs,
        private readonly array $request,
    ) {}

    /** @param array<string, mixed> $headers */
    public function complete(int $status, array $headers = [], mixed $body = null): void
    {
        $this->finish(NetworkState::Completed, ['status' => $status, 'headers' => $headers, 'body' => $body]);
    }

    public function fail(string $message): void
    {
        $this->finish(NetworkState::Failed, ['message' => mb_substr($message, 0, 2048)]);
    }

    public function cancel(): void
    {
        $this->finish(NetworkState::Cancelled, []);
    }

    /** @param array<string, mixed> $response */
    private function finish(NetworkState $state, array $response): void
    {
        if ($this->state !== NetworkState::Pending) {
            return;
        }
        $endedNs = hrtime(true);
        if (!is_int($endedNs)) {
            throw new RuntimeException('The platform does not expose integer nanosecond timestamps.');
        }
        $this->state = $state;
        $this->owner->finishNetwork($this, $this->startedNs, $endedNs, $state, $this->request, $response);
    }
}
