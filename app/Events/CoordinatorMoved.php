<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Koordinator pindah ruangan. null = ruang koordinator. Dikirim ke kelas asal dan kelas tujuan.
class CoordinatorMoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** @var array<string, mixed> */
    public array $data;

    public function __construct(User $coordinator, public ?string $from, public ?string $to)
    {
        $this->data = [
            'coordinator' => ['id' => $coordinator->id, 'name' => $coordinator->name, 'avatar' => $coordinator->avatar],
            'from' => $from,
            'to' => $to,
        ];
    }

    public function broadcastOn(): array
    {
        return collect([$this->from, $this->to])
            ->filter()
            ->map(fn (string $slug) => new PrivateChannel("classroom.$slug"))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'CoordinatorMoved';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
