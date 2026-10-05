<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

/**
 * Creates a Konta360 operator account. It belongs to no company and only
 * reaches the platform area (ADR 0002 § 8).
 */
#[Signature('konta360:platform-admin {email} {--name=Support Konta360}')]
#[Description('Crée un administrateur de la plateforme (rattaché à aucune société)')]
class CreatePlatformAdmin extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower($this->argument('email'));

        if (User::query()->where('email', $email)->exists()) {
            $this->error('Cette adresse est déjà utilisée par un compte. Un administrateur de la plateforme doit avoir son propre compte, sans société.');

            return self::FAILURE;
        }

        $password = password(label: 'Mot de passe', required: true, validate: fn (string $value) => mb_strlen($value) < 12
            ? 'Au moins 12 caractères.'
            : null);

        $user = new User(['name' => $this->option('name'), 'email' => $email, 'password' => $password]);
        $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => now()])->save();

        $this->info("Administrateur de la plateforme créé : {$email}");

        return self::SUCCESS;
    }
}
