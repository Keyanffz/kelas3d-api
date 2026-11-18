<?php

namespace App\Enums;

enum ScreenStatus: string
{
    case MenungguKetua = 'menunggu_ketua';
    case MenungguMahasiswa = 'menunggu_mahasiswa';
    case Menyambung = 'menyambung';
    case Live = 'live';
    case Ditolak = 'ditolak';
    case Kedaluwarsa = 'kedaluwarsa';
    case Selesai = 'selesai';

    /**
     * Status yang masih berjalan; mahasiswa cuma boleh punya satu sesi seperti ini.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::MenungguKetua, self::MenungguMahasiswa, self::Menyambung, self::Live];
    }
}
