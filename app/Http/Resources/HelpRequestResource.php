<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HelpRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classroom' => $this->classroom->slug,
            'user' => ['id' => $this->user->id, 'name' => $this->user->name],
            'note' => $this->note,
            'status' => $this->status,
            'handled_by' => $this->handler ? ['id' => $this->handler->id, 'name' => $this->handler->name] : null,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }
}
