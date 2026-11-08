<?php

use App\Enums\MemberRole;
use App\Models\AppRule;
use App\Models\User;

test('koordinator melihat semua aturan, yang prioritasnya tinggi dulu', function () {
    AppRule::factory()->create(['pattern' => 'Code.exe', 'priority' => 10]);
    AppRule::factory()->create(['pattern' => 'ChatGPT', 'priority' => 30, 'is_active' => false]);

    $this->actingAs(koordinator())
        ->getJson('/api/app-rules')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.pattern', 'ChatGPT')
        ->assertJsonPath('data.0.is_active', false);
});

test('koordinator menambah aturan dan agent langsung menerimanya', function () {
    $this->actingAs(koordinator())
        ->postJson('/api/app-rules', [
            'match_on' => 'process',
            'pattern' => 'Cursor.exe',
            'os' => 'windows',
            'activity' => 'vibe_coding',
            'label' => 'Cursor',
            'priority' => 20,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.activity', 'vibe_coding');

    app('auth')->forgetGuards();
    laptop(User::factory()->create());

    $this->getJson('/api/agent/config')->assertJsonPath('app_rules.0.pattern', 'Cursor.exe');
});

test('aturan divalidasi', function () {
    $this->actingAs(koordinator())
        ->postJson('/api/app-rules', ['match_on' => 'icon', 'os' => 'android', 'activity' => 'tidur', 'priority' => 5000])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['match_on', 'pattern', 'os', 'activity', 'label', 'priority']);
});

test('koordinator menonaktifkan lalu menghapus aturan', function () {
    $rule = AppRule::factory()->create();
    $koordinator = koordinator();

    $this->actingAs($koordinator)
        ->patchJson("/api/app-rules/{$rule->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.pattern', 'Code.exe');

    $this->actingAs($koordinator)->deleteJson("/api/app-rules/{$rule->id}")->assertNoContent();
    $this->assertModelMissing($rule);
});

test('selain koordinator tidak boleh mengelola aturan', function () {
    $rule = AppRule::factory()->create();
    $ketua = anggota(kelasDeveloper(), MemberRole::Admin);

    $this->actingAs($ketua)->getJson('/api/app-rules')->assertForbidden();
    $this->actingAs($ketua)->patchJson("/api/app-rules/{$rule->id}", ['is_active' => false])->assertForbidden();
    $this->actingAs($ketua)->deleteJson("/api/app-rules/{$rule->id}")->assertForbidden();
});
