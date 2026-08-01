<?php

namespace App\Services\PlatformEmail;

use App\Models\PlatformEmailCampaignRecipient;
use Illuminate\Support\Facades\URL;

/**
 * Rewrites a campaign body for a specific recipient before it hits
 * the Mailable:
 *   1. Every http/https link becomes a signed click-tracker redirect.
 *   2. An open-pixel <img> is appended at the end.
 *
 * Idempotent-ish — we skip rewriting already-tracked URLs (guarded
 * by the '/platform/track/click' substring) so re-processing doesn't
 * nest tracker redirects.
 */
class BodyRewriter
{
    public function rewrite(string $bodyHtml, PlatformEmailCampaignRecipient $recipient): string
    {
        $body = $this->rewriteLinks($bodyHtml, $recipient);
        $body .= $this->openPixel($recipient);

        return $body;
    }

    protected function rewriteLinks(string $body, PlatformEmailCampaignRecipient $recipient): string
    {
        return preg_replace_callback(
            '#href\s*=\s*"(https?://[^"]+)"#i',
            function ($match) use ($recipient) {
                $target = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
                if (str_contains($target, '/platform/track/click')) {
                    return $match[0];
                }
                if (str_contains($target, '/platform/unsubscribe')) {
                    return $match[0]; // leave signed unsub links alone
                }
                $tracked = URL::signedRoute('platform.track.click', [
                    'r' => $recipient->id,
                    'u' => $target,
                ]);

                return 'href="'.$tracked.'"';
            },
            $body,
        ) ?? $body;
    }

    protected function openPixel(PlatformEmailCampaignRecipient $recipient): string
    {
        $url = URL::signedRoute('platform.track.open', ['r' => $recipient->id]);

        return "\n".'<img src="'.e($url).'" alt="" width="1" height="1" style="display:block;width:1px;height:1px;border:0;">'."\n";
    }
}
