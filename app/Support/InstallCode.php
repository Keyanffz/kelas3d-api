<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

// Kode pribadi untuk menghubungkan laptop ke akun peserta tanpa password, contoh BCDF-GHJK.
class InstallCode
{
    // Huruf mati saja, tanpa yang mirip angka.
    private const LETTERS = 'BCDFGHJKLMNPQRSTVWXZ';

    // Kode lama milik user yang sama otomatis tidak berlaku.
    public static function issue(User $user, User $issuer): string
    {
        $ttl = config('kelas3d.install_code_ttl');

        if ($old = Cache::pull("install_code_user:{$user->id}")) {
            Cache::forget("install_code:$old");
        }

        do {
            $code = collect(range(1, 8))
                ->map(fn () => self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)])
                ->implode('');
        } while (! Cache::add("install_code:$code", ['user_id' => $user->id, 'issued_by' => $issuer->id], $ttl));

        Cache::put("install_code_user:{$user->id}", $code, $ttl);

        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    /**
     * Kode cuma bisa dipakai sekali. Tanda hubung, spasi, dan huruf kecil diabaikan.
     *
     * @return array{user_id: int, issued_by: int}|null
     */
    public static function redeem(string $code): ?array
    {
        $data = Cache::pull('install_code:'.preg_replace('/[^A-Z]/', '', strtoupper($code)));

        if ($data) {
            Cache::forget("install_code_user:{$data['user_id']}");
        }

        return $data;
    }
}
