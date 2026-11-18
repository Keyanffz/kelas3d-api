<?php

use App\Enums\PresenceState;
use App\Models\Presence;
use App\Models\ScreenSession;
use App\Support\PresenceRecorder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Aturannya sama dengan heartbeat (PresenceRules): tanpa heartbeat 2 menit istirahat, 30 menit atau jam kelas selesai keluar.
Artisan::command('presences:check', function (PresenceRecorder $recorder) {
    $now = now();

    Presence::with(['user', 'classroom.schedules'])
        ->where('state', '!=', PresenceState::Keluar)
        ->each(fn (Presence $presence) => $recorder->check($presence, $now));
})->purpose('Perbarui keadaan peserta yang heartbeat-nya berhenti atau jam kelasnya selesai');

Artisan::command('screen-sessions:expire', fn () => ScreenSession::closeExpired())
    ->purpose('Tutup permintaan lihat layar yang tidak direspons dan sesi yang melewati batas waktu');

Schedule::command('presences:check')->everyThirtySeconds();
Schedule::command('screen-sessions:expire')->everyThirtySeconds();
