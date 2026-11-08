<?php

namespace App\Http\Controllers;

use App\Enums\PresenceState;
use App\Http\Requests\ActivityRequest;
use App\Http\Requests\AvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\ActivitySession;
use App\Models\User;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UserController extends Controller
{
    /**
     * Pilih tampilan avatar.
     *
     * Pemilik akun, atau koordinator untuk user mana pun.
     */
    #[PathParameter('user', 'ID user, atau `me` untuk akun yang sedang login.', type: 'string')]
    public function updateAvatar(AvatarRequest $request, User $user): UserResource
    {
        $user->update(['avatar' => $request->validated()]);

        return new UserResource($user);
    }

    /**
     * Rekap aktivitas peserta.
     *
     * Pemiliknya, koordinator, atau ketua kelas dari kelas user itu. Default 7 hari terakhir.
     * Semua angka dalam detik; sesi yang masih jalan dihitung sampai sekarang. Rekap project dan bahasa
     * cuma dari waktu `di_kelas`.
     */
    #[PathParameter('user', 'ID user, atau `me` untuk akun yang sedang login.', type: 'string')]
    public function activity(ActivityRequest $request, User $user): JsonResponse
    {
        $from = Carbon::parse($request->validated('from') ?? today()->subDays(6)->toDateString())->startOfDay();
        $to = Carbon::parse($request->validated('to') ?? today()->toDateString())->endOfDay();
        $now = now();

        $sessions = $user->activitySessions()->whereBetween('started_at', [$from, $to])->get();
        $total = fn (Collection $group) => $group->sum(fn (ActivitySession $session) => $session->duration_seconds
            ?? max(0, (int) $session->started_at->diffInSeconds($now)));
        $working = $sessions->where('state', PresenceState::DiKelas);
        $per = fn (string $field) => $working->whereNotNull($field)->groupBy($field)
            ->map(fn (Collection $group, int|string $name) => [$field => (string) $name, 'seconds' => $total($group)])
            ->sortByDesc('seconds')
            ->values();

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'per_day' => $sessions->groupBy(fn (ActivitySession $session) => $session->started_at->toDateString())
                ->map(fn (Collection $day, string $date) => [
                    'date' => $date,
                    'di_kelas' => $total($day->where('state', PresenceState::DiKelas)),
                    'istirahat' => $total($day->where('state', PresenceState::Istirahat)),
                ])
                ->sortKeys()
                ->values(),
            'per_project' => $per('project'),
            'per_language' => $per('language'),
        ]]);
    }
}
