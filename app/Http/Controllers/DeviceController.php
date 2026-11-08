<?php

namespace App\Http\Controllers;

use App\Enums\DeviceStatus;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Classroom;
use App\Models\Device;
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
     * Koordinator, atau ketua kelas dari kelas pemilik laptop. Token agent langsung mati. Untuk memasang lagi,
     * buat kode install baru.
     */
    public function update(UpdateDeviceRequest $request, Device $device): DeviceResource
    {
        $device->update(['status' => $request->enum('status', DeviceStatus::class)]);
        $device->tokens()->delete();

        return new DeviceResource($device->load('user'));
    }
}
