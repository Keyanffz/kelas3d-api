<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    public function view(User $user, Classroom $classroom): bool
    {
        return $user->isKoordinator() || $user->membershipIn($classroom) !== null;
    }

    public function create(User $user): bool
    {
        return $user->isKoordinator();
    }

    // Juga dipakai untuk jadwal dan penunjukan ketua kelas.
    public function update(User $user, Classroom $classroom): bool
    {
        return $user->isKoordinator();
    }

    // Angkat tangan: anggota kelas itu, termasuk ketua.
    public function requestHelp(User $user, Classroom $classroom): bool
    {
        return $user->membershipIn($classroom) !== null;
    }

    // Anggota dan kursi.
    public function manageMembers(User $user, Classroom $classroom): bool
    {
        return $user->isKoordinator() || $user->isAdminOf($classroom);
    }
}
