<?php

use App\Enums\DeviceStatus;
use App\Enums\MemberRole;
use App\Enums\PresenceState;
use App\Enums\StateReason;
use App\Events\PresenceChanged;
use App\Models\ClassroomMember;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->kelas = kelasDeveloper();
    $this->ketua = anggota($this->kelas, MemberRole::Admin, 'a1');
    $this->member = ClassroomMember::factory()->for($this->kelas)->create(['seat_code' => 'a2']);
    $this->peserta = $this->member->user;
    $this->travelTo('2026-11-09 09:00');
});

function kodeInstall(User $oleh, ClassroomMember $member): string
{
    return test()->actingAs($oleh, 'web')
        ->postJson("/api/classrooms/{$member->classroom->slug}/members/{$member->id}/install-code")
        ->assertCreated()
        ->json('data.code');
}

function loginAgent(string $kode): TestResponse
{
    return test()->postJson('/api/agent/login', ['code' => $kode, 'hostname' => 'laptop-budi', 'os' => 'windows', 'agent_version' => '0.1.0']);
}

test('ketua kelas membuat perintah install pribadi untuk anggota', function () {
    $data = $this->actingAs($this->ketua, 'web')
        ->postJson("/api/classrooms/developer/members/{$this->member->id}/install-code")
        ->assertCreated()
        ->json('data');

    expect($data['code'])->toMatch('/^[A-Z]{4}-[A-Z]{4}$/')
        ->and($data['commands']['windows'])->toBe("irm http://localhost:8000/install.ps1 | iex; kelas3d login {$data['code']}")
        ->and($data['commands']['macos_linux'])->toBe("curl -fsSL http://localhost:8000/install.sh | sh -s -- {$data['code']}")
        ->and($data['expires_at'])->toBe('2026-11-10T09:00:00+07:00');
});

test('agent login dengan kode, laptop langsung aktif atas nama pemilik kode', function () {
    $kode = kodeInstall($this->ketua, $this->member);

    $token = loginAgent(strtolower(str_replace('-', ' ', $kode)))
        ->assertOk()
        ->assertJsonPath('user.id', $this->peserta->id)
        ->json('token');

    $device = Device::sole();

    expect($device->user_id)->toBe($this->peserta->id)
        ->and($device->status)->toBe(DeviceStatus::Approved)
        ->and($device->approved_by)->toBe($this->ketua->id)
        ->and($device->hostname)->toBe('laptop-budi');

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/agent/heartbeat', laporan())->assertOk();
});

test('kode cuma berlaku sekali, kedaluwarsa setelah sehari, dan kode lama batal kalau dibuat ulang', function () {
    $kode = kodeInstall($this->ketua, $this->member);
    loginAgent($kode)->assertOk();
    loginAgent($kode)->assertUnprocessable()->assertJsonValidationErrors('code');

    $lama = kodeInstall($this->ketua, $this->member);
    $baru = kodeInstall($this->ketua, $this->member);
    loginAgent($lama)->assertUnprocessable();

    $this->travel(25)->hours();
    loginAgent($baru)->assertUnprocessable();
    loginAgent('ZZZZ-ZZZZ')->assertUnprocessable();

    expect(Device::count())->toBe(1);
});

test('isi login agent divalidasi', function () {
    $this->postJson('/api/agent/login', ['os' => 'android'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'hostname', 'os', 'agent_version']);
});

test('yang boleh membuat kode cuma koordinator dan ketua kelas itu', function () {
    $ketuaLain = anggota(kelasDeveloper(['slug' => 'data-science']), MemberRole::Admin);

    $this->actingAs($this->peserta, 'web')->postJson("/api/classrooms/developer/members/{$this->member->id}/install-code")->assertForbidden();
    $this->actingAs($ketuaLain, 'web')->postJson("/api/classrooms/developer/members/{$this->member->id}/install-code")->assertForbidden();
    $this->actingAs(koordinator(), 'web')->postJson("/api/classrooms/developer/members/{$this->member->id}/install-code")->assertCreated();

    // Anggota kelas lain tidak bisa dibuatkan kode lewat kelas ini.
    $lain = ClassroomMember::factory()->create();
    $this->actingAs(koordinator(), 'web')->postJson("/api/classrooms/developer/members/{$lain->id}/install-code")->assertNotFound();
});

test('agent membuat link masuk web, peserta masuk tanpa password', function () {
    $token = Device::factory()->approved()->for($this->peserta)->create()->createToken('agent')->plainTextToken;

    $link = $this->withToken($token)
        ->postJson('/api/agent/web-link')
        ->assertOk()
        ->assertJsonPath('expires_in', 120)
        ->json('url');

    expect($link)->toStartWith('http://localhost:3000/masuk?token=');
    parse_str(parse_url($link, PHP_URL_QUERY), $query);

    app('auth')->forgetGuards();
    $this->withToken('')
        ->withHeader('Referer', 'http://localhost:3000')
        ->postJson('/api/login/link', ['token' => $query['token']])
        ->assertOk()
        ->assertJsonPath('data.id', $this->peserta->id)
        ->assertJsonPath('data.classrooms.0.slug', 'developer');

    $this->assertAuthenticatedAs($this->peserta, 'web');

    // Link cuma berlaku sekali.
    $this->postJson('/api/login/link', ['token' => $query['token']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});

test('link masuk kedaluwarsa setelah 2 menit dan harus dari domain frontend', function () {
    $token = Device::factory()->approved()->for($this->peserta)->create()->createToken('agent')->plainTextToken;
    $ambil = function () use ($token) {
        app('auth')->forgetGuards();
        parse_str(parse_url($this->withToken($token)->postJson('/api/agent/web-link')->json('url'), PHP_URL_QUERY), $query);
        app('auth')->forgetGuards();

        return $query['token'];
    };

    $basi = $ambil();
    $this->travel(3)->minutes();
    $this->withToken('')->withHeader('Referer', 'http://localhost:3000')
        ->postJson('/api/login/link', ['token' => $basi])
        ->assertUnprocessable();

    $this->withToken('')->withHeader('Referer', 'http://situs-lain.test')
        ->postJson('/api/login/link', ['token' => $ambil()])
        ->assertBadRequest();
});

test('revoke mematikan token dan heartbeat ditolak', function () {
    $device = Device::factory()->approved()->for($this->peserta)->create();
    $token = $device->createToken('agent')->plainTextToken;

    $this->withToken($token)->postJson('/api/agent/heartbeat', laporan())->assertOk();

    $this->actingAs($this->ketua, 'web')->patchJson("/api/devices/{$device->id}", ['status' => 'approved'])->assertUnprocessable();
    $this->actingAs(anggota($this->kelas), 'web')->patchJson("/api/devices/{$device->id}", ['status' => 'revoked'])->assertForbidden();
    $this->actingAs($this->ketua, 'web')->patchJson("/api/devices/{$device->id}", ['status' => 'revoked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked');
    app('auth')->forgetGuards();

    expect($device->tokens()->count())->toBe(0);
    $this->withToken($token)->postJson('/api/agent/heartbeat', laporan())->assertUnauthorized();
});

test('revoke laptop aktif terakhir membuat pemiliknya langsung keluar', function () {
    Event::fake([PresenceChanged::class]);
    $a = Device::factory()->approved()->for($this->peserta)->create();
    $b = Device::factory()->approved()->for($this->peserta)->create();
    $this->withToken($a->createToken('agent')->plainTextToken)->postJson('/api/agent/heartbeat', laporan())->assertOk();
    app('auth')->forgetGuards();
    $this->travel(1)->minute();

    // Laptop b masih aktif, jadi keadaannya dibiarkan.
    $this->actingAs($this->ketua, 'web')->patchJson("/api/devices/{$a->id}", ['status' => 'revoked'])->assertOk();
    expect($this->peserta->presence()->first()->state)->toBe(PresenceState::DiKelas);

    $this->actingAs($this->ketua, 'web')->patchJson("/api/devices/{$b->id}", ['status' => 'revoked'])->assertOk();
    $presence = $this->peserta->presence()->first();

    expect($presence->state)->toBe(PresenceState::Keluar)
        ->and($presence->state_reason)->toBe(StateReason::Terputus)
        ->and($presence->app_label)->toBeNull()
        ->and($this->peserta->activitySessions()->whereNull('ended_at')->exists())->toBeFalse();
    Event::assertDispatched(PresenceChanged::class, fn (PresenceChanged $e) => $e->broadcastWith()['state'] === 'keluar'
        && $e->broadcastWith()['app_label'] === null);
});

test('heartbeat ditolak kalau laptop tidak aktif', function () {
    $device = Device::factory()->for($this->peserta)->create(['status' => DeviceStatus::Revoked]);

    $this->withToken($device->createToken('agent')->plainTextToken)
        ->postJson('/api/agent/heartbeat', laporan())
        ->assertForbidden();
});

test('token device tidak berlaku di endpoint web, sesi web tidak berlaku di endpoint agent', function () {
    $token = Device::factory()->approved()->for($this->peserta)->create()->createToken('agent')->plainTextToken;

    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withToken('')->actingAs(User::factory()->create(), 'web')->getJson('/api/agent/config')->assertUnauthorized();
});

test('ketua kelas melihat daftar laptop anggota kelasnya', function () {
    Device::factory()->approved()->for($this->peserta)->create(['hostname' => 'laptop-budi']);
    Device::factory()->approved()->create(['hostname' => 'kelas-lain']);

    $this->actingAs($this->ketua, 'web')
        ->getJson('/api/classrooms/developer/devices')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.hostname', 'laptop-budi')
        ->assertJsonPath('data.0.user.id', $this->peserta->id);

    $this->actingAs($this->peserta, 'web')->getJson('/api/classrooms/developer/devices')->assertForbidden();
});
