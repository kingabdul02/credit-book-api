<?php

namespace App\Mail;

use App\Models\LegacyContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LegacyContactVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contact;
    public $verificationToken;
    public $accessCode;

    /**
     * Create a new message instance.
     */
    public function __construct(LegacyContact $contact, string $verificationToken, string $accessCode)
    {
        $this->contact = $contact;
        $this->verificationToken = $verificationToken;
        $this->accessCode = $accessCode;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Legacy Contact Nomination',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.legacy.verification',
            with: [
                'verificationUrl' => rtrim(config('app.url'), '/') . '/api/v1/legacy-contacts/verify-email?email=' . urlencode($this->contact->email) . '&token=' . $this->verificationToken,
                'accessCode' => $this->accessCode,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
