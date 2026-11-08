<?php

namespace Database\Factories;

use App\Enums\HelpStatus;
use App\Models\Classroom;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HelpRequest>
 */
class HelpRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'classroom_id' => Classroom::factory(),
            'note' => fake()->sentence(),
            'status' => HelpStatus::Menunggu,
        ];
    }
}
