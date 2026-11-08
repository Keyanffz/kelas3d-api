<?php

use App\Enums\HelpStatus;
use App\Enums\MemberRole;
use App\Enums\NudgeStatus;
use App\Events\AnnouncementSent;
use App\Events\HelpRequested;
use App\Events\NudgeSent;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\HelpRequest;
use App\Models\Nudge;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->kelas = kelasDeveloper();
    $this->ketua = anggota($this->kelas, MemberRole::Admin, 'a1');
    $this->peserta = anggota($this->kelas, seat: 'a2');
    $this->koordinator = koordinator();
    $this->travelTo('2026-11-09 09:00');
    Event::fake([AnnouncementSent::class, HelpRequested::class, NudgeSent::class]);
});

// Pengumuman

test('koordinator mengirim pengumuman ke satu kelas atau semua kelas', function () {
    Classroom::factory()->create(['slug' => 'data-science']);

    $this->actingAs($this->koordinator)
        ->postJson('/api/announcements', ['message' => 'Istirahat 15 menit', 'classroom' => 'developer'])
        ->assertCreated()
        ->assertJsonPath('data.classroom', 'developer');

    $this->actingAs($this->koordinator)
        ->postJson('/api/announcements', ['message' => 'Kelas selesai jam 3'])
        ->assertCreated()
        ->assertJsonPath('data.classroom', null);

    $channels = fn (AnnouncementSent $event) => collect($event->broadcastOn())->pluck('name')->sort()->values()->all();

    Event::assertDispatched(AnnouncementSent::class, fn ($event) => $channels($event) === ['private-classroom.developer']
        && $event->broadcastWith()['message'] === 'Istirahat 15 menit');
    Event::assertDispatched(AnnouncementSent::class, fn ($event) => $channels($event) === ['private-classroom.data-science', 'private-classroom.developer']
        && $event->broadcastWith()['for_all'] === true);
});

test('pengumuman divalidasi dan cuma untuk koordinator', function () {
    $this->actingAs($this->koordinator)->postJson('/api/announcements', ['message' => '', 'classroom' => 'tidak-ada'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message', 'classroom']);

    $this->actingAs($this->ketua)->postJson('/api/announcements', ['message' => 'Halo'])->assertForbidden();
});

test('balasan heartbeat berisi pengumuman yang belum diterima agent', function () {
    $lama = Announcement::factory()->create(['classroom_id' => $this->kelas->id, 'created_at' => '2026-11-08 10:00']);
    $semua = Announcement::factory()->create(['classroom_id' => null, 'message' => 'Untuk semua']);
    $kelasIni = Announcement::factory()->create(['classroom_id' => $this->kelas->id, 'message' => 'Untuk developer']);
    Announcement::factory()->create(['classroom_id' => Classroom::factory()->create()->id]);
    laptop($this->peserta);

    // Tanpa last_announcement_id: pengumuman hari ini saja.
    $this->postJson('/api/agent/heartbeat', laporan())
        ->assertJsonPath('announcements', [
            ['id' => $semua->id, 'message' => 'Untuk semua'],
            ['id' => $kelasIni->id, 'message' => 'Untuk developer'],
        ]);

    $this->postJson('/api/agent/heartbeat', laporan(['last_announcement_id' => $semua->id, 'sent_at' => now()->addSecond()->toIso8601String()]))
        ->assertJsonCount(1, 'announcements')
        ->assertJsonPath('announcements.0.id', $kelasIni->id);

    expect($lama->id)->toBeLessThan($semua->id);
});

test('anggota kelas mengambil pengumuman hari ini untuk kelasnya dan semua kelas', function () {
    Announcement::factory()->create(['classroom_id' => $this->kelas->id, 'created_at' => '2026-11-08 10:00']);
    $semua = Announcement::factory()->create(['classroom_id' => null, 'message' => 'Untuk semua']);
    $kelasIni = Announcement::factory()->create(['classroom_id' => $this->kelas->id, 'message' => 'Untuk developer']);
    $lain = Classroom::factory()->create(['slug' => 'data-science']);
    Announcement::factory()->create(['classroom_id' => $lain->id]);

    $this->actingAs($this->peserta)
        ->getJson('/api/classrooms/developer/announcements')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$kelasIni->id, $semua->id])
        ->assertJsonPath('data.0.for_all', false)
        ->assertJsonPath('data.0.classroom', 'developer')
        ->assertJsonPath('data.1.for_all', true)
        ->assertJsonPath('data.1.from', $semua->user->name);

    $this->actingAs($this->peserta)->getJson('/api/classrooms/data-science/announcements')->assertForbidden();
    $this->actingAs($this->koordinator)->getJson('/api/classrooms/data-science/announcements')->assertJsonCount(2, 'data');
});

// Angkat tangan

test('peserta angkat tangan dan kelas diberi tahu', function () {
    $id = $this->actingAs($this->peserta)
        ->postJson('/api/help-requests', ['classroom' => 'developer', 'note' => 'Error migrate'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'menunggu')
        ->json('data.id');

    Event::assertDispatched(HelpRequested::class, fn (HelpRequested $event) => $event->broadcastOn()[0]->name === 'private-classroom.developer'
        && $event->broadcastWith()['id'] === $id
        && $event->broadcastWith()['user']['id'] === $this->peserta->id);

    $this->actingAs($this->peserta)
        ->getJson('/api/classrooms/developer/presences')
        ->assertJsonPath('data.1.help_request.note', 'Error migrate');
});

test('belum selesai tidak bisa angkat tangan lagi, orang luar kelas ditolak', function () {
    $this->actingAs($this->peserta)->postJson('/api/help-requests', ['classroom' => 'developer'])->assertCreated();
    $this->actingAs($this->peserta)->postJson('/api/help-requests', ['classroom' => 'developer'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('classroom');

    $this->actingAs(anggota(Classroom::factory()->create()))->postJson('/api/help-requests', ['classroom' => 'developer'])->assertForbidden();
    $this->actingAs($this->peserta)->postJson('/api/help-requests', ['classroom' => 'tidak-ada'])->assertUnprocessable();
});

test('ketua kelas menangani lalu menyelesaikan permintaan bantuan', function () {
    $help = HelpRequest::factory()->for($this->peserta)->for($this->kelas)->create();

    $this->actingAs($this->ketua)
        ->patchJson("/api/help-requests/{$help->id}", ['status' => 'ditangani'])
        ->assertOk()
        ->assertJsonPath('data.handled_by.id', $this->ketua->id);

    $this->actingAs($this->ketua)
        ->patchJson("/api/help-requests/{$help->id}", ['status' => 'selesai'])
        ->assertOk();

    expect($help->fresh())
        ->status->toBe(HelpStatus::Selesai)
        ->resolved_at->not->toBeNull();
    Event::assertDispatchedTimes(HelpRequested::class, 2);
});

test('peserta cuma boleh menurunkan tangannya sendiri', function () {
    $help = HelpRequest::factory()->for($this->peserta)->for($this->kelas)->create();

    $this->actingAs($this->peserta)->patchJson("/api/help-requests/{$help->id}", ['status' => 'ditangani'])->assertForbidden();
    $this->actingAs(anggota($this->kelas))->patchJson("/api/help-requests/{$help->id}", ['status' => 'selesai'])->assertForbidden();
    $this->actingAs($this->peserta)->patchJson("/api/help-requests/{$help->id}", ['status' => 'selesai'])->assertOk();
    $this->actingAs($this->ketua)->patchJson("/api/help-requests/{$help->id}", ['status' => 'menunggu'])->assertUnprocessable();
});

// Teguran

test('koordinator menegur peserta lewat ketua kelas di channel staf', function () {
    $this->actingAs($this->koordinator)
        ->postJson('/api/nudges', ['classroom' => 'developer', 'about_user_id' => $this->peserta->id, 'message' => 'Coba cek, dari tadi idle'])
        ->assertCreated()
        ->assertJsonPath('data.to.id', $this->ketua->id)
        ->assertJsonPath('data.about.id', $this->peserta->id)
        ->assertJsonPath('data.status', 'terkirim');

    Event::assertDispatched(NudgeSent::class, fn (NudgeSent $event) => $event->broadcastOn()[0]->name === 'private-classroom.developer.staff'
        && $event->broadcastWith()['message'] === 'Coba cek, dari tadi idle');
});

test('teguran divalidasi dan cuma untuk koordinator', function () {
    $orangLuar = anggota(Classroom::factory()->create());

    $this->actingAs($this->koordinator)
        ->postJson('/api/nudges', ['classroom' => 'developer', 'about_user_id' => $orangLuar->id, 'message' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['about_user_id', 'message']);

    $tanpaKetua = Classroom::factory()->create(['slug' => 'tanpa-ketua']);
    $this->actingAs($this->koordinator)
        ->postJson('/api/nudges', ['classroom' => 'tanpa-ketua', 'about_user_id' => anggota($tanpaKetua)->id, 'message' => 'Cek'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('classroom');

    $this->actingAs($this->ketua)
        ->postJson('/api/nudges', ['classroom' => 'developer', 'about_user_id' => $this->peserta->id, 'message' => 'Cek'])
        ->assertForbidden();
});

test('ketua kelas menandai teguran dibaca lalu selesai', function () {
    $nudge = Nudge::factory()->for($this->kelas)->create(['from_user_id' => $this->koordinator->id, 'to_user_id' => $this->ketua->id, 'about_user_id' => $this->peserta->id]);

    $this->actingAs($this->peserta)->patchJson("/api/nudges/{$nudge->id}", ['status' => 'dibaca'])->assertForbidden();

    $this->actingAs($this->ketua)->patchJson("/api/nudges/{$nudge->id}", ['status' => 'dibaca'])
        ->assertOk()
        ->assertJsonPath('data.status', 'dibaca');

    $this->travel(5)->minutes();
    $this->actingAs($this->ketua)->patchJson("/api/nudges/{$nudge->id}", ['status' => 'selesai'])->assertOk();

    expect($nudge->fresh())
        ->status->toBe(NudgeStatus::Selesai)
        ->read_at->toDateTimeString()->toBe('2026-11-09 09:00:00')
        ->handled_at->toDateTimeString()->toBe('2026-11-09 09:05:00');
    Event::assertDispatchedTimes(NudgeSent::class, 2);
});

test('daftar teguran yang belum selesai untuk ketua dan koordinator', function () {
    $buat = fn (array $data = []) => Nudge::factory()->for($this->kelas)->create(['from_user_id' => $this->koordinator->id, 'to_user_id' => $this->ketua->id, 'about_user_id' => $this->peserta->id, ...$data]);
    $buat();
    $buat(['status' => NudgeStatus::Dibaca]);
    $buat(['status' => NudgeStatus::Selesai]);

    $this->actingAs($this->ketua)->getJson('/api/nudges')->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs($this->koordinator)->getJson('/api/nudges')->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs($this->peserta)->getJson('/api/nudges')->assertOk()->assertJsonCount(0, 'data');
});

test('balasan heartbeat ketua kelas berisi teguran yang belum diterima', function () {
    $buat = fn (array $data = []) => Nudge::factory()->for($this->kelas)->create(['from_user_id' => $this->koordinator->id, 'to_user_id' => $this->ketua->id, 'about_user_id' => $this->peserta->id, ...$data]);
    $pertama = $buat(['message' => 'Cek Budi']);
    $kedua = $buat(['message' => 'Cek lagi']);
    $buat(['status' => NudgeStatus::Dibaca]);

    laptop($this->ketua);
    $this->postJson('/api/agent/heartbeat', laporan(['last_nudge_id' => $pertama->id]))
        ->assertOk()
        ->assertJsonPath('nudges', [['id' => $kedua->id, 'about' => $this->peserta->name, 'message' => 'Cek lagi']]);

    // Peserta biasa tidak pernah menerima teguran.
    laptop($this->peserta);
    $this->postJson('/api/agent/heartbeat', laporan())->assertJsonPath('nudges', []);
});
