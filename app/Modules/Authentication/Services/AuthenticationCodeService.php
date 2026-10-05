<?php

namespace App\Modules\Authentication\Services;

use App\Mail\AuthenticationCodeMail;
use App\Models\AuthenticationCode;
use App\Models\User;
use App\Modules\Authentication\Enums\AuthenticationCodePurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Eight-digit codes sent by email (ADR 0004). A code is valid for a few
 * minutes, for a limited number of attempts, and only the latest one sent
 * for a given purpose works. Only a keyed hash of it is stored.
 */
class AuthenticationCodeService
{
    public const LENGTH = 8;

    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_DELAY_SECONDS = 60;

    /**
     * Sends a new code, replacing any previous one for the same purpose.
     *
     * @throws ValidationException when a code was sent too recently.
     */
    public function send(User $user, AuthenticationCodePurpose $purpose): void
    {
        $wait = $this->secondsBeforeResend($user, $purpose);
        if ($wait > 0) {
            throw ValidationException::withMessages([
                'code' => "Un code vient d’être envoyé. Vous pourrez en demander un nouveau dans {$wait} secondes.",
            ]);
        }

        $code = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);
        $record = new AuthenticationCode;
        $record->forceFill([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => $this->hash($user, $purpose, $code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ])->save();

        try {
            Mail::to($user)->send(new AuthenticationCodeMail($user, $purpose, $code, self::TTL_MINUTES));
        } catch (TransportExceptionInterface $exception) {
            $record->delete();
            report($exception);

            $message = str_contains(strtolower($exception->getMessage()), 'invalid recipient domain')
                ? 'Le domaine de votre adresse e-mail n’est pas reconnu par le serveur de messagerie. Vérifiez l’adresse saisie. Si elle est correcte, contactez le support.'
                : 'Nous ne parvenons pas à envoyer votre code pour le moment. Réessayez dans quelques instants. Si le problème persiste, contactez le support.';

            throw ValidationException::withMessages(['mail' => $message]);
        }

        $this->pending($user, $purpose)->where('id', '!=', $record->id)->update(['consumed_at' => now()]);
    }

    /**
     * Consumes the code if it matches; otherwise counts the attempt.
     *
     * @throws ValidationException with a message for the « code » field.
     */
    public function verify(User $user, AuthenticationCodePurpose $purpose, string $input): void
    {
        $code = preg_replace('/\D/', '', $input);
        $record = $this->pending($user, $purpose)->latest('id')->first();

        if (! $record || ! $record->isUsable()) {
            throw ValidationException::withMessages(['code' => 'Ce code a expiré. Demandez-en un nouveau.']);
        }
        if ($record->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => 'Trop d’essais avec ce code. Demandez-en un nouveau.']);
        }

        if (strlen($code) !== self::LENGTH || ! hash_equals($record->code_hash, $this->hash($user, $purpose, $code))) {
            $record->increment('attempts');
            $left = self::MAX_ATTEMPTS - $record->attempts;
            throw ValidationException::withMessages(['code' => $left > 0
                ? "Code incorrect. Il vous reste {$left} essai(s)."
                : 'Code incorrect. Demandez un nouveau code.']);
        }

        $record->forceFill(['consumed_at' => now()])->save();
    }

    public function secondsBeforeResend(User $user, AuthenticationCodePurpose $purpose): int
    {
        $last = AuthenticationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->latest('id')
            ->value('created_at');

        if (! $last) {
            return 0;
        }

        return max(0, (int) ceil(self::RESEND_DELAY_SECONDS - now()->diffInSeconds($last, absolute: true)));
    }

    private function pending(User $user, AuthenticationCodePurpose $purpose): Builder
    {
        return AuthenticationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at');
    }

    /**
     * Keyed with the application key: a database dump alone does not allow
     * guessing codes.
     */
    private function hash(User $user, AuthenticationCodePurpose $purpose, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$purpose->value.'|'.$code, config('app.key'));
    }
}
