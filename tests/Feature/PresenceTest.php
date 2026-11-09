<?php

use App\Enums\MemberRole;
use App\Models\ActivitySession;
use App\Models\Classroom;
use App\Models\Presence;

beforeEach(function () {
    $this->kelas = kelasDeveloper();
    $this->peserta = anggota($this->kelas, seat: 'a1');
    Presence::factory()->for($this->peserta)->create([
        'classroom_id' => $this->kelas->id,
        'project' => 'toko-online',
        'open_apps' => [['activity' => 'browsing', 'label' => 'Chrome']],
    ]);
});

test('anggota melihat keadaan ruangan tanpa detail kerjaan', function () {
    anggota($this->kelas, seat: 'a2');

    $this->actingAs($this->peserta)
        ->getJson('/api/classrooms/developer/presences')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.user.id', $this->peserta->id)
        ->assertJsonPath('data.0.state', 'di_kelas')
        ->assertJsonPath('data.0.app_label', 'VS Code')
        ->assertJsonMissingPath('data.0.project')
        ->assertJsonMissingPath('data.0.open_apps')
        // Belum pernah kirim heartbeat berarti keluar.
        ->assertJsonPath('data.1.state', 'keluar');
});

test('ketua kelas dan koordinator melihat detail kerjaan', function () {
    // Ketua belum punya kursi, jadi ada di urutan pertama.
    $ketua = anggota($this->kelas, MemberRole::Admin);

    foreach ([$ketua, koordinator()] as $user) {
        $this->actingAs($user)
            ->getJson('/api/classrooms/developer/presences')
            ->assertJsonPath('data.1.project', 'toko-online')
            ->assertJsonPath('data.1.open_apps.0.label', 'Chrome');
    }
});

test('orang luar kelas tidak bisa melihat keadaan ruangan', function () {
    $this->actingAs(anggota(Classroom::factory()->create()))
        ->getJson('/api/classrooms/developer/presences')
        ->assertForbidden();
});

test('rekap aktivitas per hari, project, dan bahasa', function () {
    $this->travelTo('2026-11-09 15:00');
    $sesi = fn (array $data) => ActivitySession::factory()->for($this->peserta)->create(['classroom_id' => $this->kelas->id, ...$data]);
    $sesi(['project' => 'toko-online', 'language' => 'PHP', 'started_at' => '2026-11-09 08:00', 'duration_seconds' => 3600]);
    $sesi(['project' => 'tugas-pbo', 'language' => 'Java', 'started_at' => '2026-11-09 09:00', 'duration_seconds' => 1800]);
    $sesi(['state' => 'istirahat', 'activity' => null, 'project' => null, 'language' => null, 'started_at' => '2026-11-09 12:00', 'duration_seconds' => 3600]);
    // Sesi yang masih jalan dihitung sampai sekarang (14.30–15.00).
    $sesi(['project' => 'toko-online', 'language' => 'PHP', 'started_at' => '2026-11-09 14:30', 'ended_at' => null, 'duration_seconds' => null]);
    $sesi(['project' => 'lama', 'language' => 'PHP', 'started_at' => '2026-11-08 09:00', 'duration_seconds' => 600]);

    $this->actingAs($this->peserta)
        ->getJson('/api/users/me/activity?from=2026-11-09&to=2026-11-09')
        ->assertOk()
        ->assertExactJson(['data' => [
            'from' => '2026-11-09',
            'to' => '2026-11-09',
            'per_day' => [['date' => '2026-11-09', 'di_kelas' => 7200, 'istirahat' => 3600]],
            'per_project' => [['project' => 'toko-online', 'seconds' => 5400], ['project' => 'tugas-pbo', 'seconds' => 1800]],
            'per_language' => [['language' => 'PHP', 'seconds' => 5400], ['language' => 'Java', 'seconds' => 1800]],
        ]]);
});

test('rekap aktivitas boleh dilihat ketua kelasnya, tidak oleh teman sekelas', function () {
    $this->actingAs(anggota($this->kelas, MemberRole::Admin))
        ->getJson("/api/users/{$this->peserta->id}/activity")
        ->assertOk();

    $this->actingAs(anggota($this->kelas))
        ->getJson("/api/users/{$this->peserta->id}/activity")
        ->assertForbidden();

    $this->actingAs($this->peserta)
        ->getJson('/api/users/me/activity?from=2026-11-09&to=2026-11-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});
