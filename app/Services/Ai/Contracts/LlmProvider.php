<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\LlmRequest;
use App\Services\Ai\LlmResponse;

/**
 * Common contract for every LLM provider driver (Claude, Gemini, Grok,
 * DeepSeek, OpenAI, and future ones).
 *
 * Consumers of the AI layer (AiEmailPersonalizer, AiImportOrganizer,
 * …) code against THIS interface and stay ignorant of provider
 * differences. Swap providers via config, not code.
 */
interface LlmProvider
{
    /**
     * Stable model id — matches the key in config/llm.php.providers[].
     * Written into recipient / send-log rows for later auditing.
     */
    public function id(): string;

    /**
     * Send the request. On rate-limit / 5xx / timeout the driver throws
     * an exception; the registry can catch it and try a fallback
     * provider. On success returns a fully-populated LlmResponse.
     */
    public function complete(LlmRequest $request): LlmResponse;

    /**
     * Cost estimate in USD cents for the given token counts. Used by
     * the Filament composer to show "this campaign will cost ~$X"
     * before Send is clicked.
     */
    public function estimateCost(int $inputTokens, int $outputTokens): int;
}
