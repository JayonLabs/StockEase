<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Configure the model factory to assign a role after creation.
     * Removes the old 'role' column attribute and assigns via Spatie.
     */
    public function configure(): static
    {
        $tenantRoles = [Role::Admin, Role::Cashier, Role::Warehouse, Role::SuperAdmin];

        $roles = new \WeakMap;

        return $this
            ->afterMaking(function (User $user) use ($roles) {
                $rawAttributes = $user->getAttributes();

                if (array_key_exists('role', $rawAttributes)) {
                    $roles[$user] = $rawAttributes['role'];
                    $user->offsetUnset('role');
                }
            })
            ->afterCreating(function (User $user) use ($roles, $tenantRoles) {
                $intendedRole = $roles[$user] ?? null;

                if ($intendedRole && in_array($intendedRole, array_map(fn (Role $r) => $r->value, Role::cases()))) {
                    $user->assignRole($intendedRole);
                }

                if (! $user->hasAnyRole(array_map(fn (Role $r) => $r->value, Role::cases()))) {
                    $user->assignRole(fake()->randomElement($tenantRoles)->value);
                }
            });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
