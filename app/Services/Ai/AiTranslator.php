<?php

namespace App\Services\Ai;

/**
 * Thin wrapper around LlmProviderRegistry for translating short text
 * or HTML fragments. Used by the "Duplicate for other language" action
 * to spin up a campaign twin in the target language.
 *
 * Preserves HTML tag structure — the model rewrites text nodes only.
 */
class AiTranslator
{
    public function __construct(
        protected LlmProviderRegistry $registry,
    ) {
    }

    public function translate(string $text, string $targetLanguage, ?string $model = null): string
    {
        if ($text === '') {
            return '';
        }

        $languageLabel = $targetLanguage === 'ar'
            ? 'Modern Standard Arabic (Gulf-friendly phrasing)'
            : 'English (warm, professional)';

        $bidiRule = $targetLanguage === 'ar'
            ? "7. Every Latin-script word or URL (XLinic, ibram@x-linic.com,
   Kuwait, https://x-linic.com, brand names, product names) MUST
   be wrapped in a <bdi dir=\"ltr\">…</bdi> element so it displays
   correctly inside the Arabic sentence. Example:
   'شكراً لك من فريق <bdi dir=\"ltr\">XLinic</bdi>' — NOT
   'شكراً لك من فريق XLinic' (which would render in the wrong position).
   Always wrap even simple brand mentions."
            : '';

        $system = <<<PROMPT
You translate marketing/business emails to {$languageLabel}.

Rules:
1. Preserve HTML tag structure exactly (<p>, <strong>, <a>, <br>, etc.).
   Translate ONLY the text between tags. Do not add or remove tags.
2. Keep proper names, brand names (XLinic), URLs, and email addresses
   in their original form — do NOT transliterate.
3. If the input has no HTML, output plain text with the same paragraph
   breaks.
4. Return ONLY the translated content. No preface, no explanation, no
   markdown fences.
5. If translating TO Arabic: use Modern Standard Arabic with warm,
   professional phrasing that a Kuwaiti clinic manager would find
   natural. Retain a personal, non-corporate tone.
6. Numbers can stay as Latin (0-9) — do NOT convert to Arabic-Indic
   unless the source already uses them.
{$bidiRule}
PROMPT;

        $request = LlmRequest::make(
            system: $system,
            userPrompt: $text,
            overrides: [
                'temperature' => 0.2,
                'max_tokens' => 2000,
                'metadata' => ['purpose' => 'translate', 'target' => $targetLanguage],
            ],
        );

        $response = $this->registry->completeWithFallback($model, $request);
        return trim($response->text);
    }
}
