<?php

namespace App\Http\Controllers;

use App\Enums\HelpStatus;
use App\Events\HelpRequested;
use App\Http\Requests\StoreHelpRequest;
use App\Http\Requests\UpdateHelpRequest;
use App\Http\Resources\HelpRequestResource;
use App\Models\Classroom;
use App\Models\HelpRequest;
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

        return new HelpRequestResource($helpRequest);
    }
}
