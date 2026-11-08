<?php

// Data yang harus cocok dengan aset 3D di kelas3d-web, plus angka yang bisa disetel.
return [

    // Semua dalam detik, lihat bagian Keadaan avatar di rencana.
    // Scheduler cek heartbeat jalan tiap 30 detik, diatur di routes/console.php.
    'heartbeat_interval' => 10,
    'idle_limit' => 5 * 60,           // tanpa input keyboard dan mouse → istirahat
    'rest_after_silence' => 2 * 60,   // tanpa heartbeat → istirahat
    'out_after_silence' => 30 * 60,   // tanpa heartbeat → keluar

    // Kode install pribadi dari ketua kelas berlaku sekali selama 1 hari.
    // Link masuk web dari agent berlaku sekali selama 2 menit.
    'install_code_ttl' => 24 * 60 * 60,
    'web_link_ttl' => 2 * 60,

    // Alamat script installer agent dan halaman web yang menerima link masuk.
    'install_url' => env('INSTALL_URL', env('APP_URL', 'http://localhost:8000')),
    'web_link_url' => env('FRONTEND_URL', 'http://localhost:3000').'/masuk',

    // Kode kursi per slug kelas. Titiknya di gedung.glb bernama
    // seat_<slug dengan "-" diganti "_">_<kode>, contoh seat_data_science_a1.
    'seats' => [
        'developer' => [
            'a1', 'a2', 'a3', 'a4', 'a5', 'a6',
            'b1', 'b2', 'b3', 'b4',
            'c1', 'c2', 'c3', 'c4', 'c5', 'c6',
        ],
        'data-science' => [
            'a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8',
            'b1', 'b2', 'b3', 'b4', 'b5', 'b6', 'b7', 'b8',
        ],
    ],

    // Pilihan tampilan avatar, sesuai objek di avatar-laki.glb dan avatar-perempuan.glb.
    'avatar' => [
        'tipe_badan' => ['laki', 'perempuan'],
        'rambut' => ['rambut_1', 'rambut_2', 'rambut_3', 'rambut_4', 'rambut_5'],
    ],

    // Akun koordinator pertama untuk DatabaseSeeder.
    'koordinator' => [
        'email' => env('SEED_KOORDINATOR_EMAIL'),
        'password' => env('SEED_KOORDINATOR_PASSWORD'),
    ],

];
