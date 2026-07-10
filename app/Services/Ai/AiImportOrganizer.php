<?php

namespace App\Services\Ai;

/**
 * Reads a small sample from an uploaded CSV/XLSX and asks the LLM to
 * classify each column, propose normalizations, and flag suspicious
 * rows. Staff review the proposals in a Filament wizard step before
 * materializing into platform_email_list_members.
 *
 * We deliberately DO NOT let the LLM commit rows to the database —
 * it only proposes. All commits go through the confirmed mapping in
 * PlatformEmailListResource so a rogue prompt injection can't wipe or
 * corrupt list data.
 */
class AiImportOrganizer
{
    /**
     * Standard fields the wizard understands. LLM maps input columns
     * to these; anything else it labels 'drop'.
     */
    public const TARGET_FIELDS = [
        'email',      // required
        'name_hint',  // optional
        'drop',       // ignore this column
    ];

    public function __construct(
        protected LlmProviderRegistry $registry,
    ) {
    }

    /**
     * @param  array   $headers      Column headers from the source file.
     * @param  array   $sampleRows   First N rows (recommend ≤ 50). Each row = [col => value].
     * @param  ?string $modelKey     Override the default model.
     * @return array {
     *     mapping: array<string, string>,       // header → target field
     *     normalizations: array<string, list<string>>, // per-column rules like 'trim', 'lowercase'
     *     flagged_rows: list<array{row_index:int, reason:string}>,
     *     tokens_input: ?int,
     *     tokens_output: ?int,
     *     cost_usd_cents: int,
     *     model_id: string,
     * }
     */
    public function organize(array $headers, array $sampleRows, ?string $modelKey = null): array
    {
        $system = $this->systemPrompt();
        $user = $this->userPrompt($headers, $sampleRows);

        $request = LlmRequest::make($system, $user, [
            'temperature' => 0.1,   // deterministic classification, don't sample
            'max_tokens' => 2000,
            'json_schema' => ['type' => 'object'],
            'metadata' => ['purpose' => 'csv_import_organize'],
        ]);

        $response = $this->registry->completeWithFallback($modelKey, $request);

        $parsed = $response->asJson() ?? [
            'mapping' => [],
            'normalizations' => [],
            'flagged_rows' => [],
        ];

        return [
            'mapping' => $this->sanitizeMapping((array) ($parsed['mapping'] ?? []), $headers),
            'normalizations' => (array) ($parsed['normalizations'] ?? []),
            'flagged_rows' => $this->sanitizeFlaggedRows((array) ($parsed['flagged_rows'] ?? []), count($sampleRows)),
            'tokens_input' => $response->tokensInput,
            'tokens_output' => $response->tokensOutput,
            'cost_usd_cents' => $response->costUsdCents,
            'model_id' => $response->modelId,
        ];
    }

    /**
     * Apply the confirmed mapping + normalizations to a single row.
     * Returns [email, name_hint] or null if the row can't be mapped
     * (e.g. no email column, invalid email format).
     *
     * @return ?array{email:string, name_hint:?string}
     */
    public function projectRow(array $row, array $mapping, array $normalizations): ?array
    {
        $email = null;
        $nameHint = null;

        foreach ($mapping as $header => $target) {
            $value = $row[$header] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $rules = (array) ($normalizations[$header] ?? []);
            $value = $this->applyRules((string) $value, $rules);

            if ($target === 'email') {
                $email = $value;
            } elseif ($target === 'name_hint') {
                $nameHint = $value;
            }
        }

        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return ['email' => mb_strtolower($email), 'name_hint' => $nameHint];
    }

    protected function applyRules(string $value, array $rules): string
    {
        foreach ($rules as $rule) {
            $value = match ($rule) {
                'trim' => trim($value),
                'lowercase', 'lowercase-email' => mb_strtolower($value),
                'title-case', 'title-case-name' => mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8'),
                'collapse-whitespace' => (string) preg_replace('/\s+/u', ' ', $value),
                default => $value,
            };
        }
        return $value;
    }

    /**
     * Discard mapping entries for headers we didn't send and target
     * values we don't recognize. Defense against LLM hallucination.
     */
    protected function sanitizeMapping(array $mapping, array $headers): array
    {
        $out = [];
        $headerSet = array_flip($headers);
        foreach ($mapping as $header => $target) {
            if (! isset($headerSet[$header])) {
                continue;
            }
            $target = strtolower(trim((string) $target));
            if (! in_array($target, self::TARGET_FIELDS, true)) {
                $target = 'drop';
            }
            $out[$header] = $target;
        }
        // Headers the LLM omitted default to 'drop'.
        foreach ($headers as $h) {
            $out[$h] = $out[$h] ?? 'drop';
        }
        return $out;
    }

    protected function sanitizeFlaggedRows(array $rows, int $sampleSize): array
    {
        $out = [];
        foreach ($rows as $r) {
            $idx = (int) ($r['row_index'] ?? -1);
            if ($idx < 0 || $idx >= $sampleSize) {
                continue;
            }
            $reason = mb_substr(trim((string) ($r['reason'] ?? 'unspecified')), 0, 200);
            $out[] = ['row_index' => $idx, 'reason' => $reason];
        }
        return $out;
    }

    protected function systemPrompt(): string
    {
        $targets = implode(' | ', self::TARGET_FIELDS);

        return <<<PROMPT
You classify columns from a spreadsheet uploaded to an email list.
For each header you see, map it to ONE of: {$targets}.

Also propose normalization rules per column from this set:
  trim, lowercase-email, title-case-name, collapse-whitespace

Also flag row indices (0-based within the SAMPLE you receive) that look
suspicious (invalid emails, likely test data, duplicate of another sample row).

Return STRICT JSON with exactly this shape:
{
  "mapping": {"<header>": "email|name_hint|drop", ...},
  "normalizations": {"<header>": ["trim", "lowercase-email"], ...},
  "flagged_rows": [{"row_index": 3, "reason": "invalid email"}, ...]
}

Rules:
- Every input header must appear in mapping. Unknown/junk columns → 'drop'.
- If a header clearly holds emails ('Email', 'e-mail', 'contact_email', etc.) → 'email'.
- If a header clearly holds names ('Name', 'Full Name', 'Clinic', 'Practice') → 'name_hint'.
- Do NOT invent normalizations. Only apply rules the data actually needs.
- No prose, no markdown, no code fences. Just the JSON object.
PROMPT;
    }

    protected function userPrompt(array $headers, array $sampleRows): string
    {
        $payload = [
            'headers' => $headers,
            'sample_rows' => $sampleRows,
        ];
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
