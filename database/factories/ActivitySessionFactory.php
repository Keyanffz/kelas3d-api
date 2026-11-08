<?php

namespace Database\Factories;

use App\Enums\Activity;
use App\Enums\PresenceState;
use App\Models\ActivitySession;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivitySession>
 */
class ActivitySessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'classroom_id' => Classroom::factory(),
            'state' => PresenceState::DiKelas,
            'activity' => Activity::Ngoding,
            'app_label' => 'VS Code',
            'project' => fake()->slug(2),
            'language' => 'PHP',
            'started_at' => now()->subMinutes(30),
            'ended_at' => now(),
            'duration_seconds' => 30 * 60,
        ];
    }
}
