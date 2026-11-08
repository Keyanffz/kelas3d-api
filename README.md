# kelas3d-api

Backend Kelas 3D Bengkel Koding: auth web, login device untuk agent, logika keadaan avatar, dan broadcast realtime. Dibangun dengan Laravel 13, MariaDB, Sanctum, Reverb, dan Pest.

Daftar endpoint, pemetaan kode kursi, dan catatan perubahan ada di [dokumentasi.md](dokumentasi.md). Dokumentasi OpenAPI dari Scramble bisa dibuka di `http://localhost:8000/docs/api` saat `APP_ENV=local`.

## Menjalankan

Isi dulu `DB_USERNAME`, `DB_PASSWORD`, `SEED_KOORDINATOR_EMAIL`, dan `SEED_KOORDINATOR_PASSWORD` di `.env`, lalu siapkan database `kelas3d` dan `kelas3d_test`.

```bash
composer install
php artisan migrate:fresh --seed
composer dev
```

`composer dev` menyalakan server API, antrean broadcast, Reverb, dan scheduler sekaligus.

## Tes

```bash
php artisan test
```

Tes memakai database `kelas3d_test` di MariaDB, bukan SQLite (lihat `phpunit.xml`).
