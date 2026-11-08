<?php

namespace App\Support;

use App\Enums\PresenceState;
use App\Enums\StateReason;
use App\Events\PresenceChanged;
use App\Models\Classroom;
use App\Models\Presence;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Menyimpan hasil PresenceRules ke presences dan activity_sessions.
class PresenceRecorder
{
    // Isi laporan agent yang ikut disimpan ke presences.
    private const REPORT = ['activity', 'app_label', 'project', 'file_name', 'language'];

    public function __construct(private PresenceRules $rules) {}

    /**
     * Satu heartbeat, termasuk yang tertunda di antrean agent ($broadcast false).
     *
     * @param  array<string, mixed>  $report  activity, app_label, project, file_name, language, idle_seconds, local_ip
     */
    public function heartbeat(User $user, array $report, ?string $requestIp, CarbonInterface $at, bool $broadcast = true): Presence
    {
        $presence = Presence::firstOrNew(['user_id' => $user->id]);
        $openSince = $user->activitySessions()->whereNull('ended_at')->value('started_at');

        // Laporan yang lebih tua dari data terakhir tidak mengubah apa pun.
        if ($presence->last_heartbeat_at?->gte($at) || ($openSince && Carbon::parse($openSince)->gt($at))) {
            return $presence;
        }

        $classrooms = Classroom::whereHas('members', fn ($members) => $members->where('user_id', $user->id))
            ->with('schedules')
            ->get();
        $classroom = $this->rules->locate($classrooms, $report['local_ip'] ?? null, $requestIp, $at);
        [$state, $reason] = $this->rules->resolve($classroom, $at, (int) ($report['idle_seconds'] ?? 0), 0);

        $presence->last_heartbeat_at = $at;

        return $this->apply($presence, $state, $reason, $classroom, Arr::only($report, self::REPORT), $at, $broadcast);
    }

    // Dipakai scheduler untuk presence yang tidak kunjung dapat heartbeat.
    public function check(Presence $presence, CarbonInterface $now): Presence
    {
        $silence = $presence->last_heartbeat_at
            ? (int) $presence->last_heartbeat_at->diffInSeconds($now)
            : PHP_INT_MAX;
        // idle_seconds tidak disimpan, jadi kalau terakhir idle dianggap masih idle.
        $idle = $presence->state_reason === StateReason::Idle ? PHP_INT_MAX : 0;
        [$state, $reason] = $this->rules->resolve($presence->classroom, $now, $idle, $silence);

        return $this->apply($presence, $state, $reason, $presence->classroom, $presence->only(self::REPORT), $now, true);
    }

    /**
     * Dipakai juga oleh kelas3d:simulasi untuk membuat anggota palsu keluar.
     *
     * @param  array<string, mixed>  $report
     */
    public function apply(Presence $presence, PresenceState $state, ?StateReason $reason, ?Classroom $classroom, array $report, CarbonInterface $at, bool $broadcast): Presence
    {
        $out = $state === PresenceState::Keluar;

        $presence->fill([
            'classroom_id' => $out ? null : $classroom?->id,
            'state' => $state,
            'state_reason' => $reason,
            // Di luar kelas, aplikasi yang dipakai tidak dicatat.
            ...array_fill_keys(self::REPORT, null),
            ...($out ? [] : $report),
        ]);

        if (! $presence->exists || $presence->isDirty('state')) {
            $presence->state_since = $at;
        }

        $changed = ! $presence->exists || $presence->isDirty(['classroom_id', 'state', 'state_reason', ...self::REPORT]);
        // Sesi baru tiap ruangan, keadaan, aktivitas, project, atau bahasa berganti.
        $newSession = ! $presence->exists || $presence->isDirty(['classroom_id', 'state', 'activity', 'project', 'language']);

        $previousClassroomId = $presence->getOriginal('classroom_id');

        DB::transaction(function () use ($presence, $newSession, $out, $at) {
            if ($newSession) {
                $sessions = $presence->user->activitySessions();
                $open = $sessions->clone()->whereNull('ended_at')->latest('started_at')->first();
                $open?->update([
                    'ended_at' => $at,
                    'duration_seconds' => max(0, (int) $open->started_at->diffInSeconds($at)),
                ]);

                if (! $out) {
                    $sessions->create([
                        ...$presence->only(['classroom_id', 'state', 'activity', 'app_label', 'project', 'language']),
                        'started_at' => $at,
                    ]);
                }
            }

            $presence->save();
        });

        // Kelas asal ikut diberi tahu kalau peserta pindah atau keluar.
        if ($broadcast && $changed) {
            Classroom::whereKey(array_filter([$previousClassroomId, $presence->classroom_id]))
                ->get()
                ->each(fn (Classroom $classroom) => PresenceChanged::dispatch($presence, $classroom));
        }

        return $presence;
    }
}
