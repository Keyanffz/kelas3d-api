<?php

namespace Database\Seeders;

use App\Enums\ScheduleType;
use App\Models\Classroom;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    // Contoh jadwal: Senin–Jumat 08.00–16.00. Istirahat 12.00–13.00, Jumat mulai 11.30 buat salat Jumat.
    public function run(): void
    {
        foreach (['developer' => 'Developer', 'data-science' => 'Data Science'] as $slug => $name) {
            $classroom = Classroom::create(['name' => $name, 'slug' => $slug]);

            foreach (range(1, 5) as $day) {
                $classroom->schedules()->createMany([
                    ['type' => ScheduleType::Kelas, 'day_of_week' => $day, 'starts_at' => '08:00', 'ends_at' => '16:00'],
                    ['type' => ScheduleType::Istirahat, 'day_of_week' => $day, 'starts_at' => $day === 5 ? '11:30' : '12:00', 'ends_at' => '13:00'],
                ]);
            }
        }
    }
}
