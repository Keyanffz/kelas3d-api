<?php

namespace Database\Factories;

use App\Enums\ScheduleType;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSchedule>
 */
class ClassScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'type' => ScheduleType::Kelas,
            'day_of_week' => fake()->numberBetween(1, 5),
            'starts_at' => '08:00:00',
            'ends_at' => '16:00:00',
        ];
    }
}
