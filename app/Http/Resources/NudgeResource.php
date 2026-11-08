<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NudgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classroom' => $this->classroom->slug,
            'from' => ['id' => $this->fromUser->id, 'name' => $this->fromUser->name],
            'to' => ['id' => $this->toUser->id, 'name' => $this->toUser->name],
            'about' => ['id' => $this->aboutUser->id, 'name' => $this->aboutUser->name],
            'message' => $this->message,
            'status' => $this->status,
            'read_at' => $this->read_at,
            'handled_at' => $this->handled_at,
            'created_at' => $this->created_at,
        ];
    }
}
