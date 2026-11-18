<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Single exception type for all judge-related failures.
 * Use the static factories — they carry the right context.
 */
final class JudgeException extends RuntimeException
{
    /**
     * @param array<string, mixed>|null $context
     */
    public function __construct(
        string $message,
        public readonly ?array $context = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function unreachable(Throwable $previous): self
    {
        return new self(
            'Judge engine is unreachable.',
            ['error' => $previous->getMessage()],
            $previous,
        );
    }

    public static function rejected(int $status, string $body): self
    {
        return new self(
            "Judge rejected the request (HTTP {$status}).",
            [
                'status' => $status,
                'body'   => mb_substr($body, 0, 500),
            ],
        );
    }

    public static function invalidResponse(string $reason): self
    {
        return new self("Judge returned an invalid response: {$reason}");
    }

    public static function invalidPayload(string $reason): self
    {
        return new self("Cannot build judge payload: {$reason}");
    }
}
