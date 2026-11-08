<?php

namespace App\Enums;

enum StateReason: string
{
    case Idle = 'idle';
    case Terputus = 'terputus';
    case JadwalIstirahat = 'jadwal_istirahat';
}
