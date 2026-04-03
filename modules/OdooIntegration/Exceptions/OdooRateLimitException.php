<?php

namespace Modules\OdooIntegration\Exceptions;

use Exception;
use Throwable;

class OdooRateLimitException extends Exception
{
    protected int $retryAfter;
    protected int $limit;
    protected int $remaining;

    public function __construct(
        string $message = 'Rate limit exceeded',
        int $retryAfter = 60,
        int $limit = 0,
        int $remaining = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 429, $previous);
        $this->retryAfter = $retryAfter;
        $this->limit = $limit;
        $this->remaining = $remaining;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getRemaining(): int
    {
        return $this->remaining;
    }

    public static function exceeded(int $retryAfter = 60, int $limit = 0): self
    {
        return new self(
            "Rate limit exceeded. Retry after {$retryAfter} seconds.",
            $retryAfter,
            $limit,
            0
        );
    }

    public static function tooManyRequests(int $limit, int $remaining, int $retryAfter): self
    {
        return new self(
            "Too many requests. {$remaining} of {$limit} remaining. Retry after {$retryAfter} seconds.",
            $retryAfter,
            $limit,
            $remaining
        );
    }
}
