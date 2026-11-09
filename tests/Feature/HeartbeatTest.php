<?php

use App\Enums\PresenceState;
use App\Enums\StateReason;
use App\Models\ActivitySession;
use App\Models\AppRule;
use App\Models\Presence;

beforeEach(function () {
    $this->kelas = kelasDeveloper(['network_cidr' => '192.168.10.0/24']);
    $this->peserta = anggota($this->kelas, seat: 'a1');
    $this->travelTo('2026-11-09 09:00');
    laptop($this->peserta);
});

test('heartbeat dari jaringan kelas: di kelas dan sesi terbuka', function () {
    $this->postJson('/api/agent/heartbeat', laporan())
        ->assertOk()
        ->assertExactJson(['announcements' => [], 'nudges' => []]);

    $presence = Presence::find($this->peserta->id);

    expect($presence->state)->toBe(PresenceState::DiKelas)
        ->and($presence->classroom_id)->toBe($this->kelas->id)
        ->and($presence->file_name)->toBe('auth.ts')
        ->and($presence->last_heartbeat_at->toDateTimeString())->toBe('2026-11-09 09:00:00')
        ->and(ActivitySession::whereNull('ended_at')->sole()->project)->toBe('toko-online');
});

test('heartbeat dari luar jaringan kelas langsung keluar tanpa mencatat aplikasi', function () {
    $this->postJson('/api/agent/heartbeat', laporan(['local_ip' => '10.0.0.5']))->assertOk();

    $presence = Presence::find($this->peserta->id);

    expect($presence->state)->toBe(PresenceState::Keluar)
        ->and($presence->classroom_id)->toBeNull()
        ->and($presence->app_label)->toBeNull()
        ->and(ActivitySession::count())->toBe(0);
});

test('ip publik kampus dipakai kalau subnet tidak cocok', function () {
    $this->kelas->update(['network_cidr' => null, 'public_ip' => '103.10.20.30']);

    $this->withServerVariables(['REMOTE_ADDR' => '103.10.20.30'])
        ->postJson('/api/agent/heartbeat', laporan(['local_ip' => '10.0.0.5']))
        ->assertOk();

    expect(Presence::find($this->peserta->id)->state)->toBe(PresenceState::DiKelas);
});

test('kelas tanpa pengaturan jaringan cukup memakai jadwal', function () {
    $this->kelas->update(['network_cidr' => null]);

    $this->postJson('/api/agent/heartbeat', laporan(['local_ip' => null]))->assertOk();

    expect(Presence::find($this->peserta->id)->state)->toBe(PresenceState::DiKelas);
});

test('idle lebih dari 5 menit jadi istirahat', function () {
    $this->postJson('/api/agent/heartbeat', laporan(['idle_seconds' => 301]))->assertOk();

    expect(Presence::find($this->peserta->id))
        ->state->toBe(PresenceState::Istirahat)
        ->state_reason->toBe(StateReason::Idle);
});

test('jam istirahat terjadwal jadi istirahat', function () {
    $this->travelTo('2026-11-09 12:30');

    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();

    expect(Presence::find($this->peserta->id))
        ->state->toBe(PresenceState::Istirahat)
        ->state_reason->toBe(StateReason::JadwalIstirahat);
});

test('setelah jam kelas selesai jadi keluar', function () {
    $this->travelTo('2026-11-09 16:30');

    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();

    expect(Presence::find($this->peserta->id)->state)->toBe(PresenceState::Keluar);
});

test('sesi berganti saat project berubah, tetap satu sesi kalau sama', function () {
    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();
    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan(['file_name' => 'cart.ts']))->assertOk();
    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan(['project' => 'tugas-pbo', 'language' => 'Java']))->assertOk();

    $sessions = ActivitySession::orderBy('started_at')->get();

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]->project)->toBe('toko-online')
        ->and($sessions[0]->duration_seconds)->toBe(20)
        ->and($sessions[1]->project)->toBe('tugas-pbo')
        ->and($sessions[1]->ended_at)->toBeNull();
});

test('keadaan berganti menutup sesi dan menggeser state_since', function () {
    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();
    $this->travel(5)->minutes();
    $this->postJson('/api/agent/heartbeat', laporan(['idle_seconds' => 400]))->assertOk();

    $presence = Presence::find($this->peserta->id);

    expect($presence->state_since->toDateTimeString())->toBe('2026-11-09 09:05:00')
        ->and(ActivitySession::orderBy('started_at')->pluck('state')->all())
        ->toBe([PresenceState::DiKelas, PresenceState::Istirahat]);
});

test('heartbeat tertunda (queued) melengkapi riwayat sesuai urutan waktunya', function () {
    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();
    $this->travelTo('2026-11-09 09:05');

    $this->postJson('/api/agent/heartbeat', laporan([
        'queued' => [
            laporan(['project' => 'tugas-pbo', 'sent_at' => '2026-11-09T09:03:00+07:00']),
            laporan(['project' => 'latihan', 'sent_at' => '2026-11-09T09:01:00+07:00']),
            // Lebih tua dari heartbeat terakhir, diabaikan.
            laporan(['project' => 'basi', 'sent_at' => '2026-11-09T08:59:00+07:00']),
        ],
    ]))->assertOk();

    expect(ActivitySession::orderBy('started_at')->get()->map(fn ($s) => [$s->project, $s->started_at->format('H:i')])->all())
        ->toBe([['toko-online', '09:00'], ['latihan', '09:01'], ['tugas-pbo', '09:03'], ['toko-online', '09:05']]);
});

test('isi heartbeat divalidasi', function () {
    $this->postJson('/api/agent/heartbeat', ['activity' => 'tidur', 'idle_seconds' => -1, 'local_ip' => 'bukan-ip', 'open_apps' => [['activity' => 'tidur']], 'queued' => [['sent_at' => 'kemarin']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['activity', 'idle_seconds', 'local_ip', 'agent_version', 'sent_at', 'open_apps.0.activity', 'open_apps.0.label', 'queued.0.sent_at', 'queued.0.idle_seconds']);
});

test('aplikasi lain yang terbuka disimpan tanpa membuka sesi baru dan dikosongkan saat keluar', function () {
    $apps = [['activity' => 'browsing', 'label' => 'Chrome'], ['activity' => 'main_game', 'label' => 'Steam']];
    $this->postJson('/api/agent/heartbeat', laporan(['open_apps' => $apps]))->assertOk();
    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan(['open_apps' => [$apps[0]]]))->assertOk();

    expect(Presence::find($this->peserta->id)->open_apps)->toBe([$apps[0]])
        ->and(ActivitySession::count())->toBe(1);

    // Agent lama tanpa open_apps, lalu keluar jaringan kelas.
    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();
    expect(Presence::find($this->peserta->id)->open_apps)->toBeNull();

    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan(['open_apps' => $apps, 'local_ip' => '10.0.0.5']))->assertOk();
    expect(Presence::find($this->peserta->id)->open_apps)->toBeNull();
});

test('config agent berisi jadwal kelas dan app_rules aktif untuk OS laptop', function () {
    AppRule::factory()->create(['pattern' => 'Code.exe', 'os' => 'windows']);
    AppRule::factory()->create(['match_on' => 'title', 'pattern' => 'ChatGPT', 'os' => null, 'activity' => 'vibe_coding', 'priority' => 30]);
    AppRule::factory()->create(['pattern' => 'code', 'os' => 'linux']);
    AppRule::factory()->create(['pattern' => 'nonaktif.exe', 'is_active' => false]);

    $this->getJson('/api/agent/config')
        ->assertOk()
        ->assertJsonPath('heartbeat_interval', 10)
        ->assertJsonPath('classrooms.0.slug', 'developer')
        ->assertJsonCount(2, 'classrooms.0.schedules')
        ->assertJsonCount(2, 'app_rules')
        ->assertJsonPath('app_rules.0.pattern', 'ChatGPT');
});
