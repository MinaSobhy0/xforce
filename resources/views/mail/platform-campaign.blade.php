<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>{{ $bodyHtml ? '' : 'XLinic' }}</title>
</head>
<body style="margin:0; padding:0; background:#f6f7f9;">
{{-- Preheader (inbox preview text) --}}
@if($preheader)
<div style="display:none; font-size:1px; color:#f6f7f9; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
    {{ $preheader }}
</div>
@endif

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f6f7f9;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px; background:#ffffff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.05);">
                <tr>
                    <td style="padding:32px 32px 8px 32px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; font-size:16px; line-height:1.6; color:#111827;">
                        @php
                            // Step 1 — normalize whatever shape the AI returned
                            // into properly-tagged paragraphs. Gemini Flash
                            // Lite is inconsistent: sometimes it emits <p> per
                            // paragraph, sometimes plain text with \n\n breaks.
                            // If we find no <p>/<h2>/<h3>/<ul> block tag at
                            // all, we treat the input as plain text and wrap
                            // blank-line-separated blocks in <p> ourselves.
                            $normalized = trim($bodyHtml);
                            if (! preg_match('/<(p|h[1-3]|ul|ol|div|blockquote)\b/i', $normalized)) {
                                $blocks = preg_split('/\R{2,}/', $normalized);
                                $normalized = collect($blocks)
                                    ->map(fn ($b) => trim($b))
                                    ->filter()
                                    // Single newlines inside a paragraph become <br>
                                    ->map(fn ($b) => '<p>'.nl2br($b).'</p>')
                                    ->implode("\n");
                            }

                            // Step 2 — inject inline spacing on every block
                            // element (mail clients reset default margins to 0).
                            $spaced = preg_replace(
                                '/<p(\s[^>]*)?>/i',
                                '<p$1 style="margin:0 0 16px 0;">',
                                $normalized,
                            );
                            $spaced = preg_replace(
                                '/<h2(\s[^>]*)?>/i',
                                '<h2$1 style="margin:24px 0 8px 0; font-size:20px; line-height:1.3;">',
                                $spaced,
                            );
                            $spaced = preg_replace(
                                '/<h3(\s[^>]*)?>/i',
                                '<h3$1 style="margin:20px 0 8px 0; font-size:17px; line-height:1.3;">',
                                $spaced,
                            );
                            $spaced = preg_replace(
                                '/<ul(\s[^>]*)?>/i',
                                '<ul$1 style="margin:0 0 16px 0; padding-left:24px;">',
                                $spaced,
                            );
                            $spaced = preg_replace(
                                '/<li(\s[^>]*)?>/i',
                                '<li$1 style="margin:0 0 6px 0;">',
                                $spaced,
                            );
                        @endphp
                        {!! $spaced !!}
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px 28px 32px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; font-size:12px; line-height:1.5; color:#6b7280; border-top:1px solid #e5e7eb;">
                        You are receiving this email because you signed up on XLinic or were added to a platform announcement list.
                        <br>
                        <a href="{{ $unsubscribeUrl }}" style="color:#6b7280; text-decoration:underline;">Unsubscribe</a>
                        &nbsp;·&nbsp;
                        <a href="{{ config('app.url') }}" style="color:#6b7280; text-decoration:underline;">{{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'x-linic.com' }}</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
