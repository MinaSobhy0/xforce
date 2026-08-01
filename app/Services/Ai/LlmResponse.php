<?php

namespace App\Services\Ai;

/**
 * Provider-neutral response from an LLM call. Every driver produces
 * one of these; consumers never touch provider-specific payloads
 * unless they explicitly reach into $raw.
 *
 * $costUsdCents is an integer number of USD cents (× 100 for actual
 * dollars) — kept as integer so we can sum campaigns without float
 * drift. Providers that don't return token counts return null for
 * the token fields; consumers should treat null as "unknown."
 */
class LlmResponse
{
    public function __construct(
        public string $text,
        public string $modelId,
        public ?int $tokensInput = null,
        public ?int $tokensOutput = null,
        public int $costUsdCents = 0,
        public array $raw = [],
    ) {}

    /**
     * Attempt to parse $text as JSON. Returns null on parse failure
     * — the caller decides whether to raise or fall back to text.
     */
    public function asJson(): ?array
    {
        $trimmed = trim($this->text);

        // Providers sometimes wrap JSON in ```json fences.
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/mi', '', $trimmed);
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }
}
