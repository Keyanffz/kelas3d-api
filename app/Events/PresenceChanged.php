<?php

namespace App\Events;

use App\Enums\PresenceState;
use App\Models\Classroom;
use App\Models\Presence;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Keadaan satu peserta berubah. Detail project dan file tidak ikut; staf mengambilnya lewat REST.
class PresenceChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** @var array<string, mixed> */
    public array $data;

    public function __construct(Presence $presence, public Classroom $classroom)
    {
        // Peserta yang pindah ke kelas lain atau keluar jaringan terlihat keluar dari kelas ini.
        $here = $presence->classroom_id === $classroom->id;

        $this->data = [
            'user_id' => $presence->user_id,
            'state' => $here ? $presence->state->value : PresenceState::Keluar->value,
            'state_reason' => $here ? $presence->state_reason?->value : null,
            'activity' => $here ? $presence->activity?->value : null,
            'app_label' => $here ? $presence->app_label : null,
            'state_since' => $presence->state_since?->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("classroom.{$this->classroom->slug}")];
    }

    public function broadcastAs(): string
    {
        return 'PresenceChanged';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
