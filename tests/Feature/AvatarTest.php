<?php

use App\Models\User;

$avatar = [
    'tipe_badan' => 'perempuan',
    'rambut' => 'rambut_2',
    'warna' => ['kulit' => '#c68642', 'rambut' => '#2b1b17', 'celana' => '#1f2937'],
];

test('peserta memilih tampilan avatarnya', function () use ($avatar) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/users/me/avatar', $avatar)
        ->assertOk()
        ->assertJsonPath('data.avatar', $avatar);

    expect($user->fresh()->avatar)->toBe($avatar);
});

test('isi avatar divalidasi', function () {
    $this->actingAs(User::factory()->create())
        ->patchJson('/api/users/me/avatar', [
            'tipe_badan' => 'robot',
            'rambut' => 'rambut_9',
            'warna' => ['kulit' => 'cokelat', 'rambut' => '#2b1b17', 'celana' => '#1f2937', 'sepatu' => '#000000'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tipe_badan', 'rambut', 'warna', 'warna.kulit']);
});

test('koordinator boleh mengubah avatar peserta', function () use ($avatar) {
    $peserta = User::factory()->create();

    $this->actingAs(koordinator())
        ->patchJson("/api/users/{$peserta->id}/avatar", $avatar)
        ->assertOk();

    expect($peserta->fresh()->avatar)->toBe($avatar);
});

test('peserta tidak boleh mengubah avatar orang lain', function () use ($avatar) {
    $lain = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patchJson("/api/users/{$lain->id}/avatar", $avatar)
        ->assertForbidden();
});
