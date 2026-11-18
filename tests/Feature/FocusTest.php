<?php

use App\Enums\MemberRole;
use App\Enums\PresenceState;
use App\Events\PresenceChanged;
use App\Models\Announcement;
use App\Models\Nudge;
use App\Models\Presence;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->kelas = kelasDeveloper(['network_cidr' => '192.168.10.0/24']);
    $this->peserta = anggota($this->kelas, seat: 'a2');
    $this->travelTo('2026-11-09 09:00');
    Event::fake([PresenceChanged::class]);

    $this->hadir = fn () => Presence::factory()->for($this->peserta)->create([
        'classroom_id' => $this->kelas->id,
        'last_heartbeat_at' => now(),
        'state_since' => now(),
    ]);
});

test('peserta menyalakan dan mematikan mode fokus, dan kelasnya diberi tahu', function () {
    ($this->hadir)();

    $this->actingAs($this->peserta)->putJson('/api/focus', ['on' => true])
        ->assertOk()
        ->assertJsonPath('data.focus', true);

    Event::assertDispatched(PresenceChanged::class, fn ($event) => $event->broadcastOn()[0]->name === 'private-classroom.developer'
        && $event->broadcastWith()['user_id'] === $this->peserta->id
        && $event->broadcastWith()['focus'] === true);
    $this->actingAs($this->peserta)->getJson('/api/classrooms/developer/presences')->assertJsonPath('data.0.focus', true);

    // Menyalakan lagi tidak mengirim event kedua.
    $this->actingAs($this->peserta)->putJson('/api/focus', ['on' => true])->assertOk();
    Event::assertDispatchedTimes(PresenceChanged::class, 1);

    $this->actingAs($this->peserta)->putJson('/api/focus', ['on' => false])->assertJsonPath('data.focus', false);
    expect(Presence::find($this->peserta->id)->focus_since)->toBeNull();
    Event::assertDispatched(PresenceChanged::class, fn ($event) => $event->broadcastWith()['focus'] === false);
});

test('mode fokus cuma untuk yang sedang di kelas', function () {
    $this->actingAs($this->peserta)->putJson('/api/focus', ['on' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('on');

    ($this->hadir)()->update(['state' => PresenceState::Keluar, 'classroom_id' => null]);
    $this->actingAs($this->peserta)->putJson('/api/focus', ['on' => true])->assertUnprocessable();
    $this->actingAs(koordinator())->putJson('/api/focus', ['on' => true])->assertUnprocessable();
    $this->actingAs($this->peserta)->putJson('/api/focus', [])->assertUnprocessable();

    Event::assertNotDispatched(PresenceChanged::class);
});

test('fokus selesai sendiri saat peserta keluar', function () {
    laptop($this->peserta);
    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();
    Presence::find($this->peserta->id)->update(['focus_since' => now()]);

    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan())->assertOk();
    expect(Presence::find($this->peserta->id)->focus_since)->not->toBeNull();

    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan(['local_ip' => '10.0.0.5']))->assertOk();
    expect(Presence::find($this->peserta->id))
        ->state->toBe(PresenceState::Keluar)
        ->focus_since->toBeNull();
    Event::assertDispatched(PresenceChanged::class, fn ($event) => $event->broadcastWith()['state'] === 'keluar'
        && $event->broadcastWith()['focus'] === false);
});

test('selama fokus pengumuman ditahan dari agent, teguran tetap masuk, lalu terkirim saat fokus selesai', function () {
    $ketua = anggota($this->kelas, MemberRole::Admin, 'a1');
    $pengumuman = Announcement::factory()->create(['classroom_id' => $this->kelas->id, 'message' => 'Istirahat 15 menit']);
    $teguran = Nudge::factory()->create(['classroom_id' => $this->kelas->id, 'to_user_id' => $ketua->id, 'about_user_id' => $this->peserta->id]);
    laptop($ketua);

    $this->postJson('/api/agent/heartbeat', laporan())->assertJsonCount(1, 'announcements');
    Presence::find($ketua->id)->update(['focus_since' => now()]);
    $baru = Announcement::factory()->create(['classroom_id' => $this->kelas->id, 'message' => 'Lanjut jam 10.30']);

    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan(['last_announcement_id' => $pengumuman->id]))
        ->assertJsonCount(0, 'announcements')
        ->assertJsonPath('nudges.0.id', $teguran->id);

    Presence::find($ketua->id)->update(['focus_since' => null]);
    $this->travel(10)->seconds();
    $this->postJson('/api/agent/heartbeat', laporan(['last_announcement_id' => $pengumuman->id]))
        ->assertJsonPath('announcements', [['id' => $baru->id, 'message' => 'Lanjut jam 10.30']]);
});
