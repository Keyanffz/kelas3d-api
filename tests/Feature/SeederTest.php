<?php

use App\Enums\UserRole;
use App\Models\AppRule;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Models\User;

test('seeder membuat koordinator dari env, dua kelas, jadwal, dan app_rules', function () {
    config(['kelas3d.koordinator' => ['email' => 'koor@example.com', 'password' => 'rahasia123']]);

    $this->seed();

    expect(User::firstWhere('email', 'koor@example.com')->role)->toBe(UserRole::Koordinator)
        ->and(Classroom::orderBy('id')->pluck('slug')->all())->toBe(['developer', 'data-science'])
        ->and(ClassSchedule::count())->toBe(20)
        ->and(AppRule::where('activity', 'vibe_coding')->count())->toBe(3);
});

test('seeder berhenti kalau akun koordinator belum diisi di env', function () {
    config(['kelas3d.koordinator' => ['email' => null, 'password' => null]]);

    $this->seed();
})->throws(RuntimeException::class);
