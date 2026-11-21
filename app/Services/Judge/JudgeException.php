<?php

namespace App\Services\Judge;

use RuntimeException;
use Throwable;

class JudgeException extends RuntimeException
{
    public static function unreachable(Throwable $previous): self
    {
        return new self('The judge service could not be reached.', 0, $previous);
    }

    public static function invalidResponse(string $message): self
    {
        return new self('The judge service returned an invalid response: '.$message);
    }

    public static function requestFailed(int $status): self
    {
        return new self("The judge service rejected the request with HTTP status {$status}.");
    }
}
