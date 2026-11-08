<?php

namespace Database\Factories;

use App\Enums\Activity;
use App\Enums\PresenceState;
use App\Models\Classroom;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presence>
 */
class PresenceFactory extends Factory
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
            'last_heartbeat_at' => now(),
            'state_since' => now(),
        ];
    }
}
