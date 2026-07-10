<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\LlmRequest;
use App\Services\Ai\LlmResponse;

/**
 * Google generativeAI (Gemini) — auth via ?key= query param, endpoint
 * suffix depends on the model, and messages are called "contents" with
 * roles user / model (not user / assistant).
 */
class GeminiProvider extends AbstractLlmProvider
{
    public function complete(LlmRequest $request): LlmResponse
    {
        $contents = [];
        foreach ($request->messages as $m) {
            $contents[] = [
                'role' => $m['role'] === 'assistant' ? 'model' : $m['role'],
                'parts' => [['text' => $m['content']]],
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $request->effectiveTemperature(),
                'maxOutputTokens' => $request->effectiveMaxTokens(),
            ],
        ];

        if ($request->system !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $request->system]],
            ];
        }

        if ($request->jsonSchema !== null) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $url = rtrim($this->config['endpoint'], '/').'/'.$this->modelName().':generateContent?key='.$this->apiKey();

        $response = $this->http()->post($url, $payload);
        $this->ensureSuccess($response, 'Gemini');

        $body = $response->json();
        $text = collect($body['candidates'][0]['content']['parts'] ?? [])
            ->map(fn ($p) => $p['text'] ?? '')
            ->implode('');

        $usage = $body['usageMetadata'] ?? [];
        $tokensInput = isset($usage['promptTokenCount']) ? (int) $usage['promptTokenCount'] : null;
        $tokensOutput = isset($usage['candidatesTokenCount']) ? (int) $usage['candidatesTokenCount'] : null;

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
            'Content-Type' => 'application/json',
        ];
    }
}
