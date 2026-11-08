<?php

namespace App\Events;

use App\Models\Nudge;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Teguran lewat ketua kelas, dan juga tiap statusnya berubah. Cuma di channel staf.
class NudgeSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** @var array<string, mixed> */
    public array $data;

    public string $classroom;

    public function __construct(Nudge $nudge)
    {
        $person = fn ($user) => ['id' => $user->id, 'name' => $user->name];

        $this->classroom = $nudge->classroom->slug;
        $this->data = [
            'id' => $nudge->id,
            'from' => $person($nudge->fromUser),
            'to' => $person($nudge->toUser),
            'about' => $person($nudge->aboutUser),
            'message' => $nudge->message,
            'status' => $nudge->status->value,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("classroom.{$this->classroom}.staff")];
    }

    public function broadcastAs(): string
    {
        return 'NudgeSent';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
