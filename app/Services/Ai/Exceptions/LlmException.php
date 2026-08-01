<?php

namespace App\Services\Ai\Exceptions;

class LlmException extends \RuntimeException
{
    /**
     * @return bool true when this failure should trigger a fallback
     *              provider attempt (transient — rate-limit, 5xx, timeout).
     */
    public function isRetryable(): bool
    {
        // 429 / 500-599 are transient; 4xx (other) are permanent
        // (bad API key, malformed request) and shouldn't be retried
        // on the same call.
        return $this->code === 429 || ($this->code >= 500 && $this->code < 600);
    }
}
