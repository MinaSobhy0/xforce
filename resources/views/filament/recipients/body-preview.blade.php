<div
    @if($isRtl) dir="rtl" style="text-align:right;" @endif
    class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-6 max-h-[70vh] overflow-y-auto"
    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; font-size:15px; line-height:1.6; color:inherit;"
>
    @php
        // Reuse the same normalization the mail Blade applies so the
        // preview matches what actually leaves Postfix — <p> spacing,
        // RTL/LTR bidi wrapping, etc.
        $normalized = trim($bodyHtml);
        if (! preg_match('/<(p|h[1-3]|ul|ol|div|blockquote)\b/i', $normalized)) {
            $blocks = preg_split('/\R{2,}/', $normalized);
            $normalized = collect($blocks)
                ->map(fn ($b) => trim($b))
                ->filter()
                ->map(fn ($b) => '<p>'.nl2br($b).'</p>')
                ->implode("\n");
        }
        if ($isRtl) {
            foreach (['p', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote'] as $tag) {
                $normalized = preg_replace_callback(
                    '/<'.$tag.'((?:\s[^>]*)?)>/i',
                    function ($m) use ($tag) {
                        if (stripos($m[1], 'dir=') !== false) return $m[0];
                        return '<'.$tag.$m[1].' dir="rtl">';
                    },
                    $normalized,
                );
            }
            $normalized = preg_replace_callback(
                '/(?<![>\w])([A-Za-z][A-Za-z0-9._\-@:\/]*[A-Za-z0-9])(?![^<]*>)/u',
                fn ($m) => '<span dir="ltr" style="unicode-bidi:isolate;">'.$m[1].'</span>',
                $normalized,
            );
        }
        $align = $isRtl ? 'text-align:right;' : '';
        $listPad = $isRtl ? 'padding-right:24px;' : 'padding-left:24px;';
        $spaced = preg_replace('/<p(\s[^>]*)?>/i',  '<p$1 style="margin:0 0 16px 0;'.$align.'">', $normalized);
        $spaced = preg_replace('/<h2(\s[^>]*)?>/i', '<h2$1 style="margin:24px 0 8px 0; font-size:20px; line-height:1.3;'.$align.'">', $spaced);
        $spaced = preg_replace('/<h3(\s[^>]*)?>/i', '<h3$1 style="margin:20px 0 8px 0; font-size:17px; line-height:1.3;'.$align.'">', $spaced);
        $spaced = preg_replace('/<ul(\s[^>]*)?>/i', '<ul$1 style="margin:0 0 16px 0;'.$listPad.$align.'">', $spaced);
        $spaced = preg_replace('/<li(\s[^>]*)?>/i', '<li$1 style="margin:0 0 6px 0;'.$align.'">', $spaced);
    @endphp
    {!! $spaced !!}
</div>
