<?php

use App\Enums\MemberRole;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Kelas developer punya kursi a1–a6, b1–b4, c1–c6.
beforeEach(fn () => $this->kelas = Classroom::factory()->create(['slug' => 'developer']));

test('ketua kelas melihat daftar anggota', function () {
    $ketua = anggota($this->kelas, MemberRole::Admin, 'a1');
    anggota($this->kelas, seat: 'a2');

    $this->actingAs($ketua)
        ->getJson('/api/classrooms/developer/members')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.user.id', $ketua->id);
});

test('anggota biasa tidak boleh mengelola anggota', function () {
    $this->actingAs(anggota($this->kelas))
        ->getJson('/api/classrooms/developer/members')
        ->assertForbidden();
});

test('ketua kelas lain tidak boleh mengelola kelas ini', function () {
    $ketuaLain = anggota(Classroom::factory()->create(['slug' => 'data-science']), MemberRole::Admin);

    $this->actingAs($ketuaLain)
        ->postJson('/api/classrooms/developer/members', ['email' => 'budi@example.com', 'name' => 'Budi', 'password' => 'rahasia123'])
        ->assertForbidden();
});

test('ketua kelas menambah anggota baru sekaligus membuat akunnya', function () {
    $this->actingAs(anggota($this->kelas, MemberRole::Admin))
        ->postJson('/api/classrooms/developer/members', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'seat_code' => 'B2',
        ])
        ->assertCreated()
        ->assertJsonPath('data.seat_code', 'b2')
        ->assertJsonPath('data.role', 'anggota')
        ->assertJsonPath('data.user.email', 'budi@example.com');

    $budi = User::firstWhere('email', 'budi@example.com');

    expect($budi->role)->toBe(UserRole::Peserta)
        ->and(Hash::check('rahasia123', $budi->password))->toBeTrue();
});

test('akun yang sudah ada cuma ditambahkan, password-nya tidak berubah', function () {
    $budi = User::factory()->create(['email' => 'budi@example.com']);

    $this->actingAs(koordinator())
        ->postJson('/api/classrooms/developer/members', ['email' => 'budi@example.com', 'password' => 'diganti123'])
        ->assertCreated()
        ->assertJsonPath('data.user.id', $budi->id);

    expect(Hash::check('password', $budi->fresh()->password))->toBeTrue();
});

test('akun baru wajib punya nama, password boleh kosong', function () {
    $this->actingAs(koordinator())
        ->postJson('/api/classrooms/developer/members', ['email' => 'baru@example.com', 'password' => 'pendek'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'password']);

    $this->actingAs(koordinator())
        ->postJson('/api/classrooms/developer/members', ['email' => 'baru@example.com', 'name' => 'Baru'])
        ->assertCreated();

    expect(User::firstWhere('email', 'baru@example.com')->password)->toBeNull();
});

test('koordinator dan anggota yang sudah terdaftar ditolak', function () {
    $koordinator = koordinator();
    $sudahAda = anggota($this->kelas);

    foreach ([$koordinator->email, $sudahAda->email] as $email) {
        $this->actingAs($koordinator)
            ->postJson('/api/classrooms/developer/members', ['email' => $email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
});

test('kode kursi harus ada di kelas itu', function () {
    $dataScience = Classroom::factory()->create(['slug' => 'data-science']);
    $koordinator = koordinator();
    $budi = ['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'rahasia123', 'seat_code' => 'b8'];

    // b8 cuma ada di Data Science.
    $this->actingAs($koordinator)
        ->postJson('/api/classrooms/developer/members', $budi)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('seat_code');

    $this->actingAs($koordinator)
        ->postJson("/api/classrooms/{$dataScience->slug}/members", $budi)
        ->assertCreated();
});

test('satu kursi tidak boleh dipakai dua anggota', function () {
    anggota($this->kelas, seat: 'a1');

    $this->actingAs(koordinator())
        ->postJson('/api/classrooms/developer/members', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'seat_code' => 'A1',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('seat_code');
});

test('ketua kelas memindah kursi anggota', function () {
    $anggota = ClassroomMember::factory()->for($this->kelas)->create(['seat_code' => 'a1']);

    $this->actingAs(anggota($this->kelas, MemberRole::Admin))
        ->patchJson("/api/classrooms/developer/members/{$anggota->id}", ['seat_code' => 'C6'])
        ->assertOk()
        ->assertJsonPath('data.seat_code', 'c6');
});

test('koordinator menunjuk ketua baru dan ketua lama jadi anggota', function () {
    $lama = ClassroomMember::factory()->for($this->kelas)->admin()->create();
    $baru = ClassroomMember::factory()->for($this->kelas)->create();

    $this->actingAs(koordinator())
        ->patchJson("/api/classrooms/developer/members/{$baru->id}", ['role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('data.role', 'admin');

    expect($lama->fresh()->role)->toBe(MemberRole::Anggota);
});

test('ketua kelas tidak boleh menunjuk ketua', function () {
    $anggota = ClassroomMember::factory()->for($this->kelas)->create();

    $this->actingAs(anggota($this->kelas, MemberRole::Admin))
        ->patchJson("/api/classrooms/developer/members/{$anggota->id}", ['role' => 'admin'])
        ->assertForbidden();
});

test('ketua kelas mengeluarkan anggota', function () {
    $anggota = ClassroomMember::factory()->for($this->kelas)->create();

    $this->actingAs(anggota($this->kelas, MemberRole::Admin))
        ->deleteJson("/api/classrooms/developer/members/{$anggota->id}")
        ->assertNoContent();

    $this->assertModelMissing($anggota);
});

test('anggota kelas lain tidak bisa diubah lewat kelas ini', function () {
    $lain = ClassroomMember::factory()->create();

    $this->actingAs(koordinator())
        ->patchJson("/api/classrooms/developer/members/{$lain->id}", ['seat_code' => 'a1'])
        ->assertNotFound();
});
