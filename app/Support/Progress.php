<?php

namespace App\Support;

use App\Enums\Activity;
use App\Enums\PresenceState;
use App\Enums\ScheduleType;
use App\Models\ActivitySession;
use App\Models\ClassSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

// Progres pribadi peserta dari activity_sessions: jam aktif (di_kelas), jam ngoding, dan streak.
class Progress
{
    public const CODING = [Activity::Ngoding, Activity::VibeCoding, Activity::NgolahData];

    /**
     * Total per hari sejak $from, cuma hari yang ada datanya. Sesi yang masih jalan dihitung sampai sekarang.
     *
     * @return Collection<int, array{date: string, aktif: int, ngoding: int}>
     */
    public static function perDay(User $user, Carbon $from): Collection
    {
        $now = now();
        $seconds = fn (ActivitySession $s) => $s->duration_seconds ?? max(0, (int) $s->started_at->diffInSeconds($now));

        return $user->activitySessions()
            ->where('state', PresenceState::DiKelas)
            ->where('started_at', '>=', $from)
            ->get()
            ->groupBy(fn (ActivitySession $s) => $s->started_at->toDateString())
            ->map(fn (Collection $day, string $date) => [
                'date' => $date,
                'aktif' => $day->sum($seconds),
                'ngoding' => $day->filter(fn (ActivitySession $s) => in_array($s->activity, self::CODING, true))->sum($seconds),
            ])
            ->sortKeys()
            ->values();
    }

    /**
     * XP dan level. `dari` dan `ke` adalah batas XP level sekarang dan level berikutnya.
     *
     * @param  array{sekarang: int, terbaik: int}  $streak
     * @return array{total: int, level: int, dari: int, ke: int}
     */
    public static function xp(User $user, array $streak): array
    {
        $now = now();
        $coding = $user->activitySessions()
            ->where('state', PresenceState::DiKelas)
            ->whereIn('activity', self::CODING)
            ->get(['started_at', 'duration_seconds'])
            ->sum(fn (ActivitySession $s) => $s->duration_seconds ?? max(0, (int) $s->started_at->diffInSeconds($now)));

        $total = intdiv($coding, 60) * config('kelas3d.xp.per_menit_ngoding')
            + $streak['terbaik'] * config('kelas3d.xp.per_hari_streak');
        $level = self::level($total);

        return ['total' => $total, 'level' => $level, 'dari' => self::batas($level), 'ke' => self::batas($level + 1)];
    }

    public static function level(int $xp): int
    {
        $level = 1;
        while (self::batas($level + 1) <= $xp) {
            $level++;
        }

        return $level;
    }

    private static function batas(int $level): int
    {
        return config('kelas3d.xp.kelipatan_level') * $level * ($level - 1);
    }

    /**
     * Hari aktif berturut-turut. Hari tanpa jadwal kelas (misalnya akhir pekan) tidak memutus streak,
     * dan hari ini yang belum aktif juga belum memutusnya.
     *
     * @return array{sekarang: int, terbaik: int}
     */
    public static function streak(User $user, ?Carbon $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $active = $user->activitySessions()
            ->where('state', PresenceState::DiKelas)
            ->pluck('started_at')
            ->mapWithKeys(fn ($at) => [Carbon::parse($at)->toDateString() => true]);

        if ($active->isEmpty()) {
            return ['sekarang' => 0, 'terbaik' => 0];
        }

        $classDays = ClassSchedule::query()
            ->whereIn('classroom_id', $user->memberships()->select('classroom_id'))
            ->where('type', ScheduleType::Kelas)
            ->pluck('day_of_week')
            ->map(fn ($d) => (int) $d)
            ->unique()
            ->all();

        $run = 0;
        $best = 0;
        for ($d = Carbon::parse($active->keys()->min()); $d->lte($today); $d->addDay()) {
            if ($active->has($d->toDateString())) {
                $best = max($best, ++$run);
            } elseif (! $d->eq($today) && ($classDays === [] || in_array($d->dayOfWeekIso, $classDays, true))) {
                $run = 0;
            }
        }

        return ['sekarang' => $run, 'terbaik' => $best];
    }
}
