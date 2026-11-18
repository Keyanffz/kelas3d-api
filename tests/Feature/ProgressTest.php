<?php

use App\Enums\Activity;
use App\Enums\PresenceState;
use App\Enums\ScheduleType;
use App\Models\ActivitySession;
use App\Support\Progress;

beforeEach(function () {
    $this->kelas = kelasDeveloper();
    // Jadwal Senin–Rabu; Kamis sampai Minggu bukan hari kelas.
    $this->kelas->schedules()->createMany([
        ['type' => ScheduleType::Kelas, 'day_of_week' => 2, 'starts_at' => '08:00', 'ends_at' => '16:00'],
        ['type' => ScheduleType::Kelas, 'day_of_week' => 3, 'starts_at' => '08:00', 'ends_at' => '16:00'],
    ]);
    $this->peserta = anggota($this->kelas, seat: 'a1');
    $this->sesi = fn (string $mulai, int $menit, PresenceState $state = PresenceState::DiKelas, ?Activity $activity = Activity::Ngoding) => ActivitySession::factory()
        ->for($this->peserta)->create([
            'classroom_id' => $this->kelas->id,
            'state' => $state,
            'activity' => $activity,
            'started_at' => $mulai,
            'ended_at' => now(),
            'duration_seconds' => $menit * 60,
        ]);
});

test('progresku menghitung jam aktif dan jam ngoding per hari', function () {
    $this->travelTo('2026-11-18 10:00'); // Rabu
    ($this->sesi)('2026-11-16 08:00', 60);
    ($this->sesi)('2026-11-16 09:00', 30, activity: Activity::VibeCoding);
    ($this->sesi)('2026-11-16 09:30', 20, activity: Activity::Browsing);
    ($this->sesi)('2026-11-16 10:00', 45, PresenceState::Istirahat);
    // Sesi yang masih jalan dihitung sampai sekarang.
    ActivitySession::factory()->for($this->peserta)->create([
        'classroom_id' => $this->kelas->id, 'activity' => Activity::NgolahData,
        'started_at' => '2026-11-18 09:50', 'ended_at' => null, 'duration_seconds' => null,
    ]);

    $this->actingAs($this->peserta)->getJson('/api/progress')
        ->assertOk()
        ->assertJsonPath('data.days', [
            ['date' => '2026-11-16', 'aktif' => 110 * 60, 'ngoding' => 90 * 60],
            ['date' => '2026-11-18', 'aktif' => 600, 'ngoding' => 600],
        ]);
});

test('streak tidak putus di hari tanpa jadwal dan hari ini yang belum aktif', function () {
    $this->travelTo('2026-11-24 07:00'); // Selasa, belum aktif
    ($this->sesi)('2026-11-11 09:00', 30); // Rabu
    // Senin 16 Nov bolos, lalu Selasa–Rabu, lewat akhir pekan, lalu Senin.
    ($this->sesi)('2026-11-17 09:00', 30);
    ($this->sesi)('2026-11-18 09:00', 30);
    ($this->sesi)('2026-11-21 09:00', 30); // Sabtu, bonus
    ($this->sesi)('2026-11-23 09:00', 30);
    // Istirahat saja tidak dihitung aktif.
    ($this->sesi)('2026-11-16 09:00', 30, PresenceState::Istirahat);

    $this->actingAs($this->peserta)->getJson('/api/progress')
        ->assertJsonPath('data.streak', ['sekarang' => 4, 'terbaik' => 4]);

    $this->travelTo('2026-11-25 07:00'); // Selasa kemarin bolos
    $this->actingAs($this->peserta)->getJson('/api/progress')
        ->assertJsonPath('data.streak', ['sekarang' => 0, 'terbaik' => 4]);
});

test('progresku cuma milik sendiri dan kosong untuk akun baru', function () {
    $teman = anggota($this->kelas, seat: 'a2');
    ($this->sesi)('2026-11-16 08:00', 60);

    $this->actingAs($teman)->getJson('/api/progress')
        ->assertOk()
        ->assertJsonPath('data.days', [])
        ->assertJsonPath('data.streak', ['sekarang' => 0, 'terbaik' => 0]);
});

test('xp dari menit ngoding dan streak terbaik, dan level membuka aksesoris', function () {
    $this->travelTo('2026-11-18 10:00');
    ($this->sesi)('2026-11-16 08:00', 200); // ngoding
    ($this->sesi)('2026-11-17 08:00', 45, activity: Activity::Browsing); // aktif tapi bukan ngoding
    ($this->sesi)('2026-11-17 09:00', 30, PresenceState::Istirahat);

    // 200 menit + 2 hari streak × 20 = 240 XP: level 2 (100–300).
    $this->actingAs($this->peserta)->getJson('/api/progress')
        ->assertJsonPath('data.xp', ['total' => 240, 'level' => 2, 'dari' => 100, 'ke' => 300])
        ->assertJsonPath('data.aksesoris.0', ['id' => 'topi_1', 'slot' => 'topi', 'nama' => 'Topi pet', 'level' => 2, 'terbuka' => true])
        ->assertJsonPath('data.aksesoris.1.terbuka', false)
        ->assertJsonPath('data.dipakai', ['topi' => null, 'meja' => null]);

    expect(Progress::level(0))->toBe(1)
        ->and(Progress::level(299))->toBe(2)
        ->and(Progress::level(300))->toBe(3)
        ->and(Progress::level(1000))->toBe(5);
});

test('peserta cuma bisa memakai aksesoris yang sudah terbuka, dan ganti avatar tidak melepasnya', function () {
    $this->travelTo('2026-11-18 10:00');
    ($this->sesi)('2026-11-16 08:00', 120);
    $this->peserta->update(['avatar' => ['tipe_badan' => 'laki', 'rambut' => 'rambut_1', 'warna' => ['kulit' => '#e0ac69', 'rambut' => '#2b1b17', 'celana' => '#1f2937']]]);

    $this->actingAs($this->peserta)->putJson('/api/aksesoris', ['topi' => 'topi_1', 'meja' => 'meja_1'])
        ->assertUnprocessable()->assertJsonValidationErrors(['meja' => 'level 3']);
    $this->actingAs($this->peserta)->putJson('/api/aksesoris', ['topi' => 'meja_1', 'meja' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('topi');

    $this->actingAs($this->peserta)->putJson('/api/aksesoris', ['topi' => 'topi_1', 'meja' => null])
        ->assertOk()
        ->assertJsonPath('data.avatar.aksesoris', ['topi' => 'topi_1', 'meja' => null]);

    $this->actingAs($this->peserta)->patchJson('/api/users/me/avatar', [
        'tipe_badan' => 'perempuan', 'rambut' => 'rambut_5', 'warna' => ['kulit' => '#c68642', 'rambut' => '#000000', 'celana' => '#1f2937'],
    ])->assertOk()->assertJsonPath('data.avatar.aksesoris.topi', 'topi_1');

    $this->actingAs($this->peserta)->getJson('/api/progress')->assertJsonPath('data.dipakai.topi', 'topi_1');
});

test('aksesoris butuh avatar yang sudah dipilih', function () {
    $this->peserta->update(['avatar' => null]);
    $this->actingAs($this->peserta)->putJson('/api/aksesoris', ['topi' => null, 'meja' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('topi');
});
