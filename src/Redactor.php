<?php

declare(strict_types=1);

namespace Pam\Native\DevTools;

final readonly class Redactor
{
    /** @param list<string> $keys */
    public function __construct(
        private array $keys = ['authorization', 'cookie', 'set-cookie', 'password', 'token', 'secret', 'api_key'],
    ) {}

    public function redact(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return '[max-depth]';
        }
        if (is_array($value)) {
            if (count($value) > 256) {
                $value = array_slice($value, 0, 256, true);
            }
            $output = [];
            foreach ($value as $key => $item) {
                $output[$key] = is_string($key) && in_array(strtolower($key), $this->keys, true)
                    ? '[redacted]'
                    : $this->redact($item, $depth + 1);
            }
            return $output;
        }
        if (is_object($value)) {
            return ['class' => $value::class, 'properties' => $this->redact(get_object_vars($value), $depth + 1)];
        }
        if (is_resource($value)) {
            return '[resource]';
        }
        if (is_string($value)) {
            return mb_substr($value, 0, 4096);
        }
        return is_scalar($value) || $value === null ? $value : '[unsupported]';
    }
}
