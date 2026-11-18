<?php

namespace App\Http\Controllers;

use App\Enums\HelpStatus;
use App\Enums\PresenceState;
use App\Enums\UserRole;
use App\Events\PresenceChanged;
use App\Http\Requests\FocusRequest;
use App\Http\Resources\PresenceResource;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PresenceController extends Controller
{
    /**
     * Keadaan awal ruangan.
     *
     * Koordinator dan anggota kelas itu. Satu item per anggota; yang belum pernah kirim heartbeat dianggap `keluar`.
     * `project`, `file_name`, dan `language` cuma muncul untuk koordinator dan ketua kelas.
     * `help_request` berisi permintaan bantuan yang belum selesai, beserta penanganannya dan waktu angkat tangan
     * untuk urutan antrean. `coordinators` berisi koordinator yang sedang
     * berkunjung ke kelas ini.
     */
    public function index(Classroom $classroom): AnonymousResourceCollection
    {
        $members = $classroom->members()->with('user.presence')->orderBy('seat_code')->get();
        $helps = $classroom->helpRequests()->with('handler')->where('status', '!=', HelpStatus::Selesai)->get()->keyBy('user_id');
        $members->each(fn ($member) => $member
            ->setRelation('classroom', $classroom)
            ->setRelation('openHelpRequest', $helps->get($member->user_id)));

        $visiting = User::where('role', UserRole::Koordinator)->get()
            ->filter(fn (User $coordinator) => $coordinator->visiting() === $classroom->slug)
            ->map(fn (User $coordinator) => ['id' => $coordinator->id, 'name' => $coordinator->name, 'avatar' => $coordinator->avatar])
            ->values();

        return PresenceResource::collection($members)->additional(['coordinators' => $visiting]);
    }

    /**
     * Nyalakan atau matikan mode fokus.
     *
     * Untuk diri sendiri, selama sedang di kelas atau istirahat. Selama fokus, pengumuman tidak dikirim ke
     * agent lewat balasan heartbeat, dan web menahannya; permintaan lihat layar dan teguran tetap masuk.
     * Fokus selesai sendiri saat peserta keluar. Kelas menerima `PresenceChanged` dengan `focus` yang baru.
     */
    public function focus(FocusRequest $request): JsonResponse
    {
        $presence = $request->user()->presence;

        if (! $presence || $presence->state === PresenceState::Keluar) {
            throw ValidationException::withMessages(['on' => 'Mode fokus cuma bisa dipakai saat kamu di kelas.']);
        }

        $on = $request->boolean('on');

        if ($on !== ($presence->focus_since !== null)) {
            $presence->update(['focus_since' => $on ? now() : null]);
            PresenceChanged::dispatch($presence, $presence->classroom);
        }

        return response()->json(['data' => ['focus' => $on, 'focus_since' => $presence->focus_since]]);
    }
}
