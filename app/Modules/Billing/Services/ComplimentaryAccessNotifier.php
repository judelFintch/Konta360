<?php

namespace App\Modules\Billing\Services;

use App\Mail\ComplimentaryAccessMail;
use App\Models\Company;
use App\Models\PlatformEvent;
use App\Models\User;
use App\Modules\Administration\Enums\Role;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Emails the active administrators of a company when the platform grants
 * or withdraws its complimentary access. One email per administrator, so
 * that no one sees the others' addresses. A failed delivery never undoes
 * the access change: it is reported to the operator and logged.
 */
class ComplimentaryAccessNotifier
{
    /**
     * @return array{sent: list<string>, failed: list<string>}
     */
    public function notify(Company $company, bool $granted, ?User $actor = null): array
    {
        $recipients = $company->users()
            ->role(Role::Administrateur->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $sent = [];
        $failed = [];
        foreach ($recipients as $user) {
            try {
                Mail::to($user)->send(new ComplimentaryAccessMail($company, $user, $granted));
                $sent[] = $user->email;
            } catch (TransportExceptionInterface $exception) {
                report($exception);
                $failed[] = $user->email;
            }
        }

        if ($sent !== [] || $failed !== []) {
            PlatformEvent::record(
                $actor,
                $company,
                'exemption_email',
                ($granted ? 'E-mail d’accès offert' : 'E-mail de fin d’accès offert').' envoyé à '.count($sent).' administrateur(s)'.($failed ? ', échec pour '.count($failed) : ''),
                ['granted' => $granted, 'sent' => $sent, 'failed' => $failed],
            );
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Flash messages for the operator.
     *
     * @param  array{sent: list<string>, failed: list<string>}  $result
     * @return array{success: ?string, warning: ?string}
     */
    public static function summary(array $result): array
    {
        return [
            'success' => $result['sent'] ? 'E-mail envoyé à '.implode(', ', $result['sent']).'.' : null,
            'warning' => match (true) {
                $result['failed'] !== [] => 'L’e-mail n’a pas pu être envoyé à '.implode(', ', $result['failed']).'. Vérifiez la configuration e-mail puis utilisez « Renvoyer l’e-mail ».',
                $result['sent'] === [] => 'Aucun administrateur actif à prévenir dans cette société : aucun e-mail envoyé.',
                default => null,
            },
        ];
    }
}
