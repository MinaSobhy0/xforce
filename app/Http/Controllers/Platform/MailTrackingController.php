<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformEmailCampaign;
use App\Models\PlatformEmailCampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Open-pixel + click-tracking endpoints. Both signed so nobody can
 * inflate counters by spamming the URL.
 */
class MailTrackingController extends Controller
{
    /**
     * 1x1 transparent GIF. When a recipient opens the email, this URL
     * is fetched — we bump opened_at (once) and the campaign counter.
     */
    public function open(Request $request): Response
    {
        $recipient = $this->recipient($request);

        if ($recipient && $recipient->opened_at === null) {
            $recipient->forceFill(['opened_at' => now()])->save();
            $this->incrementCampaign($recipient->campaign_id, 'opened_count');
        }

        // 1x1 transparent GIF (43 bytes).
        return response(
            base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
            200,
            [
                'Content-Type' => 'image/gif',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ],
        );
    }

    /**
     * Click-through redirect. `u` query param carries the encoded target;
     * we log first_clicked_at then 302 to the target.
     */
    public function click(Request $request)
    {
        $recipient = $this->recipient($request);
        $target = (string) $request->query('u', '');

        // Loose safety: reject anything that isn't http/https to avoid
        // being used as a phishing open-redirector.
        if (! preg_match('#^https?://#i', $target)) {
            abort(400, 'Bad target URL');
        }

        if ($recipient && $recipient->first_clicked_at === null) {
            $recipient->forceFill(['first_clicked_at' => now()])->save();
            $this->incrementCampaign($recipient->campaign_id, 'clicked_count');
        }

        return redirect()->away($target, 302);
    }

    protected function recipient(Request $request): ?PlatformEmailCampaignRecipient
    {
        $id = (int) $request->query('r', 0);

        return $id ? PlatformEmailCampaignRecipient::find($id) : null;
    }

    protected function incrementCampaign(int $campaignId, string $column): void
    {
        PlatformEmailCampaign::query()->where('id', $campaignId)->increment($column);
    }
}
