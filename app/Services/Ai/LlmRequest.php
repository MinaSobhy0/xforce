<?php

namespace App\Services\Ai;

/**
 * Provider-neutral request to an LLM. Every driver knows how to
 * translate this into its provider-specific request shape.
 *
 * $messages is an array of [
 *   'role' => 'user' | 'assistant',
 *   'content' => string,
 * ] — the trailing role must be 'user'. System prompt goes in $system.
 *
 * $jsonSchema, when non-null, requests structured JSON output. Providers
 * that support native JSON mode use it; others get a prompt-appended
 * instruction. Consumers should treat the returned text as JSON in
 * either case.
 */
class LlmRequest
{
    public function __construct(
        public string $system = '',
        public array $messages = [],
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?array $jsonSchema = null,
        public array $metadata = [],
    ) {
    }

    /**
     * Convenience factory for the common single-user-message pattern.
     */
    public static function make(string $system, string $userPrompt, array $overrides = []): self
    {
        return new self(
            system: $system,
            messages: [['role' => 'user', 'content' => $userPrompt]],
            temperature: $overrides['temperature'] ?? null,
            maxTokens: $overrides['max_tokens'] ?? null,
            jsonSchema: $overrides['json_schema'] ?? null,
            metadata: $overrides['metadata'] ?? [],
        );
    }

    public function effectiveTemperature(): float
    {
        return $this->temperature ?? (float) config('llm.defaults.temperature', 0.4);
    }

    public function effectiveMaxTokens(): int
    {
        return $this->maxTokens ?? (int) config('llm.defaults.max_tokens', 1024);
    }
}
