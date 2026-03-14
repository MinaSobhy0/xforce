<?php

namespace Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\Tenant;

class UserLimitExceededMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public int $currentCount,
        public int $limit
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[XLinic] Tenant '{$this->tenant->name}' Exceeded User Limit",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'auth::emails.user-limit-exceeded-admin',
            with: [
                'tenant' => $this->tenant,
                'currentCount' => $this->currentCount,
                'limit' => $this->limit,
                'overage' => $this->currentCount - $this->limit,
                'gracePeriodEnds' => $this->tenant->users_overage_at?->addDays(14),
            ],
        );
    }
}
