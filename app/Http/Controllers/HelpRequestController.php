<?php

namespace App\Http\Controllers;

use App\Enums\HelpStatus;
use App\Enums\ScreenCloseReason;
use App\Enums\ScreenStatus;
use App\Events\HelpRequested;
use App\Events\ScreenSessionChanged;
use App\Http\Requests\StoreHelpRequest;
use App\Http\Requests\UpdateHelpRequest;
use App\Http\Resources\HelpRequestResource;
use App\Models\Classroom;
use App\Models\HelpRequest;
use App\Models\ScreenSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HelpRequestController extends Controller
{
    /**
     * Angkat tangan.
     *
     * Anggota kelas itu. Satu peserta cuma boleh punya satu permintaan yang belum selesai per kelas.
     * Kelas menerima event `HelpRequested`.
     */
    public function store(StoreHelpRequest $request): HelpRequestResource
    {
        $classroom = Classroom::firstWhere('slug', $request->validated('classroom'));
        Gate::authorize('requestHelp', $classroom);

        $open = $classroom->helpRequests()
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', HelpStatus::Selesai)
            ->exists();

        if ($open) {
            throw ValidationException::withMessages(['classroom' => 'Masih ada permintaan bantuan yang belum selesai.']);
        }

        $help = $classroom->helpRequests()->create([
            'user_id' => $request->user()->id,
            'note' => $request->validated('note'),
            'status' => HelpStatus::Menunggu,
        ]);

        HelpRequested::dispatch($help);

        /** @status 201 */
        return new HelpRequestResource($help);
    }

    /**
     * Tangani atau selesaikan permintaan bantuan.
     *
     * Koordinator dan ketua kelas boleh mengisi `ditangani` atau `selesai`. Peserta yang angkat tangan
     * cuma boleh `selesai` (menurunkan tangan). Kelas menerima event `HelpRequested` dengan status baru.
     * Share layar sukarela dari permintaan ini dihentikan saat `selesai`.
     */
    public function update(UpdateHelpRequest $request, HelpRequest $helpRequest): HelpRequestResource
    {
        $status = $request->enum('status', HelpStatus::class);
        $isStaff = $request->user()->can('manageMembers', $helpRequest->classroom);

        $helpRequest->update([
            'status' => $status,
            'handled_by' => $isStaff ? $request->user()->id : $helpRequest->handled_by,
            'resolved_at' => $status === HelpStatus::Selesai ? now() : null,
        ]);

        HelpRequested::dispatch($helpRequest->refresh());

        // Bantuan selesai, share layar sukarelanya ikut berhenti.
        if ($status === HelpStatus::Selesai) {
            ScreenSession::whereBelongsTo($helpRequest)->whereIn('status', ScreenStatus::active())->get()
                ->each(function (ScreenSession $session) use ($request) {
                    $session->update([
                        'status' => ScreenStatus::Selesai,
                        'close_reason' => ScreenCloseReason::Dihentikan,
                        'closed_by' => $request->user()->id,
                        'ended_at' => now(),
                    ]);
                    ScreenSessionChanged::dispatch($session);
                });
        }

        return new HelpRequestResource($helpRequest);
    }
}
