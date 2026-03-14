<?php

namespace App\Mail;

use App\Models\OwnerUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OwnerUser $user,
        public string $password
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your XLinic Owner Portal Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.owner-password',
            with: [
                'user' => $this->user,
                'password' => $this->password,
                'loginUrl' => 'https://sys.x-linic.com/admin/login',
            ],
        );
    }
}
