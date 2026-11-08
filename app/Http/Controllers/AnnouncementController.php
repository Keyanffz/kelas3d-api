<?php

namespace App\Http\Controllers;

use App\Events\AnnouncementSent;
use App\Http\Requests\AnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Classroom;

class AnnouncementController extends Controller
{
    /**
     * Kirim pengumuman.
     *
     * Khusus koordinator. `classroom` kosong berarti untuk semua kelas. Pengumuman muncul lewat event
     * `AnnouncementSent` di web dan lewat balasan heartbeat di agent.
     */
    public function store(AnnouncementRequest $request): AnnouncementResource
    {
        $classroom = Classroom::firstWhere('slug', $request->validated('classroom'));

        $announcement = Announcement::create([
            'classroom_id' => $classroom?->id,
            'user_id' => $request->user()->id,
            'message' => $request->validated('message'),
        ]);

        $targets = $classroom ? [$classroom->slug] : Classroom::pluck('slug')->all();
        AnnouncementSent::dispatch($announcement, $targets);

        /** @status 201 */
        return new AnnouncementResource($announcement);
    }
}
