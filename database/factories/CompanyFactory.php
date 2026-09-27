<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

// ══════════════════════════════════════════════════════════════════
//  Massar — CompanyFactory
//  Location: database/factories/CompanyFactory.php
//
//  Test partner organisations. States:
//    suspended() → switched off by the Super Admin
//    lapsed()    → subscription ended yesterday
// ══════════════════════════════════════════════════════════════════

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name'                 => fake()->company(),
            'name_ar'              => null,
            'type'                 => 'ngo',
            'seat_limit'           => 5,
            'subscription_ends_at' => now()->addYear(),
            'is_active'            => true,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function lapsed(): static
    {
        return $this->state(fn () => ['subscription_ends_at' => now()->subDay()]);
    }
}
