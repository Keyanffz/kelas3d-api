<?php

namespace App\Support;

use App\Enums\PresenceState;
use App\Enums\ScheduleType;
use App\Enums\StateReason;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\IpUtils;

// Aturan ruangan dan keadaan avatar. Dipakai heartbeat dan scheduler, dan bisa dites tanpa HTTP.
class PresenceRules
{
    /**
     * Ruangan dari jaringan: IP lokal cocok network_cidr, atau IP request cocok public_ip.
     * Kelas yang belum diatur jaringannya cukup dicocokkan dengan jadwal.
     *
     * @param  Collection<int, Classroom>  $classrooms  kelas yang diikuti peserta, beserta schedules
     */
    public function locate(Collection $classrooms, ?string $localIp, ?string $requestIp, CarbonInterface $at): ?Classroom
    {
        foreach ($classrooms as $classroom) {
            if ($localIp && $classroom->network_cidr && IpUtils::checkIp($localIp, $classroom->network_cidr)) {
                return $classroom;
            }

            if ($requestIp && $classroom->public_ip && $requestIp === $classroom->public_ip) {
                return $classroom;
            }
        }

        return $classrooms->first(fn (Classroom $classroom) => ! $classroom->network_cidr
            && ! $classroom->public_ip
            && $this->scheduled($classroom, ScheduleType::Kelas, $at));
    }

    /**
     * @return array{PresenceState, ?StateReason}
     */
    public function resolve(?Classroom $classroom, CarbonInterface $at, int $idleSeconds, int $silenceSeconds): array
    {
        return match (true) {
            // Di luar jaringan kelas atau jam kelas sudah selesai.
            ! $classroom, ! $this->scheduled($classroom, ScheduleType::Kelas, $at) => [PresenceState::Keluar, null],
            $silenceSeconds >= config('kelas3d.out_after_silence') => [PresenceState::Keluar, StateReason::Terputus],
            $this->scheduled($classroom, ScheduleType::Istirahat, $at) => [PresenceState::Istirahat, StateReason::JadwalIstirahat],
            $silenceSeconds >= config('kelas3d.rest_after_silence') => [PresenceState::Istirahat, StateReason::Terputus],
            $idleSeconds >= config('kelas3d.idle_limit') => [PresenceState::Istirahat, StateReason::Idle],
            default => [PresenceState::DiKelas, null],
        };
    }

    public function scheduled(Classroom $classroom, ScheduleType $type, CarbonInterface $at): bool
    {
        $time = $at->format('H:i:s');

        return $classroom->schedules->contains(fn (ClassSchedule $schedule) => $schedule->type === $type
            && $schedule->day_of_week === $at->dayOfWeekIso
            && $schedule->starts_at <= $time
            && $time < $schedule->ends_at);
    }
}
