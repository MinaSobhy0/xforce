<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformEmailCampaignRecipient;
use App\Models\PlatformEmailListMember;
use App\Models\PlatformEmailSuppression;
use Illuminate\Http\Request;

/**
 * Public unsubscribe endpoint. Called from the footer link in every
 * platform campaign AND from the RFC 8058 one-click POST that Gmail /
 * Apple Mail / Outlook fires when a recipient uses their inbox's
 * built-in "Unsubscribe" button.
 *
 * The signed URL carries campaign_id + email as query params. Signature
 * validation is done by the `signed` route middleware.
 */
class UnsubscribeController extends Controller
{
    /**
     * GET — shown when the user clicks the footer link. Renders a
     * confirmation page so accidental prefetch scans (Gmail / Outlook
     * prefetchers) don't wrongly opt someone out.
     */
    public function show(Request $request): mixed
    {
        [$email, $campaignId] = $this->extract($request);

        return view('platform.unsubscribe.confirm', [
            'email' => $email,
            'campaignId' => $campaignId,
            'confirmUrl' => $request->fullUrl(),
        ]);
    }

    /**
     * POST — one-click unsubscribe (RFC 8058). Suppresses immediately.
     */
    public function unsubscribe(Request $request): mixed
    {
        [$email, $campaignId] = $this->extract($request);

        PlatformEmailSuppression::add(
            email: $email,
            reason: PlatformEmailSuppression::REASON_UNSUBSCRIBED,
            extra: ['campaign_id' => $campaignId],
        );

        // Per-list unsubscribe (soft) — so a re-add to a different list
        // still respects the recipient's choice unless it's a genuine
        // resubscribe flow.
        PlatformEmailListMember::query()
            ->where('email', $email)
            ->update(['unsubscribed_at' => now()]);

        // Mark the campaign recipient row + bump the counter.
        if ($campaignId) {
            $updated = PlatformEmailCampaignRecipient::query()
                ->where('campaign_id', $campaignId)
                ->where('email', $email)
                ->update(['status' => PlatformEmailCampaignRecipient::STATUS_UNSUBSCRIBED]);
            if ($updated) {
                \App\Models\PlatformEmailCampaign::query()
                    ->where('id', $campaignId)
                    ->increment('unsubscribed_count');
            }
        }

        return view('platform.unsubscribe.done', [
            'email' => $email,
        ]);
    }

    protected function extract(Request $request): array
    {
        $email = mb_strtolower(trim((string) $request->query('email', '')));
        $campaignId = (int) $request->query('campaign', 0) ?: null;

        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 400, 'Missing or invalid email');

        return [$email, $campaignId];
    }
}
