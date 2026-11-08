<?php

namespace Database\Factories;

use App\Enums\NudgeStatus;
use App\Models\Classroom;
use App\Models\Nudge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nudge>
 */
class NudgeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'from_user_id' => User::factory()->koordinator(),
            'to_user_id' => User::factory(),
            'about_user_id' => User::factory(),
            'message' => fake()->sentence(),
            'status' => NudgeStatus::Terkirim,
        ];
    }
}
