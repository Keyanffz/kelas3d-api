<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassroomController extends Controller
{
    /**
     * Daftar kelas.
     *
     * Koordinator mendapat semua kelas, peserta cuma kelas yang ia ikuti.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Classroom::orderBy('name');

        if (! $user->isKoordinator()) {
            $query->whereHas('members', fn ($members) => $members->where('user_id', $user->id));
        }

        return ClassroomResource::collection($query->get());
    }

    /**
     * Buat kelas.
     *
     * Khusus koordinator.
     */
    public function store(ClassroomRequest $request): ClassroomResource
    {
        /** @status 201 */
        return new ClassroomResource(Classroom::create($request->validated()));
    }

    /**
     * Detail kelas.
     *
     * Koordinator dan anggota kelas itu.
     */
    public function show(Classroom $classroom): ClassroomResource
    {
        return new ClassroomResource($classroom);
    }

    /**
     * Ubah kelas.
     *
     * Khusus koordinator. Semua field dikirim lengkap.
     */
    public function update(ClassroomRequest $request, Classroom $classroom): ClassroomResource
    {
        $classroom->update($request->validated());

        return new ClassroomResource($classroom);
    }
}
