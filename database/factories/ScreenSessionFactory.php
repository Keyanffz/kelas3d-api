<?php

namespace Database\Factories;

use App\Enums\ScreenStatus;
use App\Models\Classroom;
use App\Models\ScreenSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreenSession>
 */
class ScreenSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'viewer_id' => User::factory()->koordinator(),
            'target_id' => User::factory(),
            'status' => ScreenStatus::MenungguKetua,
            'expires_at' => now()->addSeconds(config('kelas3d.screen.request_ttl')),
        ];
    }
}
