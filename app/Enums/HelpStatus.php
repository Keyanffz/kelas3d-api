<?php

namespace App\Enums;

enum HelpStatus: string
{
    case Menunggu = 'menunggu';
    case Ditangani = 'ditangani';
    case Selesai = 'selesai';
}
