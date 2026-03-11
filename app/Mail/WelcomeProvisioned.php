<?php

namespace App\Mail;

use App\Models\OnboardingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeProvisioned extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public OnboardingRequest $request,
        public ?string $temporaryPassword = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to XLinic - Your Account is Ready!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.onboarding.welcome',
            with: [
                'request' => $this->request,
                'loginUrl' => "https://{$this->request->slug}.x-linic.com/admin",
                'temporaryPassword' => $this->temporaryPassword,
            ],
        );
    }
}
