<?php

namespace App\Http\Resources;

use App\Models\ScreenSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScreenSession */
class ScreenSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = fn (?User $user) => $user ? ['id' => $user->id, 'name' => $user->name] : null;

        return [
            'id' => $this->id,
            'classroom' => $this->classroom->slug,
            'viewer' => $person($this->viewer),
            'target' => $person($this->target),
            'help_request_id' => $this->help_request_id,
            'approver' => $person($this->approver),
            'reason' => $this->reason,
            'status' => $this->status,
            'close_reason' => $this->close_reason,
            'closed_by' => $person($this->closer),
            'expires_at' => $this->expires_at,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'created_at' => $this->created_at,
        ];
    }
}
