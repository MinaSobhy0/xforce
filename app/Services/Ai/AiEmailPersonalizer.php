<?php

namespace App\Services\Ai;

/**
 * Turns a campaign body (author-written brief) + one recipient's
 * context into a personalized final HTML message.
 *
 * The brief is the campaign's raw body_html — treated as INTENT, not
 * final copy. The LLM rewrites it to include the recipient's tenant
 * name, plan, and other facts naturally in the flow.
 *
 * Called per-recipient by RenderPlatformCampaignBodyJob. Result is
 * cached on platform_email_campaign_recipients.rendered_body_html so
 * job retries don't re-charge the AI provider.
 */
class AiEmailPersonalizer
{
    public function __construct(
        protected LlmProviderRegistry $registry,
    ) {
    }

    /**
     * @param  string  $brief         The campaign's raw body_html.
     * @param  string  $promptExtra   Author's tone / style constraints.
     * @param  array   $recipient     ['name_hint' => ?string, 'email' => string]
     * @param  array   $context       Frozen tenant/plan snapshot ('tenant_name', 'plan_code', 'branch_count', ...).
     * @param  ?string $modelKey      Override the campaign default; null = campaign default.
     */
    public function personalize(
        string $brief,
        string $promptExtra,
        array $recipient,
        array $context,
        ?string $modelKey = null,
    ): LlmResponse {
        $system = $this->buildSystemPrompt($promptExtra);
        $user = $this->buildUserPrompt($brief, $recipient, $context);

        $request = LlmRequest::make($system, $user, [
            'temperature' => 0.5,
            'max_tokens' => 1500,
            'metadata' => [
                'purpose' => 'email_personalization',
                'recipient' => $recipient['email'] ?? null,
            ],
        ]);

        return $this->registry->completeWithFallback($modelKey, $request);
    }

    /**
     * Same as personalize() but returns a plain-text (no LLM call)
     * version — a safety net for when the campaign has AI disabled
     * OR the AI call fails hard and we want to deliver *something*.
     */
    public function fallbackTokenReplace(string $brief, array $recipient, array $context): string
    {
        $replacements = [];
        foreach (array_merge($recipient, $context) as $key => $value) {
            if (is_scalar($value)) {
                $replacements['{{ '.$key.' }}'] = e((string) $value);
                $replacements['{{'.$key.'}}'] = e((string) $value);
            }
        }
        return strtr($brief, $replacements);
    }

    protected function buildSystemPrompt(string $extra): string
    {
        $base = <<<PROMPT
You are an email copywriter for XLinic, a SaaS platform for clinics.
You rewrite a MARKETING BRIEF into personalized HTML for one specific
recipient. Follow these rules exactly:

1. Preserve the author's intent. Do not add new offers or facts.
2. START with a warm, natural greeting that uses the clinic's name.
   Good: "Hi Bayan Derma team," / "Hello Seoul Derma," /
   "Dear Al Andalus Clinic team,". Bad: no greeting at all, or a
   stiff "Dear [Name Placeholder]," stub. Then weave more clinic
   context (specialty, location if given) into the FIRST paragraph
   so it doesn't read as a template.
3. Wrap EVERY paragraph in <p>…</p>. This is not optional. Even a
   single-sentence paragraph gets its own <p>…</p>. Never emit plain
   text separated by blank lines — mail clients render that as one
   block of run-on text.
4. Allowed tags: <p>, <strong>, <em>, <a>, <br>, <ul>, <li>, <h2>,
   <h3>. NO inline styles, NO images, NO tracking pixels — the
   sending pipeline injects those separately.
5. Return ONLY the HTML body. No <html>, <head>, <body> wrappers.
   No <!DOCTYPE>. No markdown fences (no ```html either).
6. Never mention competitors or specific pricing not given in the brief.
7. Keep length within ±20% of the brief's word count.
8. If the clinic name suggests a specialty (dermatology, dental,
   skincare, hair, laser, cosmetic), acknowledge it once — briefly,
   not repeatedly.
PROMPT;

        if ($extra !== '') {
            $base .= "\n\nAdditional constraints from the author:\n".$extra;
        }

        return $base;
    }

    protected function buildUserPrompt(string $brief, array $recipient, array $context): string
    {
        $contextBlock = json_encode(
            array_merge(['recipient' => $recipient], ['context' => $context]),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
Recipient facts (JSON):
{$contextBlock}

Marketing brief (HTML the author drafted; treat as intent, not final copy):
---
{$brief}
---

Return the personalized HTML body ONLY.
PROMPT;
    }
}
