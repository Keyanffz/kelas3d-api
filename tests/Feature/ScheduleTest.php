<?php

use App\Enums\MemberRole;
use App\Models\Classroom;
use App\Models\ClassSchedule;

beforeEach(fn () => $this->kelas = Classroom::factory()->create(['slug' => 'developer']));

test('anggota melihat jadwal kelasnya', function () {
    ClassSchedule::factory()->for($this->kelas)->create(['day_of_week' => 1]);

    $this->actingAs(anggota($this->kelas))
        ->getJson('/api/classrooms/developer/schedules')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.starts_at', '08:00')
        ->assertJsonPath('data.0.type', 'kelas');
});

test('koordinator menambah jadwal', function () {
    $this->actingAs(koordinator())
        ->postJson('/api/classrooms/developer/schedules', [
            'type' => 'istirahat',
            'day_of_week' => 1,
            'starts_at' => '12:00',
            'ends_at' => '13:00',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'istirahat');

    $this->assertDatabaseHas('class_schedules', [
        'classroom_id' => $this->kelas->id,
        'type' => 'istirahat',
        'starts_at' => '12:00:00',
    ]);
});

test('jadwal divalidasi', function () {
    $this->actingAs(koordinator())
        ->postJson('/api/classrooms/developer/schedules', [
            'type' => 'libur',
            'day_of_week' => 8,
            'starts_at' => '13:00',
            'ends_at' => '12:00',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'day_of_week', 'ends_at']);
});

test('koordinator mengubah lalu menghapus jadwal', function () {
    $jadwal = ClassSchedule::factory()->for($this->kelas)->create();
    $koordinator = koordinator();

    $this->actingAs($koordinator)
        ->putJson("/api/classrooms/developer/schedules/{$jadwal->id}", [
            'type' => 'kelas',
            'day_of_week' => 2,
            'starts_at' => '09:00',
            'ends_at' => '15:00',
        ])
        ->assertOk()
        ->assertJsonPath('data.day_of_week', 2)
        ->assertJsonPath('data.ends_at', '15:00');

    $this->actingAs($koordinator)
        ->deleteJson("/api/classrooms/developer/schedules/{$jadwal->id}")
        ->assertNoContent();

    $this->assertModelMissing($jadwal);
});

test('ketua kelas tidak boleh mengelola jadwal', function () {
    $this->actingAs(anggota($this->kelas, MemberRole::Admin))
        ->postJson('/api/classrooms/developer/schedules', [
            'type' => 'kelas',
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '16:00',
        ])
        ->assertForbidden();
});

test('jadwal kelas lain tidak bisa diakses lewat kelas ini', function () {
    $lain = ClassSchedule::factory()->create();

    $this->actingAs(koordinator())
        ->deleteJson("/api/classrooms/developer/schedules/{$lain->id}")
        ->assertNotFound();
});
