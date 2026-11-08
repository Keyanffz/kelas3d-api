<?php

namespace App\Http\Controllers;

use App\Events\AnnouncementSent;
use App\Http\Requests\AnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Classroom;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    /**
     * Pengumuman hari ini.
     *
     * Koordinator dan anggota kelas itu. Berisi pengumuman untuk kelas ini dan untuk semua kelas, terbaru dulu,
     * paling banyak 20. Dipakai web yang baru dibuka; pengumuman berikutnya datang lewat `AnnouncementSent`.
     */
    public function index(Classroom $classroom): AnonymousResourceCollection
    {
        return AnnouncementResource::collection(
            Announcement::with(['user', 'classroom'])
                ->where(fn ($query) => $query->whereNull('classroom_id')->orWhere('classroom_id', $classroom->id))
                ->where('created_at', '>=', now()->startOfDay())
                ->latest('id')
                ->limit(20)
                ->get()
        );
    }

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
