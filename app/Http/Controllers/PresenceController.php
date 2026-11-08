<?php

namespace App\Http\Controllers;

use App\Enums\HelpStatus;
use App\Enums\UserRole;
use App\Http\Resources\PresenceResource;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PresenceController extends Controller
{
    /**
     * Keadaan awal ruangan.
     *
     * Koordinator dan anggota kelas itu. Satu item per anggota; yang belum pernah kirim heartbeat dianggap `keluar`.
     * `project`, `file_name`, dan `language` cuma muncul untuk koordinator dan ketua kelas.
     * `help_request` berisi permintaan bantuan yang belum selesai. `coordinators` berisi koordinator yang sedang
     * berkunjung ke kelas ini.
     */
    public function index(Classroom $classroom): AnonymousResourceCollection
    {
        $members = $classroom->members()->with('user.presence')->orderBy('seat_code')->get();
        $helps = $classroom->helpRequests()->where('status', '!=', HelpStatus::Selesai)->get()->keyBy('user_id');
        $members->each(fn ($member) => $member
            ->setRelation('classroom', $classroom)
            ->setRelation('openHelpRequest', $helps->get($member->user_id)));

        $visiting = User::where('role', UserRole::Koordinator)->get()
            ->filter(fn (User $coordinator) => $coordinator->visiting() === $classroom->slug)
            ->map(fn (User $coordinator) => ['id' => $coordinator->id, 'name' => $coordinator->name, 'avatar' => $coordinator->avatar])
            ->values();

        return PresenceResource::collection($members)->additional(['coordinators' => $visiting]);
    }
}
