<?php

namespace App\Mail;

use App\Models\OnboardingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public OnboardingRequest $request,
        public string $reason
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Regarding Your XLinic Application',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.onboarding.rejected',
            with: [
                'request' => $this->request,
                'reason' => $this->reason,
            ],
        );
    }
}
