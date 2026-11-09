<?php

namespace App\Http\Controllers;

use App\Enums\DeviceStatus;
use App\Enums\PresenceState;
use App\Enums\StateReason;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Classroom;
use App\Models\Device;
use App\Support\PresenceRecorder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceController extends Controller
{
    /**
     * Laptop anggota kelas.
     *
     * Koordinator dan ketua kelas itu. Terbaru dulu.
     */
    public function index(Classroom $classroom): AnonymousResourceCollection
    {
        $devices = Device::with('user')
            ->whereIn('user_id', $classroom->members()->select('user_id'))
            ->latest()
            ->get();

        return DeviceResource::collection($devices);
    }

    /**
     * Revoke laptop.
     *
     * Koordinator, atau ketua kelas dari kelas pemilik laptop. Token agent langsung mati. Kalau pemiliknya tidak
     * punya laptop aktif lain, keadaannya langsung keluar (alasan terputus). Untuk memasang lagi, buat kode install baru.
     */
    public function update(UpdateDeviceRequest $request, Device $device, PresenceRecorder $recorder): DeviceResource
    {
        $device->update(['status' => $request->enum('status', DeviceStatus::class)]);
        $device->tokens()->delete();

        // Laporan terakhir laptop ini tidak akan diganti lagi, jadi jangan dibiarkan tampil seolah masih jalan.
        $presence = $device->user->presence;
        if ($presence && $presence->state !== PresenceState::Keluar
            && ! $device->user->devices()->where('status', DeviceStatus::Approved)->exists()) {
            $recorder->apply($presence, PresenceState::Keluar, StateReason::Terputus, null, [], now(), true);
        }

        return new DeviceResource($device->load('user'));
    }
}
