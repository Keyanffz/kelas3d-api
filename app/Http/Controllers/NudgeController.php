<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Enums\NudgeStatus;
use App\Events\NudgeSent;
use App\Http\Requests\StoreNudgeRequest;
use App\Http\Requests\UpdateNudgeRequest;
use App\Http\Resources\NudgeResource;
use App\Models\Classroom;
use App\Models\Nudge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NudgeController extends Controller
{
    /**
     * Teguran yang belum selesai.
     *
     * Untuk ketua kelas: teguran yang ditujukan kepadanya. Untuk koordinator: teguran yang ia kirim. Terbaru dulu.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $nudges = Nudge::with(['classroom', 'fromUser', 'toUser', 'aboutUser'])
            ->where($user->isKoordinator() ? 'from_user_id' : 'to_user_id', $user->id)
            ->where('status', '!=', NudgeStatus::Selesai)
            ->latest('id')
            ->get();

        return NudgeResource::collection($nudges);
    }

    /**
     * Minta ketua kelas menegur peserta.
     *
     * Khusus koordinator. Teguran diteruskan ke ketua kelas itu, lewat event `NudgeSent` di channel
     * `classroom.{slug}.staff` dan lewat balasan heartbeat di laptop ketua kelas.
     */
    public function store(StoreNudgeRequest $request): NudgeResource
    {
        $classroom = Classroom::firstWhere('slug', $request->validated('classroom'));

        $nudge = $classroom->nudges()->create([
            'from_user_id' => $request->user()->id,
            'to_user_id' => $classroom->members()->where('role', MemberRole::Admin)->value('user_id'),
            'about_user_id' => $request->validated('about_user_id'),
            'message' => $request->validated('message'),
            'status' => NudgeStatus::Terkirim,
        ]);

        NudgeSent::dispatch($nudge);

        /** @status 201 */
        return new NudgeResource($nudge);
    }

    /**
     * Tandai teguran dibaca atau selesai.
     *
     * Khusus ketua kelas penerima teguran. Staf kelas menerima event `NudgeSent` dengan status baru.
     */
    public function update(UpdateNudgeRequest $request, Nudge $nudge): NudgeResource
    {
        $status = $request->enum('status', NudgeStatus::class);

        $nudge->update([
            'status' => $status,
            'read_at' => $nudge->read_at ?? now(),
            'handled_at' => $status === NudgeStatus::Selesai ? now() : null,
        ]);

        NudgeSent::dispatch($nudge);

        return new NudgeResource($nudge);
    }
}
