<?php

namespace Modules\Marketing\Exceptions;

use Exception;

class QuotaExceededException extends Exception
{
    public function __construct(
        public readonly string $channel,
        public readonly int $remaining,
        public readonly int $requested,
        string $message = ''
    ) {
        $message = $message ?: $this->buildMessage();
        parent::__construct($message);
    }

    protected function buildMessage(): string
    {
        $channelName = ucfirst($this->channel);

        if ($this->remaining === 0) {
            return "{$channelName} quota exhausted. Please upgrade your plan or wait for the next billing cycle.";
        }

        return "{$channelName} quota exceeded. Remaining: {$this->remaining}, requested: {$this->requested}.";
    }

    /**
     * Get a user-friendly error message.
     */
    public function getUserMessage(): string
    {
        return __('marketing::marketing.quota.exceeded', [
            'channel' => $this->channel,
            'remaining' => $this->remaining,
        ]);
    }

    /**
     * Get the error as an array for API responses.
     */
    public function toArray(): array
    {
        return [
            'error' => 'quota_exceeded',
            'channel' => $this->channel,
            'remaining' => $this->remaining,
            'requested' => $this->requested,
            'message' => $this->getMessage(),
        ];
    }
}
