<?php

namespace App\Http\Controllers;

use App\Enums\PresenceState;
use App\Http\Requests\ActivityRequest;
use App\Http\Requests\AksesorisRequest;
use App\Http\Requests\AvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\ActivitySession;
use App\Models\User;
use App\Support\Progress;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
        // Aksesoris diatur lewat /api/aksesoris dan tidak ikut terhapus saat tampilan diganti.
        $aksesoris = $user->avatar['aksesoris'] ?? null;
        $user->update(['avatar' => [...$request->validated(), ...($aksesoris ? ['aksesoris' => $aksesoris] : [])]]);

        return new UserResource($user);
    }

    /**
     * Progresku.
     *
     * Cuma untuk akun yang sedang login. `days` berisi jam aktif (waktu `di_kelas`) dan jam ngoding
     * (ngoding, vibe_coding, ngolah_data) per hari selama 90 hari terakhir, dalam detik.
     */
    public function progress(Request $request): JsonResponse
    {
        $user = $request->user();
        $streak = Progress::streak($user);
        $xp = Progress::xp($user, $streak);

        return response()->json(['data' => [
            'days' => Progress::perDay($user, today()->subDays(89)),
            'streak' => $streak,
            'xp' => $xp,
            'aksesoris' => collect(config('kelas3d.aksesoris'))
                ->map(fn (array $a) => [...$a, 'terbuka' => $a['level'] <= $xp['level']])
                ->all(),
            'dipakai' => ['topi' => null, 'meja' => null, ...($user->avatar['aksesoris'] ?? [])],
        ]]);
    }

    /**
     * Pakai aksesoris.
     *
     * Untuk akun sendiri. Tiap slot diisi id aksesoris yang sudah terbuka di level sekarang, atau `null` untuk melepas.
     * Butuh avatar yang sudah dipilih.
     */
    public function aksesoris(AksesorisRequest $request): UserResource
    {
        $user = $request->user();

        if (! $user->avatar) {
            throw ValidationException::withMessages(['topi' => 'Pilih tampilan avatar dulu.']);
        }

        $level = Progress::xp($user, Progress::streak($user))['level'];
        $katalog = collect(config('kelas3d.aksesoris'))->keyBy('id');
        $pilihan = $request->validated();
        foreach ($pilihan as $slot => $id) {
            if ($id && $katalog[$id]['level'] > $level) {
                throw ValidationException::withMessages([$slot => "{$katalog[$id]['nama']} terbuka di level {$katalog[$id]['level']}."]);
            }
        }

        $user->update(['avatar' => [...$user->avatar, 'aksesoris' => $pilihan]]);

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
