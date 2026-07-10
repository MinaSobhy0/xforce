<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\LlmRequest;
use App\Services\Ai\LlmResponse;

/**
 * Anthropic Messages API. System prompt lives at the top level (not
 * in messages), and token counts arrive as input_tokens /
 * output_tokens (not prompt_tokens / completion_tokens like the
 * OpenAI-compatible providers).
 */
class ClaudeProvider extends AbstractLlmProvider
{
    public function complete(LlmRequest $request): LlmResponse
    {
        $payload = [
            'model' => $this->modelName(),
            'max_tokens' => $request->effectiveMaxTokens(),
            'temperature' => $request->effectiveTemperature(),
            'messages' => $request->messages,
        ];

        if ($request->system !== '') {
            $payload['system'] = $request->system;
        }

        $response = $this->http()->post($this->config['endpoint'], $payload);
        $this->ensureSuccess($response, 'Anthropic');

        $body = $response->json();
        // content is an array of blocks — grab the text ones and join.
        $blocks = $body['content'] ?? [];
        $text = collect($blocks)
            ->filter(fn ($b) => ($b['type'] ?? '') === 'text')
            ->map(fn ($b) => $b['text'] ?? '')
            ->implode('');

        $usage = $body['usage'] ?? [];
        $tokensInput = isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null;
        $tokensOutput = isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null;

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
            'x-api-key' => $this->apiKey(),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ];
    }
}
