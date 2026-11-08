<?php

namespace Database\Factories;

use App\Enums\DeviceOs;
use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'hostname' => 'laptop-'.fake()->unique()->userName(),
            'os' => fake()->randomElement(DeviceOs::cases()),
            'agent_version' => '0.1.0',
            'status' => DeviceStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status' => DeviceStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
