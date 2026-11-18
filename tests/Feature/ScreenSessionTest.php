<?php

use App\Enums\HelpStatus;
use App\Enums\MemberRole;
use App\Enums\ScreenCloseReason;
use App\Enums\ScreenStatus;
use App\Events\ScreenSessionChanged;
use App\Models\Classroom;
use App\Models\HelpRequest;
use App\Models\ScreenSession;
use App\Models\User;
use App\Support\WebOnline;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

// Web siapa saja yang dianggap terbuka; null berarti semua.
function webTerbuka(?array $ids = null): void
{
    app()->instance(WebOnline::class, new class($ids) extends WebOnline
    {
        public function __construct(private ?array $ids) {}

        public function has(User $user): bool
        {
            return $this->ids === null || in_array($user->id, $this->ids, true);
        }
    });
}

beforeEach(function () {
    $this->kelas = kelasDeveloper();
    $this->ketua = anggota($this->kelas, MemberRole::Admin, 'a1');
    $this->peserta = anggota($this->kelas, seat: 'a2');
    $this->koordinator = koordinator();
    $this->travelTo('2026-11-09 09:00');
    Event::fake([ScreenSessionChanged::class]);
    webTerbuka();

    $this->minta = fn (array $body = []) => $this->actingAs($this->koordinator)
        ->postJson('/api/screen-sessions', ['classroom' => 'developer', 'target_user_id' => $this->peserta->id, ...$body]);
    $this->aksi = fn (User $user, ScreenSession $sesi, string $action, array $body = []) => $this->actingAs($user)
        ->patchJson("/api/screen-sessions/{$sesi->id}", ['action' => $action, ...$body]);
    $this->penerima = fn (ScreenSessionChanged $event) => collect($event->broadcastOn())->pluck('name')->all();
});

test('alur lengkap: koordinator minta, ketua setuju, mahasiswa izinkan, live, lalu dihentikan', function () {
    $id = ($this->minta)(['reason' => 'Dari tadi browsing'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'menunggu_ketua')
        ->assertJsonPath('data.reason', 'Dari tadi browsing')
        ->json('data.id');
    $sesi = ScreenSession::find($id);

    // Mahasiswa belum diberi tahu sebelum ketua kelas setuju.
    Event::assertDispatched(ScreenSessionChanged::class, fn ($event) => ($this->penerima)($event) === [
        "private-user.{$this->koordinator->id}", "private-user.{$this->ketua->id}",
    ]);
    $this->actingAs($this->peserta)->getJson('/api/screen-sessions')->assertJsonCount(0, 'data');
    $this->actingAs($this->ketua)->getJson('/api/screen-sessions')->assertJsonCount(1, 'data');
    ($this->aksi)($this->peserta, $sesi, 'izinkan')->assertForbidden();

    ($this->aksi)($this->ketua, $sesi, 'setujui')
        ->assertOk()
        ->assertJsonPath('data.status', 'menunggu_mahasiswa')
        ->assertJsonPath('data.approver.id', $this->ketua->id);
    Event::assertDispatched(ScreenSessionChanged::class, fn ($event) => in_array("private-user.{$this->peserta->id}", ($this->penerima)($event), true)
        && $event->broadcastAs() === 'ScreenSessionChanged'
        && $event->broadcastWith()['status'] === ScreenStatus::MenungguMahasiswa);
    $this->actingAs($this->peserta)->getJson('/api/screen-sessions')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('ice_servers.0.urls', 'stun:stun.l.google.com:19302');

    // Tanpa izin mahasiswa, sesi tidak bisa mulai.
    ($this->aksi)($this->peserta, $sesi, 'mulai')->assertUnprocessable();
    ($this->aksi)($this->ketua, $sesi, 'izinkan')->assertUnprocessable();

    $this->travel(20)->seconds();
    ($this->aksi)($this->peserta, $sesi, 'izinkan')->assertJsonPath('data.status', 'menyambung');
    $this->travel(5)->seconds();
    ($this->aksi)($this->peserta, $sesi, 'mulai')->assertJsonPath('data.status', 'live');
    $this->travel(5)->minutes();
    ($this->aksi)($this->peserta, $sesi, 'stop')
        ->assertJsonPath('data.status', 'selesai')
        ->assertJsonPath('data.close_reason', 'dihentikan')
        ->assertJsonPath('data.closed_by.id', $this->peserta->id);

    expect($sesi->fresh())
        ->approved_at->toDateTimeString()->toBe('2026-11-09 09:00:00')
        ->consented_at->toDateTimeString()->toBe('2026-11-09 09:00:20')
        ->started_at->toDateTimeString()->toBe('2026-11-09 09:00:25')
        ->ended_at->toDateTimeString()->toBe('2026-11-09 09:05:25');

    ($this->aksi)($this->koordinator, $sesi, 'stop')->assertUnprocessable();
    $this->actingAs($this->ketua)->getJson('/api/classrooms/developer/screen-sessions')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'selesai');
    $this->actingAs($this->peserta)->getJson('/api/classrooms/developer/screen-sessions')->assertForbidden();
});

test('ketua kelas atau mahasiswa bisa menolak', function () {
    $sesi = ScreenSession::find(($this->minta)()->json('data.id'));
    ($this->aksi)($this->ketua, $sesi, 'tolak')
        ->assertJsonPath('data.status', 'ditolak')
        ->assertJsonPath('data.close_reason', 'ditolak_ketua');
    Event::assertNotDispatched(ScreenSessionChanged::class, fn ($event) => in_array("private-user.{$this->peserta->id}", ($this->penerima)($event), true));

    // Sesi lama sudah selesai, jadi boleh minta lagi.
    $sesi = ScreenSession::find(($this->minta)()->assertCreated()->json('data.id'));
    ($this->aksi)($this->ketua, $sesi, 'setujui');
    ($this->aksi)($this->peserta, $sesi, 'tolak')
        ->assertJsonPath('data.status', 'ditolak')
        ->assertJsonPath('data.close_reason', 'ditolak_mahasiswa');
});

test('izin browser yang ditolak tercatat sebagai ditolak, koneksi putus sebagai selesai', function () {
    $sesi = ScreenSession::find(($this->minta)()->json('data.id'));
    ($this->aksi)($this->ketua, $sesi, 'setujui');
    ($this->aksi)($this->peserta, $sesi, 'stop', ['reason' => 'izin_browser'])
        ->assertJsonPath('data.status', 'ditolak')
        ->assertJsonPath('data.close_reason', 'izin_browser');

    $sesi = ScreenSession::find(($this->minta)()->json('data.id'));
    ($this->aksi)($this->ketua, $sesi, 'setujui');
    ($this->aksi)($this->peserta, $sesi, 'izinkan');
    ($this->aksi)($this->peserta, $sesi, 'mulai');
    ($this->aksi)($this->koordinator, $sesi, 'stop', ['reason' => 'terputus'])
        ->assertJsonPath('data.status', 'selesai')
        ->assertJsonPath('data.close_reason', 'terputus');
});

test('ketua kelas dan koordinator peminta bisa menghentikan, orang lain tidak', function () {
    $sesi = ScreenSession::find(($this->minta)()->json('data.id'));

    ($this->aksi)(koordinator(), $sesi, 'stop')->assertForbidden();
    ($this->aksi)(anggota($this->kelas), $sesi, 'stop')->assertForbidden();
    ($this->aksi)(anggota(Classroom::factory()->create(), MemberRole::Admin), $sesi, 'setujui')->assertForbidden();

    ($this->aksi)($this->ketua, $sesi, 'setujui');
    ($this->aksi)($this->ketua, $sesi, 'stop')->assertJsonPath('data.status', 'selesai');
});

test('satu mahasiswa cuma boleh punya satu sesi berjalan', function () {
    ($this->minta)()->assertCreated();
    ($this->minta)()->assertUnprocessable()->assertJsonValidationErrors('target_user_id');
    $this->actingAs(koordinator())
        ->postJson('/api/screen-sessions', ['classroom' => 'developer', 'target_user_id' => $this->peserta->id])
        ->assertUnprocessable();

    expect(ScreenSession::count())->toBe(1);
});

test('permintaan kedaluwarsa kalau tidak direspons 60 detik, di tiap tahap', function () {
    $sesi = ScreenSession::find(($this->minta)()->json('data.id'));
    $this->travel(59)->seconds();
    $this->artisan('screen-sessions:expire')->assertSuccessful();
    expect($sesi->fresh()->status)->toBe(ScreenStatus::MenungguKetua);

    ($this->aksi)($this->ketua, $sesi, 'setujui');
    $this->travel(59)->seconds();
    $this->artisan('screen-sessions:expire');
    expect($sesi->fresh()->status)->toBe(ScreenStatus::MenungguMahasiswa);

    $this->travel(2)->seconds();
    // Tanpa menunggu scheduler: aksi yang terlambat ikut ditolak.
    ($this->aksi)($this->peserta, $sesi, 'izinkan')->assertUnprocessable();
    expect($sesi->fresh())
        ->status->toBe(ScreenStatus::Kedaluwarsa)
        ->ended_at->not->toBeNull();
    Event::assertDispatched(ScreenSessionChanged::class, fn ($event) => $event->broadcastWith()['status'] === ScreenStatus::Kedaluwarsa);

    ($this->minta)()->assertCreated();
});

test('sesi live ditutup sesudah batas waktu', function () {
    $sesi = ScreenSession::find(($this->minta)()->json('data.id'));
    ($this->aksi)($this->ketua, $sesi, 'setujui');
    ($this->aksi)($this->peserta, $sesi, 'izinkan');
    ($this->aksi)($this->peserta, $sesi, 'mulai');

    $this->travel(29)->minutes();
    $this->artisan('screen-sessions:expire');
    expect($sesi->fresh()->status)->toBe(ScreenStatus::Live);

    $this->travel(2)->minutes();
    $this->artisan('screen-sessions:expire');
    expect($sesi->fresh())
        ->status->toBe(ScreenStatus::Selesai)
        ->close_reason->toBe(ScreenCloseReason::BatasWaktu);
});

test('ditolak kalau web ketua kelas atau web mahasiswa tidak terbuka', function () {
    webTerbuka([$this->peserta->id]);
    ($this->minta)()->assertUnprocessable()->assertJsonValidationErrors('classroom');

    webTerbuka([$this->ketua->id]);
    ($this->minta)()->assertUnprocessable()->assertJsonValidationErrors('target_user_id');

    expect(ScreenSession::count())->toBe(0);
});

test('kalau yang dituju ketua kelas sendiri, langsung ke izin miliknya', function () {
    webTerbuka([$this->ketua->id]);

    $sesi = ScreenSession::find(($this->minta)(['target_user_id' => $this->ketua->id])
        ->assertCreated()
        ->assertJsonPath('data.status', 'menunggu_mahasiswa')
        ->assertJsonPath('data.approver', null)
        ->json('data.id'));

    ($this->aksi)($this->ketua, $sesi, 'izinkan')->assertJsonPath('data.status', 'menyambung');
});

test('permintaan divalidasi dan cuma untuk koordinator', function () {
    ($this->minta)(['target_user_id' => anggota(Classroom::factory()->create())->id])
        ->assertUnprocessable()->assertJsonValidationErrors('target_user_id');

    $this->ketua->memberships()->update(['role' => MemberRole::Anggota]);
    ($this->minta)()->assertUnprocessable()->assertJsonValidationErrors('classroom');

    $this->actingAs($this->ketua)
        ->postJson('/api/screen-sessions', ['classroom' => 'developer', 'target_user_id' => $this->peserta->id])
        ->assertForbidden();
});

test('channel pribadi cuma untuk pemiliknya, channel signaling cuma untuk dua ujung sesudah mahasiswa mengizinkan', function () {
    $user = Broadcast::getChannels()['user.{id}'];
    $screen = Broadcast::getChannels()['screen.{session}'];
    $sesi = ScreenSession::find(($this->minta)()->json('data.id'));

    expect($user($this->peserta, $this->peserta->id))->toBeTrue()
        ->and($user($this->ketua, $this->peserta->id))->toBeFalse();

    ($this->aksi)($this->ketua, $sesi, 'setujui');
    expect($screen($this->koordinator, $sesi->fresh()))->toBeFalse();

    ($this->aksi)($this->peserta, $sesi, 'izinkan');
    expect($screen($this->koordinator, $sesi->fresh()))->toBe(['id' => $this->koordinator->id, 'name' => $this->koordinator->name])
        ->and($screen($this->peserta, $sesi->fresh()))->toBeArray()
        ->and($screen($this->ketua, $sesi->fresh()))->toBeFalse()
        ->and($screen(koordinator(), $sesi->fresh()))->toBeFalse();

    ($this->aksi)($this->peserta, $sesi, 'stop');
    expect($screen($this->koordinator, $sesi->fresh()))->toBeFalse();
});

test('mahasiswa yang sedang dibantu bisa membagikan layar ke penanganannya tanpa persetujuan ketua', function () {
    $help = HelpRequest::factory()->for($this->peserta)->for($this->kelas)->create();
    $bagikan = fn (User $user) => $this->actingAs($user)->postJson("/api/help-requests/{$help->id}/screen-session");

    // Belum ada yang menangani.
    $bagikan($this->peserta)->assertUnprocessable()->assertJsonValidationErrors('help_request');

    $help->update(['status' => HelpStatus::Ditangani, 'handled_by' => $this->koordinator->id]);
    $bagikan($this->ketua)->assertForbidden();

    $sesi = ScreenSession::find($bagikan($this->peserta)
        ->assertCreated()
        ->assertJsonPath('data.status', 'menyambung')
        ->assertJsonPath('data.help_request_id', $help->id)
        ->assertJsonPath('data.viewer.id', $this->koordinator->id)
        ->assertJsonPath('data.approver', null)
        ->json('data.id'));

    // Ketua kelas tidak dilibatkan, cuma dua ujungnya.
    Event::assertDispatched(ScreenSessionChanged::class, fn ($event) => ($this->penerima)($event) === [
        "private-user.{$this->koordinator->id}", "private-user.{$this->peserta->id}",
    ]);
    $this->actingAs($this->ketua)->getJson('/api/screen-sessions')->assertJsonCount(0, 'data');
    $this->actingAs($this->koordinator)->getJson('/api/screen-sessions')->assertJsonCount(1, 'data');
    ($this->aksi)($this->ketua, $sesi, 'stop')->assertForbidden();
    expect(Broadcast::getChannels()['screen.{session}']($this->koordinator, $sesi))->toBeArray();

    ($this->aksi)($this->peserta, $sesi, 'mulai')->assertJsonPath('data.status', 'live');
    $bagikan($this->peserta)->assertUnprocessable();

    // Bantuan selesai, share-nya ikut berhenti.
    $this->actingAs($this->koordinator)->patchJson("/api/help-requests/{$help->id}", ['status' => 'selesai'])->assertOk();
    expect($sesi->fresh())
        ->status->toBe(ScreenStatus::Selesai)
        ->close_reason->toBe(ScreenCloseReason::Dihentikan)
        ->closed_by->toBe($this->koordinator->id);
});

test('share sukarela ditolak kalau web penanganannya tidak terbuka', function () {
    webTerbuka([$this->peserta->id]);
    $help = HelpRequest::factory()->for($this->peserta)->for($this->kelas)
        ->create(['status' => HelpStatus::Ditangani, 'handled_by' => $this->ketua->id]);

    $this->actingAs($this->peserta)->postJson("/api/help-requests/{$help->id}/screen-session")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['help_request' => $this->ketua->name]);
    expect(ScreenSession::count())->toBe(0);
});
