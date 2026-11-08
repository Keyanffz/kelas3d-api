<?php

namespace App\Http\Resources;

use App\Models\ClassroomMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClassroomMember */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'seat_code' => $this->seat_code,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
