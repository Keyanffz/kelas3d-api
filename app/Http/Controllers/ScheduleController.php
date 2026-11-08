<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ScheduleController extends Controller
{
    /**
     * Jadwal kelas.
     *
     * Koordinator dan anggota kelas itu. Urut hari lalu jam mulai. Jadwal istirahat berada di dalam rentang jadwal kelas.
     */
    public function index(Classroom $classroom): AnonymousResourceCollection
    {
        return ScheduleResource::collection(
            $classroom->schedules()->orderBy('day_of_week')->orderBy('starts_at')->get()
        );
    }

    /**
     * Tambah jadwal.
     *
     * Khusus koordinator. `day_of_week` 1 = Senin sampai 7 = Minggu.
     */
    public function store(ScheduleRequest $request, Classroom $classroom): ScheduleResource
    {
        /** @status 201 */
        return new ScheduleResource($classroom->schedules()->create($request->validated()));
    }

    /**
     * Ubah jadwal.
     *
     * Khusus koordinator. Semua field dikirim lengkap.
     */
    public function update(ScheduleRequest $request, Classroom $classroom, ClassSchedule $schedule): ScheduleResource
    {
        $schedule->update($request->validated());

        return new ScheduleResource($schedule);
    }

    /**
     * Hapus jadwal.
     *
     * Khusus koordinator.
     */
    public function destroy(Classroom $classroom, ClassSchedule $schedule): Response
    {
        $schedule->delete();

        return response()->noContent();
    }
}
