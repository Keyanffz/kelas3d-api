<?php

use App\Enums\MemberRole;
use App\Enums\ScheduleType;
use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit test butuh config Laravel, tapi tidak butuh database.
pest()->extend(TestCase::class)->in('Unit');

function koordinator(): User
{
    return User::factory()->koordinator()->create();
}

// Peserta baru yang langsung jadi anggota (atau ketua) kelas itu.
function anggota(Classroom $classroom, MemberRole $role = MemberRole::Anggota, ?string $seat = null): User
{
    return ClassroomMember::factory()->for($classroom)->create(['role' => $role, 'seat_code' => $seat])->user;
}

// Kelas developer dengan jadwal Senin 08.00–16.00 dan istirahat 12.00–13.00.
function kelasDeveloper(array $attributes = []): Classroom
{
    $kelas = Classroom::factory()->create(['slug' => 'developer', 'name' => 'Developer', ...$attributes]);
    $kelas->schedules()->createMany([
        ['type' => ScheduleType::Kelas, 'day_of_week' => 1, 'starts_at' => '08:00', 'ends_at' => '16:00'],
        ['type' => ScheduleType::Istirahat, 'day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '13:00'],
    ]);

    return $kelas;
}

// Laptop yang sudah di-approve, dan request berikutnya memakai token-nya.
function laptop(User $user): Device
{
    $device = Device::factory()->approved()->for($user)->create(['os' => 'windows']);
    Sanctum::actingAs($device, guard: 'sanctum');

    return $device;
}

// Isi heartbeat seperti contoh di rencana.
function laporan(array $override = []): array
{
    return [
        'activity' => 'ngoding',
        'app_label' => 'VS Code',
        'project' => 'toko-online',
        'file_name' => 'auth.ts',
        'language' => 'TypeScript',
        'idle_seconds' => 12,
        'local_ip' => '192.168.10.23',
        'agent_version' => '0.1.0',
        'sent_at' => now()->toIso8601String(),
        'queued' => [],
        ...$override,
    ];
}
