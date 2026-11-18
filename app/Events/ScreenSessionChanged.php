<?php

namespace App\Events;

use App\Http\Resources\ScreenSessionResource;
use App\Models\ScreenSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

// Permintaan lihat layar dibuat, dan juga tiap statusnya berubah. Dikirim ke channel pribadi pihak yang
// terlibat saja, dan tanpa antrean supaya prompt izin tidak terlambat.
class ScreenSessionChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @var array<string, mixed> */
    public array $data;

    /** @var list<int> */
    public array $audience;

    public function __construct(ScreenSession $session)
    {
        $this->data = (new ScreenSessionResource($session))->resolve();
        $this->audience = $session->audience();
    }

    public function broadcastOn(): array
    {
        return array_map(fn (int $id) => new PrivateChannel("user.{$id}"), $this->audience);
    }

    public function broadcastAs(): string
    {
        return 'ScreenSessionChanged';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
