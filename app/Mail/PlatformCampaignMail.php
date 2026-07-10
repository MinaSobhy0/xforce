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
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                $this->campaign->from_name ?: config('mail.from.name'),
            ),
            replyTo: filled($this->campaign->reply_to)
                ? [new Address($this->campaign->reply_to)]
                : (filled(config('mail.reply_to.address'))
                    ? [new Address(config('mail.reply_to.address'), config('mail.reply_to.name'))]
                    : []),
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
