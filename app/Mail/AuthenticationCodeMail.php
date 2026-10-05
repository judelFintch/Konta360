<?php

namespace App\Mail;

use App\Models\User;
use App\Modules\Authentication\Enums\AuthenticationCodePurpose;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent synchronously: the hosting has no queue worker, and the user waits
 * for the code. The sender is MAIL_FROM_ADDRESS.
 */
class AuthenticationCodeMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly AuthenticationCodePurpose $purpose,
        public readonly string $code,
        public readonly int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose->subject().' — Konta360');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.authentication-code',
            text: 'mail.authentication-code-text',
            with: ['formattedCode' => implode(' ', str_split($this->code, 4))],
        );
    }
}
