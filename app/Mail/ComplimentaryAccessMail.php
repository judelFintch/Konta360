<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells a company administrator that Konta360 offers their company free
 * access, or that this offer has ended. Sent synchronously: the hosting
 * has no queue worker.
 */
class ComplimentaryAccessMail extends Mailable
{
    public function __construct(
        public readonly Company $company,
        public readonly User $user,
        public readonly bool $granted,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->granted
            ? "Konta360 vous offre l’accès — {$this->company->name}"
            : "Fin de l’accès offert à Konta360 — {$this->company->name}");
    }

    public function content(): Content
    {
        $status = $this->company->subscriptionStatus();

        return new Content(
            view: 'mail.complimentary-access',
            text: 'mail.complimentary-access-text',
            with: [
                // After a withdrawal: what the company can still do.
                'canStillWrite' => $status->allowsWriting(),
                'accessEndsOn' => $this->company->accessEndsOn(),
                'actionUrl' => $this->granted ? route('login') : route('subscription.show'),
                'actionLabel' => $this->granted ? 'Accéder à Konta360' : 'Choisir une formule',
                'contact' => config('konta360.billing.payment_instructions.contact'),
            ],
        );
    }
}
