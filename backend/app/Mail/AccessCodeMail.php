<?php

namespace App\Mail;

use App\Models\AccessCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AccessCode $accessCode,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your LinkForge Access Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.access-code',
            with: [
                'code'        => $this->accessCode->code,
                'email'       => $this->accessCode->email,
                'description' => $this->accessCode->description,
                'expiresAt'   => $this->accessCode->expires_at,
                'appUrl'      => config('app.url'),
            ],
        );
    }
}
