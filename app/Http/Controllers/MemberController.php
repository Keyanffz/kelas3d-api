<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Enums\UserRole;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\User;
use App\Support\InstallCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    /**
     * Daftar anggota kelas.
     *
     * Koordinator dan ketua kelas itu. Urut kode kursi; yang belum punya kursi ada di awal.
     */
    public function index(Classroom $classroom): AnonymousResourceCollection
    {
        return MemberResource::collection($classroom->members()->with('user')->orderBy('seat_code')->get());
    }

    /**
     * Tambah anggota.
     *
     * Koordinator dan ketua kelas itu. Kalau email belum terdaftar, akun peserta dibuat sekalian dengan `name`;
     * `password` opsional karena peserta masuk lewat kode install. Kalau sudah terdaftar, akunnya cuma
     * ditambahkan; nama dan password-nya tidak disentuh.
     */
    public function store(StoreMemberRequest $request, Classroom $classroom): MemberResource
    {
        $member = DB::transaction(function () use ($request, $classroom) {
            $user = User::firstOrCreate(['email' => $request->validated('email')], [
                'name' => $request->validated('name'),
                'password' => $request->validated('password'),
                'role' => UserRole::Peserta,
            ]);

            return $classroom->members()->create([
                'user_id' => $user->id,
                'role' => MemberRole::Anggota,
                'seat_code' => $request->validated('seat_code'),
            ]);
        });

        /** @status 201 */
        return new MemberResource($member->load('user'));
    }

    /**
     * Ubah kursi atau role anggota.
     *
     * Koordinator dan ketua kelas itu, tapi mengubah `role` cuma koordinator.
     * Menjadikan anggota `admin` otomatis menurunkan ketua lama jadi anggota.
     */
    public function update(UpdateMemberRequest $request, Classroom $classroom, ClassroomMember $member): MemberResource
    {
        DB::transaction(function () use ($request, $classroom, $member) {
            // Satu kelas satu ketua: ketua lama turun jadi anggota.
            if ($request->enum('role', MemberRole::class) === MemberRole::Admin) {
                $classroom->members()
                    ->where('role', MemberRole::Admin)
                    ->whereKeyNot($member->id)
                    ->update(['role' => MemberRole::Anggota]);
            }

            $member->update($request->validated());
        });

        return new MemberResource($member->load('user'));
    }

    /**
     * Buat perintah install agent untuk anggota.
     *
     * Koordinator dan ketua kelas itu. Kodenya pribadi, berlaku sekali selama 1 hari; membuat kode baru
     * membatalkan kode lama anggota yang sama. Perintahnya tinggal di-paste anggota itu di terminal.
     */
    public function installCode(Request $request, Classroom $classroom, ClassroomMember $member): JsonResponse
    {
        $code = InstallCode::issue($member->user, $request->user());
        $base = rtrim(config('kelas3d.install_url'), '/');

        return response()->json(['data' => [
            'code' => $code,
            'expires_at' => now()->addSeconds(config('kelas3d.install_code_ttl'))->toIso8601String(),
            'commands' => [
                'windows' => "irm $base/install.ps1 | iex; kelas3d login $code",
                'macos_linux' => "curl -fsSL $base/install.sh | sh && kelas3d login $code",
            ],
        ]], 201);
    }

    /**
     * Keluarkan anggota.
     *
     * Koordinator dan ketua kelas itu. Akun user tetap ada.
     */
    public function destroy(Classroom $classroom, ClassroomMember $member): Response
    {
        $member->delete();

        return response()->noContent();
    }
}
