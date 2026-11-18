<?php

namespace App\Enums;

enum ScreenCloseReason: string
{
    case DitolakKetua = 'ditolak_ketua';
    case DitolakMahasiswa = 'ditolak_mahasiswa';
    case IzinBrowser = 'izin_browser';
    case Dihentikan = 'dihentikan';
    case Terputus = 'terputus';
    case BatasWaktu = 'batas_waktu';
}
