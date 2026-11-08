<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            // Nama pengirim; sama dengan `from` di event AnnouncementSent.
            'from' => $this->user->name,
            // null = untuk semua kelas
            'classroom' => $this->classroom?->slug,
            'for_all' => $this->classroom_id === null,
            'created_at' => $this->created_at,
        ];
    }
}
