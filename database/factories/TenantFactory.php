<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 *
 * Note: creating a Tenant fires the provisioning pipeline (creates and migrates
 * a real tenant database). Prefer the App\Actions\Tenancy\CreateTenant action
 * when you also need a domain and seeded roles.
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
        ];
    }
}
