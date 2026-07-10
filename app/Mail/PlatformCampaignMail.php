<?php

namespace App\Mail;

use App\Models\PlatformEmailCampaign;
use App\Models\PlatformEmailCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * The actual per-recipient email. Built from the frozen
 * campaign_recipients.rendered_body_html (AI mode) OR the campaign's
 * body_html with token replacement (non-AI mode).
 *
 * List-Unsubscribe headers are injected here so every provider
 * (Gmail's "Unsubscribe" button, Apple Mail, Outlook) can offer the
 * one-click unsubscribe UX. `List-Unsubscribe-Post: List-Unsubscribe=One-Click`
 * is what makes RFC 8058 one-click sends work.
 */
class PlatformCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PlatformEmailCampaign $campaign,
        public PlatformEmailCampaignRecipient $recipient,
        public string $renderedBody,
        public string $unsubscribeUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        // From address priority for MARKETING campaigns:
        //   1. Campaign's own from_address (per-campaign override)
        //   2. platform_email.marketing_from_address setting
        //      (platform-wide default; kept separate from
        //       config('mail.from.address') which is noreply@ for
        //       transactional mail)
        //   3. config('mail.from.address') — last resort
        $fromAddress = $this->campaign->from_address
            ?: \App\Models\PlatformSetting::get('platform_email.marketing_from_address')
            ?: config('mail.from.address');

        $fromName = $this->campaign->from_name
            ?: \App\Models\PlatformSetting::get('platform_email.marketing_from_name')
            ?: config('mail.from.name');

        // Same layered resolution for Reply-To.
        $replyToAddress = $this->campaign->reply_to
            ?: \App\Models\PlatformSetting::get('platform_email.marketing_reply_to')
            ?: config('mail.reply_to.address');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: filled($replyToAddress)
                ? [new Address($replyToAddress)]
                : [],
            subject: $this->campaign->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.platform-campaign',
            with: [
                'bodyHtml' => $this->renderedBody,
                'preheader' => $this->campaign->preheader ?? '',
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                // RFC 8058 one-click unsubscribe. Gmail, Apple Mail, Outlook
                // show the built-in "Unsubscribe" affordance next to the
                // sender name when both headers are present.
                'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                'X-Platform-Campaign' => (string) $this->campaign->id,
                'X-Platform-Recipient' => (string) $this->recipient->id,
            ],
        );
    }
}
