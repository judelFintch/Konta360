<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Konta360</title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:12px;overflow:hidden;">
                <tr><td style="background:#4f46e5;padding:20px 32px;color:#ffffff;font-size:18px;font-weight:bold;">Konta360</td></tr>
                <tr><td style="padding:32px;">
                    <p style="margin:0 0 16px;font-size:16px;">Bonjour {{ $user->name }},</p>

                    @if ($granted)
                        <p style="margin:0 0 16px;font-size:14px;line-height:22px;color:#374151;">
                            Bonne nouvelle : l’équipe Konta360 offre à <strong>{{ $company->name }}</strong> l’accès complet à Konta360.
                        </p>
                        <ul style="margin:0 0 24px;padding-left:20px;font-size:14px;line-height:24px;color:#374151;">
                            <li>toutes les fonctionnalités : devis, factures, règlements, comptabilité, trésorerie ;</li>
                            <li>sans limite d’utilisateurs ni de factures ;</li>
                            <li>aucun abonnement à payer.</li>
                        </ul>
                    @else
                        <p style="margin:0 0 16px;font-size:14px;line-height:22px;color:#374151;">
                            L’accès offert dont bénéficiait <strong>{{ $company->name }}</strong> sur Konta360 a pris fin.
                        </p>
                        <p style="margin:0 0 24px;font-size:14px;line-height:22px;color:#374151;">
                            @if ($canStillWrite && $accessEndsOn)
                                Votre accès continue normalement jusqu’au <strong>{{ $accessEndsOn->format('d/m/Y') }}</strong>. Pensez à choisir une formule avant cette date.
                            @elseif ($canStillWrite)
                                Votre accès continue normalement selon votre abonnement en cours.
                            @else
                                Vos données restent consultables et exportables, mais la création et la modification sont suspendues jusqu’à la souscription d’une formule.
                            @endif
                        </p>
                    @endif

                    <p style="margin:0 0 24px;text-align:center;">
                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 24px;background:#4f46e5;border-radius:8px;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none;">{{ $actionLabel }}</a>
                    </p>

                    <p style="margin:0;font-size:13px;line-height:20px;color:#6b7280;">
                        Une question ? @if ($contact)Écrivez-nous à <a href="mailto:{{ $contact }}" style="color:#4f46e5;">{{ $contact }}</a>.@else Répondez simplement à cet e-mail.@endif
                    </p>
                </td></tr>
            </table>
            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">Vous recevez cet e-mail en tant qu’administrateur de {{ $company->name }} sur Konta360.</p>
        </td></tr>
    </table>
</body>
</html>
