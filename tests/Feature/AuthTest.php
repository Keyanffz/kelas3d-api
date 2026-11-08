<?php

use App\Enums\MemberRole;
use App\Models\Classroom;
use App\Models\User;

// Sesi web cuma dibuat untuk request dari domain frontend, jadi tes mengirim Referer localhost:3000.
beforeEach(fn () => $this->withHeader('Referer', 'http://localhost:3000'));

test('login dengan email dan password yang benar', function () {
    $user = User::factory()->create(['email' => 'budi@example.com']);

    $this->postJson('/api/login', ['email' => 'budi@example.com', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

test('login dengan password salah ditolak', function () {
    User::factory()->create(['email' => 'budi@example.com']);

    $this->postJson('/api/login', ['email' => 'budi@example.com', 'password' => 'salah'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

test('akun tanpa password (akun LMS) tidak bisa login pakai password', function () {
    User::factory()->create(['email' => 'lms@example.com', 'password' => null]);

    $this->postJson('/api/login', ['email' => 'lms@example.com', 'password' => 'apa-saja'])
        ->assertUnprocessable();
});

test('isi login divalidasi', function () {
    $this->postJson('/api/login', ['email' => 'bukan-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

test('login dari luar domain frontend ditolak', function () {
    User::factory()->create(['email' => 'budi@example.com']);

    $this->withHeader('Referer', 'http://situs-lain.test')
        ->postJson('/api/login', ['email' => 'budi@example.com', 'password' => 'password'])
        ->assertBadRequest();
});

test('logout', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/logout')
        ->assertNoContent();

    $this->assertGuest('web');
});

test('me berisi user beserta kelas dan role-nya', function () {
    $kelas = Classroom::factory()->create(['slug' => 'developer']);
    $ketua = anggota($kelas, MemberRole::Admin, 'a1');

    $this->actingAs($ketua)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.role', 'peserta')
        ->assertJsonPath('data.classrooms.0.slug', 'developer')
        ->assertJsonPath('data.classrooms.0.role', 'admin')
        ->assertJsonPath('data.classrooms.0.seat_code', 'a1');
});

test('tamu tidak bisa membuka me', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});
