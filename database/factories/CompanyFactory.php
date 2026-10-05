<?php

namespace Database\Factories;

use App\Models\Company;
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
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Company $company) => app(CompanyProvisioner::class)->provision($company));
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended_at' => now(),
        ]);
    }
}
