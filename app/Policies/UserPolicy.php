<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    // Avatar dipilih sendiri, tapi koordinator boleh mengubah punya siapa saja.
    public function updateAvatar(User $user, User $target): bool
    {
        return $user->is($target) || $user->isKoordinator();
    }

    // Rekap aktivitas: pemiliknya, koordinator, atau ketua kelas dari kelas user itu.
    public function viewActivity(User $user, User $target): bool
    {
        return $user->is($target) || $user->isKoordinator() || $user->isAdminOver($target);
    }
}
