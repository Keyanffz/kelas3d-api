<?php

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Semua yang boleh melihat ruangan: koordinator dan anggota kelas.
Broadcast::channel('classroom.{classroom}', fn (User $user, Classroom $classroom) => $user->can('view', $classroom));

// Adegan teguran: cuma koordinator dan ketua kelas itu.
Broadcast::channel('classroom.{classroom}.staff', fn (User $user, Classroom $classroom) => $user->can('manageMembers', $classroom));
