<?php

use App\Models\Classroom;
use App\Models\ScreenSession;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Semua yang boleh melihat ruangan: koordinator dan anggota kelas.
Broadcast::channel('classroom.{classroom}', fn (User $user, Classroom $classroom) => $user->can('view', $classroom));

// Adegan teguran: cuma koordinator dan ketua kelas itu.
Broadcast::channel('classroom.{classroom}.staff', fn (User $user, Classroom $classroom) => $user->can('manageMembers', $classroom));

// Channel pribadi: permintaan lihat layar dan jawabannya. Dipakai juga untuk tahu web siapa yang terbuka.
Broadcast::channel('user.{id}', fn (User $user, int $id) => $user->id === $id);

// Signaling WebRTC lewat whisper, cuma antara koordinator peminta dan mahasiswanya. Presence channel,
// supaya tiap ujung tahu kalau ujung satunya menghilang.
Broadcast::channel('screen.{session}', fn (User $user, ScreenSession $session) => $user->can('signal', $session)
    ? ['id' => $user->id, 'name' => $user->name]
    : false);
