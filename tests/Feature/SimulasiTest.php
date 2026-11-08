<?php

use App\Events\HelpRequested;
use App\Events\PresenceChanged;
use App\Models\ClassSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->kelas = kelasDeveloper();
    Event::fake([PresenceChanged::class, HelpRequested::class]);
});

test('simulasi membuat anggota palsu di kursi kosong lalu menghapus jadwal sementaranya', function () {
    anggota($this->kelas, seat: 'a1');

    $this->artisan('kelas3d:simulasi', ['--jumlah' => 3, '--putaran' => 1, '--istirahat' => 10])->assertSuccessful();

    $seats = $this->kelas->members()
        ->whereHas('user', fn ($user) => $user->where('email', 'like', '%@simulasi.test'))
        ->orderBy('id')
        ->pluck('seat_code')
        ->all();
    expect($seats)->toBe(['a2', 'a3', 'a4'])
        ->and(ClassSchedule::count())->toBe(2);
});

test('--hapus menghapus anggota palsu', function () {
    $this->artisan('kelas3d:simulasi', ['--jumlah' => 2, '--putaran' => 1])->assertSuccessful();
    $this->artisan('kelas3d:simulasi', ['--hapus' => true])->assertSuccessful();

    expect(User::where('email', 'like', '%@simulasi.test')->count())->toBe(0)
        ->and($this->kelas->members()->count())->toBe(0);
});

test('simulasi menolak jalan di luar lokal', function () {
    $this->app['env'] = 'production';

    $this->artisan('kelas3d:simulasi', ['--putaran' => 1])->assertFailed();
});
