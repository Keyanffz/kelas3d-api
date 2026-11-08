<?php

namespace App\Events;

use App\Models\HelpRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Peserta angkat tangan, dan juga tiap status permintaannya berubah.
class HelpRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** @var array<string, mixed> */
    public array $data;

    public string $classroom;

    public function __construct(HelpRequest $help)
    {
        $this->classroom = $help->classroom->slug;
        $this->data = [
            'id' => $help->id,
            'user' => ['id' => $help->user->id, 'name' => $help->user->name],
            'note' => $help->note,
            'status' => $help->status->value,
            'handled_by' => $help->handler ? ['id' => $help->handler->id, 'name' => $help->handler->name] : null,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("classroom.{$this->classroom}")];
    }

    public function broadcastAs(): string
    {
        return 'HelpRequested';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
