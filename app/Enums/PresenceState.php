<?php

namespace App\Enums;

enum PresenceState: string
{
    case DiKelas = 'di_kelas';
    case Istirahat = 'istirahat';
    case Keluar = 'keluar';
}
