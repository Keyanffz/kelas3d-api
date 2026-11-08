<?php

use App\Enums\MemberRole;
use App\Enums\PresenceState;
use App\Enums\StateReason;
use App\Events\CoordinatorMoved;
use App\Events\PresenceChanged;
use App\Models\Classroom;
use App\Models\Presence;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->kelas = kelasDeveloper(['network_cidr' => '192.168.10.0/24']);
    $this->peserta = anggota($this->kelas, seat: 'a1');
    $this->travelTo('2026-11-09 09:00');
    Event::fake([PresenceChanged::class, CoordinatorMoved::class]);
});

test('heartbeat yang mengubah keadaan mengirim PresenceChanged ke channel kelas', function () {
    laptop($this->peserta);

    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();
    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();

    // Heartbeat kedua tidak mengubah apa pun, jadi tidak ada event lagi.
    Event::assertDispatchedTimes(PresenceChanged::class, 1);
    Event::assertDispatched(PresenceChanged::class, function (PresenceChanged $event) {
        return $event->broadcastOn()[0]->name === 'private-classroom.developer'
            && $event->broadcastAs() === 'PresenceChanged'
            && $event->broadcastWith() === [
                'user_id' => $this->peserta->id,
                'state' => 'di_kelas',
                'state_reason' => null,
                'activity' => 'ngoding',
                'app_label' => 'VS Code',
                'state_since' => '2026-11-09T09:00:00+07:00',
            ];
    });
});

test('peserta yang keluar jaringan terlihat keluar di kelas asalnya', function () {
    laptop($this->peserta);
    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();
    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan(['local_ip' => '10.0.0.5']))->assertOk();

    Event::assertDispatched(PresenceChanged::class, fn (PresenceChanged $event) => $event->classroom->is($this->kelas)
        && $event->broadcastWith()['state'] === 'keluar'
        && $event->broadcastWith()['app_label'] === null);
});

test('heartbeat dari antrean tidak di-broadcast', function () {
    laptop($this->peserta);
    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();
    $this->travelTo('2026-11-09 09:05');

    $this->postJson('/api/agent/heartbeat', laporan([
        'queued' => [laporan(['project' => 'lain', 'sent_at' => '2026-11-09T09:02:00+07:00'])],
    ]))->assertOk();

    // Satu dari heartbeat pertama, satu dari heartbeat sekarang yang kembali ke project awal.
    Event::assertDispatchedTimes(PresenceChanged::class, 2);
});

test('scheduler: tanpa heartbeat 2 menit istirahat, 30 menit keluar', function () {
    $presence = Presence::factory()->for($this->peserta)->create([
        'classroom_id' => $this->kelas->id,
        'last_heartbeat_at' => now(),
        'state_since' => now(),
    ]);

    $this->travel(90)->seconds();
    $this->artisan('presences:check')->assertSuccessful();
    expect($presence->fresh()->state)->toBe(PresenceState::DiKelas);

    $this->travel(60)->seconds();
    $this->artisan('presences:check')->assertSuccessful();
    expect($presence->fresh())
        ->state->toBe(PresenceState::Istirahat)
        ->state_reason->toBe(StateReason::Terputus);

    $this->travel(30)->minutes();
    $this->artisan('presences:check')->assertSuccessful();
    expect($presence->fresh())
        ->state->toBe(PresenceState::Keluar)
        ->classroom_id->toBeNull();

    Event::assertDispatchedTimes(PresenceChanged::class, 2);
});

test('scheduler: jam kelas selesai semua keluar, jam istirahat semua istirahat', function () {
    $duduk = Presence::factory()->for($this->peserta)->create(['classroom_id' => $this->kelas->id, 'last_heartbeat_at' => '2026-11-09 11:59:50']);
    $pulang = Presence::factory()->for(anggota($this->kelas))->create(['classroom_id' => $this->kelas->id, 'last_heartbeat_at' => '2026-11-09 15:59:50']);

    $this->travelTo('2026-11-09 12:00:10');
    $this->artisan('presences:check');
    expect($duduk->fresh()->state_reason)->toBe(StateReason::JadwalIstirahat);

    $this->travelTo('2026-11-09 16:00:10');
    $this->artisan('presences:check');
    expect($pulang->fresh()->state)->toBe(PresenceState::Keluar);
});

test('scheduler: peserta yang idle tetap idle selama heartbeat masih jalan', function () {
    $presence = Presence::factory()->for($this->peserta)->create([
        'classroom_id' => $this->kelas->id,
        'state' => PresenceState::Istirahat,
        'state_reason' => StateReason::Idle,
        'last_heartbeat_at' => now(),
    ]);

    $this->travel(30)->seconds();
    $this->artisan('presences:check');

    expect($presence->fresh()->state_reason)->toBe(StateReason::Idle);
    Event::assertNotDispatched(PresenceChanged::class);
});

test('channel kelas cuma untuk koordinator dan anggotanya', function () {
    $channel = Broadcast::getChannels()['classroom.{classroom}'];

    expect($channel($this->peserta, $this->kelas))->toBeTrue()
        ->and($channel(koordinator(), $this->kelas))->toBeTrue()
        ->and($channel(anggota(Classroom::factory()->create()), $this->kelas))->toBeFalse();
});

test('channel staf cuma untuk koordinator dan ketua kelas itu', function () {
    $channel = Broadcast::getChannels()['classroom.{classroom}.staff'];

    expect($channel(anggota($this->kelas, MemberRole::Admin), $this->kelas))->toBeTrue()
        ->and($channel(koordinator(), $this->kelas))->toBeTrue()
        ->and($channel($this->peserta, $this->kelas))->toBeFalse()
        ->and($channel(anggota(Classroom::factory()->create(), MemberRole::Admin), $this->kelas))->toBeFalse();
});

test('otorisasi channel lewat /api/broadcasting/auth butuh login web', function () {
    $this->postJson('/api/broadcasting/auth', ['channel_name' => 'private-classroom.developer', 'socket_id' => '1.1'])
        ->assertUnauthorized();
});

test('kunjungan koordinator mengirim CoordinatorMoved dan tercatat di presences', function () {
    $koordinator = koordinator();
    $dataScience = Classroom::factory()->create(['slug' => 'data-science']);

    $this->actingAs($koordinator)
        ->postJson('/api/coordinator/visit', ['classroom' => 'developer'])
        ->assertOk()
        ->assertJson(['data' => ['from' => null, 'to' => 'developer']]);

    Event::assertDispatched(CoordinatorMoved::class, fn (CoordinatorMoved $event) => collect($event->broadcastOn())->pluck('name')->all() === ['private-classroom.developer']
        && $event->broadcastWith()['coordinator']['id'] === $koordinator->id);

    $this->actingAs($this->peserta)
        ->getJson('/api/classrooms/developer/presences')
        ->assertJsonPath('coordinators.0.id', $koordinator->id);

    $this->actingAs($koordinator)->postJson('/api/coordinator/visit', ['classroom' => $dataScience->slug])->assertOk();

    Event::assertDispatched(CoordinatorMoved::class, fn (CoordinatorMoved $event) => collect($event->broadcastOn())->pluck('name')->all() === ['private-classroom.developer', 'private-classroom.data-science']);

    $this->actingAs($koordinator)->postJson('/api/coordinator/visit', ['classroom' => null])->assertJsonPath('data.to', null);

    $this->actingAs($this->peserta)
        ->getJson('/api/classrooms/developer/presences')
        ->assertJsonCount(0, 'coordinators');
});

test('kunjungan cuma untuk koordinator, ke kelas yang ada', function () {
    $this->actingAs(anggota($this->kelas, MemberRole::Admin))->postJson('/api/coordinator/visit', ['classroom' => 'developer'])->assertForbidden();
    $this->actingAs(koordinator())->postJson('/api/coordinator/visit', ['classroom' => 'tidak-ada'])->assertUnprocessable();
    $this->actingAs(koordinator())->postJson('/api/coordinator/visit', [])->assertUnprocessable();

    Event::assertNotDispatched(CoordinatorMoved::class);
});
