<?php

namespace App\Policies;

use App\Models\ScreenSession;
use App\Models\User;

class ScreenSessionPolicy
{
    // Pihak yang terlibat: koordinator peminta, ketua kelas itu, dan mahasiswa sesudah ketua setuju.
    // Share sukarela cuma urusan dua ujungnya.
    public function update(User $user, ScreenSession $session): bool
    {
        return $user->id === $session->viewer_id
            || ($session->help_request_id === null && $user->isAdminOf($session->classroom))
            || ($user->id === $session->target_id && $session->approved_at !== null);
    }

    // Channel signaling WebRTC: cuma dua ujung koneksi, selama sesi sedang menyambung atau live.
    public function signal(User $user, ScreenSession $session): bool
    {
        return in_array($user->id, [$session->viewer_id, $session->target_id], true)
            && $session->consented_at !== null
            && $session->isActive();
    }
}
