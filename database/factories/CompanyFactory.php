<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Plan;
use App\Modules\Companies\Services\CompanyProvisioner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'default_currency' => 'CDF',
            // A company that just signed up: on trial.
            'plan_id' => fn () => Plan::query()->where('code', 'pro')->value('id'),
            'trial_ends_at' => today()->addDays(29),
            'terms_version' => config('konta360.terms_version'),
            'terms_accepted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Company $company) => app(CompanyProvisioner::class)->provision($company));
    }

    /**
     * Trial over and nothing paid: read-only.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => today()->subDay(),
            'subscription_ends_at' => null,
        ]);
    }

    public function onPlan(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_id' => Plan::query()->where('code', $code)->value('id'),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended_at' => now(),
        ]);
    }
}
