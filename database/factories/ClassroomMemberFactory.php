<?php

namespace Database\Factories;

use App\Enums\MemberRole;
use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassroomMember>
 */
class ClassroomMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'user_id' => User::factory(),
            'role' => MemberRole::Anggota,
            'seat_code' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => MemberRole::Admin]);
    }
}
