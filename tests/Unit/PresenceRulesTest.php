<?php

use App\Enums\PresenceState;
use App\Enums\StateReason;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Support\PresenceRules;
use Illuminate\Support\Carbon;

// Kelas di memori, tanpa database: Senin 08.00–16.00, istirahat 12.00–13.00.
function kelasDiMemori(array $attributes = []): Classroom
{
    return (new Classroom(['slug' => 'developer', ...$attributes]))->setRelation('schedules', collect([
        new ClassSchedule(['type' => 'kelas', 'day_of_week' => 1, 'starts_at' => '08:00:00', 'ends_at' => '16:00:00']),
        new ClassSchedule(['type' => 'istirahat', 'day_of_week' => 1, 'starts_at' => '12:00:00', 'ends_at' => '13:00:00']),
    ]));
}

function senin(string $jam): Carbon
{
    return Carbon::parse("2026-11-09 $jam");
}

test('aktif di jam kelas berarti di kelas', function () {
    expect((new PresenceRules)->resolve(kelasDiMemori(), senin('09:00'), 12, 0))
        ->toBe([PresenceState::DiKelas, null]);
});

test('idle lebih dari batas berarti istirahat', function () {
    $rules = new PresenceRules;

    expect($rules->resolve(kelasDiMemori(), senin('09:00'), 299, 0))->toBe([PresenceState::DiKelas, null])
        ->and($rules->resolve(kelasDiMemori(), senin('09:00'), 300, 0))->toBe([PresenceState::Istirahat, StateReason::Idle]);
});

test('jam istirahat terjadwal berarti istirahat', function () {
    expect((new PresenceRules)->resolve(kelasDiMemori(), senin('12:30'), 0, 0))
        ->toBe([PresenceState::Istirahat, StateReason::JadwalIstirahat]);
});

test('jam kelas selesai atau hari libur berarti keluar', function () {
    $rules = new PresenceRules;

    expect($rules->resolve(kelasDiMemori(), senin('16:00'), 0, 0))->toBe([PresenceState::Keluar, null])
        ->and($rules->resolve(kelasDiMemori(), Carbon::parse('2026-11-10 09:00'), 0, 0))->toBe([PresenceState::Keluar, null]);
});

test('di luar jaringan kelas berarti keluar', function () {
    expect((new PresenceRules)->resolve(null, senin('09:00'), 0, 0))->toBe([PresenceState::Keluar, null]);
});

test('tanpa heartbeat 2 menit istirahat, 30 menit keluar', function () {
    $rules = new PresenceRules;

    expect($rules->resolve(kelasDiMemori(), senin('09:00'), 0, 119))->toBe([PresenceState::DiKelas, null])
        ->and($rules->resolve(kelasDiMemori(), senin('09:00'), 0, 120))->toBe([PresenceState::Istirahat, StateReason::Terputus])
        ->and($rules->resolve(kelasDiMemori(), senin('09:00'), 0, 1800))->toBe([PresenceState::Keluar, StateReason::Terputus]);
});

test('angka batas diambil dari config', function () {
    config(['kelas3d.idle_limit' => 900]);

    expect((new PresenceRules)->resolve(kelasDiMemori(), senin('09:00'), 600, 0))->toBe([PresenceState::DiKelas, null]);
});

test('ruangan ditentukan dari network_cidr, lalu public_ip, lalu jadwal', function () {
    $rules = new PresenceRules;
    $lan = kelasDiMemori(['slug' => 'developer', 'network_cidr' => '192.168.10.0/24']);
    $publik = kelasDiMemori(['slug' => 'data-science', 'public_ip' => '103.10.20.30']);
    $polos = kelasDiMemori(['slug' => 'lain']);

    expect($rules->locate(collect([$publik, $lan]), '192.168.10.23', '1.1.1.1', senin('09:00')))->toBe($lan)
        ->and($rules->locate(collect([$lan, $publik]), '10.0.0.5', '103.10.20.30', senin('09:00')))->toBe($publik)
        ->and($rules->locate(collect([$lan, $polos]), '10.0.0.5', '1.1.1.1', senin('09:00')))->toBe($polos)
        ->and($rules->locate(collect([$lan, $publik]), '10.0.0.5', '1.1.1.1', senin('09:00')))->toBeNull()
        ->and($rules->locate(collect([$polos]), null, null, senin('17:00')))->toBeNull();
});
