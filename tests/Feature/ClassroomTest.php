<?php

use App\Enums\MemberRole;
use App\Models\Classroom;

test('koordinator melihat semua kelas', function () {
    Classroom::factory()->count(2)->create();

    $this->actingAs(koordinator())
        ->getJson('/api/classrooms')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('peserta cuma melihat kelasnya sendiri', function () {
    $kelas = Classroom::factory()->create();
    Classroom::factory()->create();

    $this->actingAs(anggota($kelas))
        ->getJson('/api/classrooms')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $kelas->slug);
});

test('koordinator membuat kelas', function () {
    $this->actingAs(koordinator())
        ->postJson('/api/classrooms', [
            'name' => 'Developer',
            'slug' => 'developer',
            'network_cidr' => '192.168.10.0/24',
            'public_ip' => '103.10.20.30',
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'developer');

    $this->assertDatabaseHas('classrooms', ['slug' => 'developer', 'network_cidr' => '192.168.10.0/24']);
});

test('data kelas divalidasi', function (array $data, array $errors) {
    Classroom::factory()->create(['slug' => 'developer']);

    $this->actingAs(koordinator())
        ->postJson('/api/classrooms', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'kosong' => [[], ['name', 'slug']],
    'slug dobel' => [['name' => 'Developer 2', 'slug' => 'developer'], ['slug']],
    'slug bukan huruf kecil dan tanda hubung' => [['name' => 'Data Science', 'slug' => 'Data_Science'], ['slug']],
    'cidr tanpa prefix' => [['name' => 'X', 'slug' => 'x', 'network_cidr' => '192.168.10.0'], ['network_cidr']],
    'cidr prefix kebesaran' => [['name' => 'X', 'slug' => 'x', 'network_cidr' => '192.168.10.0/33'], ['network_cidr']],
    'ip publik salah' => [['name' => 'X', 'slug' => 'x', 'public_ip' => 'bukan-ip'], ['public_ip']],
]);

test('ketua kelas tidak boleh membuat kelas', function () {
    $ketua = anggota(Classroom::factory()->create(), MemberRole::Admin);

    $this->actingAs($ketua)
        ->postJson('/api/classrooms', ['name' => 'Baru', 'slug' => 'baru'])
        ->assertForbidden();
});

test('anggota melihat detail kelasnya, orang luar ditolak', function () {
    $kelas = Classroom::factory()->create();

    $this->actingAs(anggota($kelas))->getJson("/api/classrooms/{$kelas->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $kelas->id);

    $this->actingAs(anggota(Classroom::factory()->create()))->getJson("/api/classrooms/{$kelas->slug}")
        ->assertForbidden();
});

test('koordinator mengubah kelas', function () {
    Classroom::factory()->create(['slug' => 'developer']);

    $this->actingAs(koordinator())
        ->putJson('/api/classrooms/developer', [
            'name' => 'Developer',
            'slug' => 'developer',
            'network_cidr' => '10.0.0.0/16',
        ])
        ->assertOk()
        ->assertJsonPath('data.network_cidr', '10.0.0.0/16');
});

test('ketua kelas tidak boleh mengubah kelas', function () {
    $kelas = Classroom::factory()->create();

    $this->actingAs(anggota($kelas, MemberRole::Admin))
        ->putJson("/api/classrooms/{$kelas->slug}", ['name' => 'Ganti', 'slug' => $kelas->slug])
        ->assertForbidden();
});
