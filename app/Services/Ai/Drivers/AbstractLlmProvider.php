<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\LlmProvider;
use App\Services\Ai\Exceptions\LlmException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared plumbing: cost math, HTTP client wiring, response error
 * mapping. Concrete drivers implement only the provider-specific
 * request/response translation.
 */
abstract class AbstractLlmProvider implements LlmProvider
{
    public function __construct(
        protected string $modelKey,
        protected array $config,
    ) {
    }

    public function id(): string
    {
        return $this->modelKey;
    }

    public function estimateCost(int $inputTokens, int $outputTokens): int
    {
        $inputUsd = ($inputTokens / 1_000_000) * (float) ($this->config['input_cost_per_mtok'] ?? 0);
        $outputUsd = ($outputTokens / 1_000_000) * (float) ($this->config['output_cost_per_mtok'] ?? 0);

        // Return cents, rounded UP so we never under-report cost.
        return (int) ceil(($inputUsd + $outputUsd) * 100);
    }

    /**
     * HTTP client used by every driver. Sub-classes call ->post() etc.
     * Extra headers can be layered on by overriding {@see extraHeaders()}.
     */
    protected function http(): PendingRequest
    {
        return Http::timeout((int) config('llm.defaults.timeout', 60))
            ->connectTimeout(15)
            ->acceptJson()
            ->withHeaders($this->extraHeaders());
    }

    /**
     * Provider-specific headers (auth, API version, etc.).
     *
     * @return array<string,string>
     */
    abstract protected function extraHeaders(): array;

    /**
     * Guard against HTTP failures uniformly — turns rate-limit/5xx into
     * an LlmException the registry can catch to try a fallback.
     */
    protected function ensureSuccess(Response $response, string $providerLabel): void
    {
        if ($response->successful()) {
            return;
        }

        throw new LlmException(sprintf(
            '%s API returned %d: %s',
            $providerLabel,
            $response->status(),
            substr((string) $response->body(), 0, 500),
        ), $response->status());
    }

    protected function apiKey(): string
    {
        $key = $this->config['api_key'] ?? null;
        if (empty($key)) {
            throw new LlmException("No API key configured for {$this->modelKey}");
        }
        return $key;
    }

    protected function modelName(): string
    {
        return $this->config['model'] ?? $this->modelKey;
    }
}
