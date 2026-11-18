<?php

namespace Database\Seeders;

use App\Enums\MemberRole;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

// Tiga akun ber-password untuk menguji alur antar-role tanpa agent: koordinator, ketua kelas, dan mahasiswa
// di kelas Data Science. Password-nya tertulis di dokumentasi, jadi cuma boleh jalan di lokal.
// Boleh dijalankan ulang; ketua lama di kelas itu turun jadi anggota, sama seperti penunjukan ketua di web.
class AkunUjiSeeder extends Seeder
{
    public const PASSWORD = 'kelas3d-uji';

    public const KELAS = 'data-science';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('AkunUjiSeeder cuma untuk APP_ENV=local.');
        }

        $kelas = Classroom::firstWhere('slug', self::KELAS)
            ?? throw new RuntimeException('Kelas '.self::KELAS.' belum ada. Jalankan ClassroomSeeder dulu.');

        $akun = fn (string $name, string $email, UserRole $role, string $badan) => User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => self::PASSWORD,
            'role' => $role,
            'avatar' => [
                'tipe_badan' => $badan,
                'rambut' => 'rambut_1',
                'warna' => ['kulit' => '#e0ac69', 'rambut' => '#2b1b17', 'celana' => '#1f2937'],
            ],
        ]);

        $akun('Koordinator Uji', 'koordinator@uji.test', UserRole::Koordinator, 'laki');
        $ketua = $akun('Ketua Uji', 'ketua@uji.test', UserRole::Peserta, 'perempuan');
        $mahasiswa = $akun('Mahasiswa Uji', 'mahasiswa@uji.test', UserRole::Peserta, 'laki');

        $kelas->members()->where('role', MemberRole::Admin)->where('user_id', '!=', $ketua->id)->update(['role' => MemberRole::Anggota]);

        foreach ([[$ketua, MemberRole::Admin], [$mahasiswa, MemberRole::Anggota]] as [$user, $role]) {
            $member = $kelas->members()->firstOrNew(['user_id' => $user->id]);
            $member->role = $role;
            $member->seat_code ??= collect($kelas->seatCodes())->diff($kelas->members()->pluck('seat_code'))->first();
            $member->save();
        }
    }
}
