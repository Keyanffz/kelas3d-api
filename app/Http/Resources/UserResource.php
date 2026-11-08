<?php

namespace App\Http\Resources;

use App\Models\ClassroomMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            /** @var array{tipe_badan: string, rambut: string, warna: array{kulit: string, rambut: string, celana: string}}|null */
            'avatar' => $this->avatar,
            // Kelas yang diikuti beserta role-nya. Koordinator tidak jadi anggota kelas mana pun.
            'classrooms' => $this->whenLoaded('memberships', fn (Collection $memberships) => $memberships->map(fn (ClassroomMember $member) => [
                'id' => $member->classroom->id,
                'name' => $member->classroom->name,
                'slug' => $member->classroom->slug,
                'role' => $member->role,
                'seat_code' => $member->seat_code,
            ])),
        ];
    }
}
