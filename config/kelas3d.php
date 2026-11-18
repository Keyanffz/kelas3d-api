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

    // Lihat layar: permintaan yang tidak direspons kedaluwarsa, dan sesi live ditutup sesudah batas ini.
    // ICE_SERVERS berisi JSON daftar server STUN dan TURN untuk WebRTC di browser.
    'screen' => [
        'request_ttl' => 60,
        'max_duration' => 30 * 60,
        'ice_servers' => json_decode(env('ICE_SERVERS', '[{"urls":"stun:stun.l.google.com:19302"}]'), true),
    ],

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

    // XP = menit ngoding (ngoding, vibe_coding, ngolah_data) + bonus per hari streak terbaik, jadi tidak pernah turun.
    // Level L butuh 50 × L × (L − 1) XP: level 2 di 100, level 3 di 300, level 4 di 600, level 5 di 1000.
    'xp' => [
        'per_menit_ngoding' => 1,
        'per_hari_streak' => 20,
        'kelipatan_level' => 50,
    ],

    // Aksesoris yang terbuka per level, satu per slot. Id-nya jadi nama objek di avatar.glb dan gedung.glb
    // nanti; sampai asetnya dibuat di Blender, web menampilkan balok sementara.
    'aksesoris' => [
        ['id' => 'topi_1', 'slot' => 'topi', 'nama' => 'Topi pet', 'level' => 2],
        ['id' => 'meja_1', 'slot' => 'meja', 'nama' => 'Mug', 'level' => 3],
        ['id' => 'topi_2', 'slot' => 'topi', 'nama' => 'Kupluk', 'level' => 4],
        ['id' => 'meja_2', 'slot' => 'meja', 'nama' => 'Tanaman kecil', 'level' => 5],
    ],

    // Akun koordinator pertama untuk DatabaseSeeder.
    'koordinator' => [
        'email' => env('SEED_KOORDINATOR_EMAIL'),
        'password' => env('SEED_KOORDINATOR_PASSWORD'),
    ],

];
