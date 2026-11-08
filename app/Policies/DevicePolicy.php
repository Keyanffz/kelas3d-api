<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    // Approve atau revoke: koordinator, atau ketua kelas dari kelas pemilik laptop.
    public function update(User $user, Device $device): bool
    {
        return $user->isKoordinator() || $user->isAdminOver($device->user);
    }
}
