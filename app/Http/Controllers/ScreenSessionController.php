<?php

namespace App\Http\Controllers;

use App\Enums\HelpStatus;
use App\Enums\MemberRole;
use App\Enums\ScreenCloseReason;
use App\Enums\ScreenStatus;
use App\Events\ScreenSessionChanged;
use App\Http\Requests\StoreScreenSessionRequest;
use App\Http\Requests\UpdateScreenSessionRequest;
use App\Http\Resources\ScreenSessionResource;
use App\Models\Classroom;
use App\Models\HelpRequest;
use App\Models\ScreenSession;
use App\Models\User;
use App\Support\WebOnline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScreenSessionController extends Controller
{
    private const WITH = ['classroom', 'viewer', 'target', 'approver', 'closer'];

    /**
     * Sesi lihat layar yang sedang berjalan dan melibatkan saya.
     *
     * Koordinator: yang ia minta. Ketua kelas: semua di kelasnya kecuali share sukarela. Mahasiswa: yang sudah
     * disetujui ketua kelas atau yang ia bagikan sendiri. Penonton share sukarela juga melihatnya.
     * `ice_servers` adalah konfigurasi ICE untuk WebRTC, dari `ICE_SERVERS` di `.env`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        ScreenSession::closeExpired();
        $user = $request->user();

        $sessions = ScreenSession::with(self::WITH)
            ->whereIn('status', ScreenStatus::active())
            ->where(fn ($query) => $query
                ->where('viewer_id', $user->id)
                ->orWhere(fn ($query) => $query->where('target_id', $user->id)->whereNotNull('approved_at'))
                ->orWhere(fn ($query) => $query->whereNull('help_request_id')
                    ->whereIn('classroom_id', $user->memberships()->where('role', MemberRole::Admin)->select('classroom_id'))))
            ->latest('id')
            ->get();

        return ScreenSessionResource::collection($sessions)->additional(['ice_servers' => config('kelas3d.screen.ice_servers')]);
    }

    /**
     * Log lihat layar satu kelas.
     *
     * Koordinator dan ketua kelas itu. 50 terakhir: siapa minta, siapa menyetujui, kapan mulai dan selesai.
     */
    public function log(Classroom $classroom): AnonymousResourceCollection
    {
        return ScreenSessionResource::collection(
            ScreenSession::with(self::WITH)->whereBelongsTo($classroom)->latest('id')->limit(50)->get()
        );
    }

    /**
     * Minta lihat layar mahasiswa.
     *
     * Khusus koordinator. Permintaan diteruskan ke ketua kelas, lalu ke mahasiswanya; tiap tahap kedaluwarsa
     * kalau tidak direspons. Ditolak 422 kalau mahasiswa itu masih punya sesi berjalan, atau web ketua kelas
     * atau web mahasiswa sedang tidak terbuka. Kalau yang dituju ketua kelas sendiri, langsung ke izin miliknya.
     */
    public function store(StoreScreenSessionRequest $request, WebOnline $online): ScreenSessionResource
    {
        $classroom = Classroom::firstWhere('slug', $request->validated('classroom'));
        $target = User::findOrFail($request->validated('target_user_id'));
        $ketua = User::find($classroom->members()->where('role', MemberRole::Admin)->value('user_id'));
        $sendiri = $ketua->is($target);

        if (! $sendiri && ! $online->has($ketua)) {
            throw ValidationException::withMessages(['classroom' => "Ketua kelas ({$ketua->name}) sedang tidak membuka web Kelas 3D."]);
        }

        if (! $online->has($target)) {
            throw ValidationException::withMessages(['target_user_id' => "{$target->name} sedang tidak membuka web Kelas 3D."]);
        }

        ScreenSession::closeExpired();

        $session = DB::transaction(function () use ($request, $classroom, $target, $sendiri) {
            // Kunci baris mahasiswa supaya dua permintaan bersamaan tidak sama-sama lolos.
            User::whereKey($target->id)->lockForUpdate()->first();

            if (ScreenSession::where('target_id', $target->id)->whereIn('status', ScreenStatus::active())->exists()) {
                throw ValidationException::withMessages(['target_user_id' => "Masih ada sesi lihat layar untuk {$target->name}."]);
            }

            return $classroom->screenSessions()->create([
                'viewer_id' => $request->user()->id,
                'target_id' => $target->id,
                'reason' => $request->validated('reason'),
                'status' => $sendiri ? ScreenStatus::MenungguMahasiswa : ScreenStatus::MenungguKetua,
                'approved_at' => $sendiri ? now() : null,
                'expires_at' => now()->addSeconds(config('kelas3d.screen.request_ttl')),
            ]);
        });

        ScreenSessionChanged::dispatch($session);

        /** @status 201 */
        return new ScreenSessionResource($session);
    }

    /**
     * Bagikan layar ke yang menangani bantuan.
     *
     * Khusus mahasiswa yang angkat tangan, setelah permintaannya ditangani. Tanpa persetujuan ketua kelas karena
     * mahasiswa sendiri yang memulai, jadi sesi langsung `menyambung`. Ditolak 422 kalau web penanganannya tidak
     * terbuka atau mahasiswa itu masih punya sesi berjalan.
     */
    public function share(Request $request, HelpRequest $helpRequest, WebOnline $online): ScreenSessionResource
    {
        if ($helpRequest->user_id !== $request->user()->id) {
            abort(403);
        }

        $viewer = $helpRequest->handler;

        if ($helpRequest->status !== HelpStatus::Ditangani || ! $viewer) {
            throw ValidationException::withMessages(['help_request' => 'Bantuanmu belum ditangani siapa pun.']);
        }

        if (! $online->has($viewer)) {
            throw ValidationException::withMessages(['help_request' => "{$viewer->name} sedang tidak membuka web Kelas 3D."]);
        }

        ScreenSession::closeExpired();

        $session = DB::transaction(function () use ($request, $helpRequest, $viewer) {
            User::whereKey($request->user()->id)->lockForUpdate()->first();

            if (ScreenSession::where('target_id', $request->user()->id)->whereIn('status', ScreenStatus::active())->exists()) {
                throw ValidationException::withMessages(['help_request' => 'Layarmu masih dalam sesi lihat layar lain.']);
            }

            return ScreenSession::create([
                'classroom_id' => $helpRequest->classroom_id,
                'viewer_id' => $viewer->id,
                'target_id' => $request->user()->id,
                'help_request_id' => $helpRequest->id,
                'status' => ScreenStatus::Menyambung,
                'approved_at' => now(),
                'consented_at' => now(),
                'expires_at' => now()->addSeconds(config('kelas3d.screen.request_ttl')),
            ]);
        });

        ScreenSessionChanged::dispatch($session);

        /** @status 201 */
        return new ScreenSessionResource($session);
    }

    /**
     * Jawab atau hentikan sesi lihat layar.
     *
     * `setujui` dan `tolak` untuk ketua kelas saat menunggu ketua. `izinkan`, `tolak`, dan `mulai` (stream sudah
     * tersambung) untuk mahasiswanya. `stop` untuk koordinator peminta, ketua kelas, dan mahasiswanya, kapan pun
     * selama sesi berjalan. Aksi yang tidak cocok dengan status sekarang ditolak 422.
     */
    public function update(UpdateScreenSessionRequest $request, ScreenSession $screenSession): ScreenSessionResource
    {
        ScreenSession::closeExpired();
        $user = $request->user();
        $action = $request->validated('action');

        $session = DB::transaction(function () use ($request, $screenSession, $user, $action) {
            $session = ScreenSession::lockForUpdate()->findOrFail($screenSession->id);
            $ketua = $user->isAdminOf($session->classroom);
            $target = $user->id === $session->target_id;
            $ttl = now()->addSeconds(config('kelas3d.screen.request_ttl'));
            $tutup = fn (ScreenStatus $status, ScreenCloseReason $reason) => [
                'status' => $status, 'close_reason' => $reason, 'closed_by' => $user->id, 'ended_at' => now(),
            ];
            $stop = $request->enum('reason', ScreenCloseReason::class) ?? ScreenCloseReason::Dihentikan;

            $changes = match (true) {
                ! $session->isActive() => null,
                $action === 'stop' => $tutup($stop === ScreenCloseReason::IzinBrowser ? ScreenStatus::Ditolak : ScreenStatus::Selesai, $stop),
                $ketua && $session->status === ScreenStatus::MenungguKetua => match ($action) {
                    'setujui' => ['status' => ScreenStatus::MenungguMahasiswa, 'approver_id' => $user->id, 'approved_at' => now(), 'expires_at' => $ttl],
                    'tolak' => $tutup(ScreenStatus::Ditolak, ScreenCloseReason::DitolakKetua),
                    default => null,
                },
                $target && $session->status === ScreenStatus::MenungguMahasiswa => match ($action) {
                    'izinkan' => ['status' => ScreenStatus::Menyambung, 'consented_at' => now(), 'expires_at' => $ttl],
                    'tolak' => $tutup(ScreenStatus::Ditolak, ScreenCloseReason::DitolakMahasiswa),
                    default => null,
                },
                $target && $session->status === ScreenStatus::Menyambung && $action === 'mulai' => [
                    'status' => ScreenStatus::Live, 'started_at' => now(), 'expires_at' => now()->addSeconds(config('kelas3d.screen.max_duration')),
                ],
                default => null,
            };

            if ($changes === null) {
                throw ValidationException::withMessages(['action' => 'Aksi ini tidak berlaku untuk status sesi sekarang.']);
            }

            $session->update($changes);

            return $session;
        });

        ScreenSessionChanged::dispatch($session);

        return new ScreenSessionResource($session);
    }
}
