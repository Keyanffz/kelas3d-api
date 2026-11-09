<?php

namespace App\Http\Resources;

use App\Enums\PresenceState;
use App\Models\ClassroomMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClassroomMember */
class PresenceResource extends JsonResource
{
    /**
     * Satu anggota kelas beserta keadaannya.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $presence = $this->user->presence;
        // Presence yang tercatat di kelas lain dianggap keluar dari kelas ini.
        $here = $presence?->classroom_id === $this->classroom_id ? $presence : null;

        return [
            'member_id' => $this->id,
            'role' => $this->role,
            'seat_code' => $this->seat_code,
            'user' => ['id' => $this->user->id, 'name' => $this->user->name, 'avatar' => $this->user->avatar],
            'state' => $here?->state ?? PresenceState::Keluar,
            'state_reason' => $here?->state_reason,
            'activity' => $here?->activity,
            'app_label' => $here?->app_label,
            'state_since' => $here?->state_since,
            'last_heartbeat_at' => $presence?->last_heartbeat_at,
            'help_request' => $this->openHelpRequest ? [
                'id' => $this->openHelpRequest->id,
                'note' => $this->openHelpRequest->note,
                'status' => $this->openHelpRequest->status,
            ] : null,
            // Detail kerjaan cuma untuk koordinator dan ketua kelas.
            $this->mergeWhen($request->user()->can('manageMembers', $this->classroom), fn () => [
                'project' => $here?->project,
                'file_name' => $here?->file_name,
                'language' => $here?->language,
                'open_apps' => $here?->open_apps,
            ]),
        ];
    }
}
