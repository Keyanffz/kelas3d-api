<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'match_on' => $this->match_on,
            'pattern' => $this->pattern,
            'os' => $this->os,
            'activity' => $this->activity,
            'label' => $this->label,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
        ];
    }
}
