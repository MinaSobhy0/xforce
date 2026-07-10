<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\LlmRequest;
use App\Services\Ai\LlmResponse;

/**
 * OpenAI's chat-completions API shape is a de-facto standard: DeepSeek,
 * xAI Grok, and (obviously) OpenAI itself all speak it. This base class
 * centralizes the translation so those three drivers stay ~15 lines
 * each — they only vary in the endpoint and auth header.
 */
abstract class OpenAiCompatibleProvider extends AbstractLlmProvider
{
    public function complete(LlmRequest $request): LlmResponse
    {
        $messages = [];
        if ($request->system !== '') {
            $messages[] = ['role' => 'system', 'content' => $request->system];
        }
        foreach ($request->messages as $m) {
            $messages[] = $m;
        }

        $payload = [
            'model' => $this->modelName(),
            'messages' => $messages,
            'temperature' => $request->effectiveTemperature(),
            'max_tokens' => $request->effectiveMaxTokens(),
        ];

        // Native JSON mode when requested — providers agree on the key.
        if ($request->jsonSchema !== null) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $endpoint = $this->config['endpoint'];
        $response = $this->http()->post($endpoint, $payload);
        $this->ensureSuccess($response, static::providerLabel());

        $body = $response->json();
        $text = $body['choices'][0]['message']['content'] ?? '';
        $usage = $body['usage'] ?? [];
        $tokensInput = isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null;
        $tokensOutput = isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null;

        return new LlmResponse(
            text: $text,
            modelId: $this->modelKey,
            tokensInput: $tokensInput,
            tokensOutput: $tokensOutput,
            costUsdCents: $this->estimateCost($tokensInput ?? 0, $tokensOutput ?? 0),
            raw: $body,
        );
    }

    protected function extraHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey(),
            'Content-Type' => 'application/json',
        ];
    }

    abstract protected static function providerLabel(): string;
}
