<?php

use App\Enums\MemberRole;
use App\Enums\UserRole;
use App\Models\AppRule;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Models\User;
use Database\Seeders\AkunUjiSeeder;

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

test('seeder akun uji membuat koordinator, ketua, dan mahasiswa yang bisa login, dan aman diulang', function () {
    $kelas = Classroom::factory()->create(['slug' => 'data-science']);
    $ketuaLama = anggota($kelas, MemberRole::Admin, 'a1');

    $this->seed(AkunUjiSeeder::class);
    $this->seed(AkunUjiSeeder::class);

    $ketua = User::firstWhere('email', 'ketua@uji.test');
    $mahasiswa = User::firstWhere('email', 'mahasiswa@uji.test');

    expect(User::firstWhere('email', 'koordinator@uji.test')->role)->toBe(UserRole::Koordinator)
        ->and($kelas->members()->count())->toBe(3)
        ->and($ketua->isAdminOf($kelas))->toBeTrue()
        ->and($ketuaLama->isAdminOf($kelas))->toBeFalse()
        ->and($ketua->membershipIn($kelas)->seat_code)->toBe('a2')
        ->and($mahasiswa->membershipIn($kelas)->seat_code)->toBe('a3')
        ->and($mahasiswa->avatar)->not->toBeNull();

    $this->withHeader('Referer', 'http://localhost:3000')
        ->postJson('/api/login', ['email' => 'mahasiswa@uji.test', 'password' => AkunUjiSeeder::PASSWORD])
        ->assertOk();
});
