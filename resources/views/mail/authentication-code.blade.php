<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $purpose->subject() }}</title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:12px;overflow:hidden;">
                <tr><td style="background:#4f46e5;padding:20px 32px;color:#ffffff;font-size:18px;font-weight:bold;">Konta360</td></tr>
                <tr><td style="padding:32px;">
                    <p style="margin:0 0 8px;font-size:16px;">Bonjour {{ $user->name }},</p>
                    <p style="margin:0 0 24px;font-size:14px;line-height:22px;color:#374151;">{{ $purpose->intro() }}</p>
                    <p style="margin:0 0 24px;text-align:center;">
                        <span style="display:inline-block;padding:16px 24px;background:#eef2ff;border-radius:10px;font-family:'Courier New',monospace;font-size:32px;font-weight:bold;letter-spacing:6px;color:#3730a3;">{{ $formattedCode }}</span>
                    </p>
                    <p style="margin:0 0 8px;font-size:13px;color:#6b7280;">Ce code est valable {{ $ttlMinutes }} minutes et ne peut servir qu’une fois.</p>
                    <p style="margin:0;font-size:13px;color:#6b7280;">Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail et ne communiquez ce code à personne : l’équipe Konta360 ne vous le demandera jamais.</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
