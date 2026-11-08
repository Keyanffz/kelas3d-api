<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Pengumuman koordinator, dikirim ke satu kelas atau ke semua kelas.
class AnnouncementSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** @var array<string, mixed> */
    public array $data;

    /**
     * @param  list<string>  $classrooms  slug kelas tujuan
     */
    public function __construct(Announcement $announcement, public array $classrooms)
    {
        $this->data = [
            'id' => $announcement->id,
            'message' => $announcement->message,
            'from' => $announcement->user->name,
            'for_all' => $announcement->classroom_id === null,
            'created_at' => $announcement->created_at->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return array_map(fn (string $slug) => new PrivateChannel("classroom.$slug"), $this->classrooms);
    }

    public function broadcastAs(): string
    {
        return 'AnnouncementSent';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
