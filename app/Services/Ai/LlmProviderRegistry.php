<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\LlmProvider;
use App\Services\Ai\Exceptions\LlmException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a model key (config/llm.php provider entry) into a driver
 * instance. Also drives the fallback chain and exposes the "available
 * providers" list that Filament dropdowns filter on.
 */
class LlmProviderRegistry
{
    /** @var array<string, LlmProvider> */
    protected array $instances = [];

    /**
     * Resolve a driver for the given model key. Defaults to the
     * configured default when null / unknown.
     */
    public function for(?string $modelKey = null): LlmProvider
    {
        $key = $modelKey ?: (string) config('llm.default');

        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        $config = (array) config("llm.providers.{$key}");
        if (empty($config)) {
            throw new LlmException("Unknown LLM model key: {$key}");
        }

        $driverName = $config['driver'] ?? null;
        $driverClass = config("llm.drivers.{$driverName}");
        if (! $driverClass || ! class_exists($driverClass)) {
            throw new LlmException("No driver class registered for '{$driverName}'");
        }

        /** @var LlmProvider $provider */
        $provider = new $driverClass($key, $config);
        return $this->instances[$key] = $provider;
    }

    /**
     * Try the primary provider; on transient failures walk the
     * config('llm.fallbacks') chain. Only transient errors trigger a
     * fallback — a 400/401 (bad prompt / bad key) fails immediately.
     */
    public function completeWithFallback(?string $primaryModelKey, LlmRequest $request): LlmResponse
    {
        $primaryKey = $primaryModelKey ?: (string) config('llm.default');
        $chain = array_merge([$primaryKey], (array) config('llm.fallbacks', []));
        $chain = array_values(array_unique(array_filter($chain)));

        $lastException = null;
        foreach ($chain as $key) {
            try {
                return $this->for($key)->complete($request);
            } catch (LlmException $e) {
                $lastException = $e;
                if (! $e->isRetryable()) {
                    throw $e;
                }
                Log::warning("LLM {$key} failed, trying fallback", [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);
            }
        }

        throw $lastException ?? new LlmException('No LLM providers available');
    }

    /**
     * Model keys that have an API key configured — powers Filament
     * dropdowns so admins never pick a provider whose key is missing.
     *
     * @return array<string, string> [modelKey => label]
     */
    public static function available(): array
    {
        $providers = (array) config('llm.providers', []);
        return collect($providers)
            ->filter(fn (array $cfg) => filled($cfg['api_key'] ?? null))
            ->mapWithKeys(fn (array $cfg, string $key) => [$key => $cfg['label'] ?? $key])
            ->all();
    }
}
