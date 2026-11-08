# Dokumentasi kelas3d-api

Acuan untuk `kelas3d-web` dan `kelas3d-agent`: cara memanggil API, daftar endpoint, dan pemetaan data ke aset 3D. Catatan file baru dan file yang diubah ada di bagian Riwayat perubahan, entri terbaru di paling bawah.

## Cara memanggil API

- Base URL lokal `http://localhost:8000/api`. Kirim header `Accept: application/json`; semua respons berupa JSON.
- **Web** login lewat Sanctum mode SPA (cookie dan CSRF) dari `http://localhost:3000`:
  1. `GET http://localhost:8000/sanctum/csrf-cookie` dengan `credentials: 'include'`.
  2. Request berikutnya pakai `credentials: 'include'` dan header `X-XSRF-TOKEN` berisi isi cookie `XSRF-TOKEN` (di-`decodeURIComponent` dulu). Axios mengerjakan ini sendiri kalau `withCredentials` dan `withXSRFToken` diaktifkan.
  3. Sesi cuma dibuat untuk request yang header `Origin` atau `Referer`-nya ada di `SANCTUM_STATEFUL_DOMAINS` (default `localhost:3000`). Kalau tes manual pakai curl atau Postman, kirim `Referer: http://localhost:3000`.
- **Agent** memakai header `Authorization: Bearer <token>` dari `POST /api/agent/login` (lihat bagian Laptop). Token device tidak berlaku di endpoint web, dan sesi web tidak berlaku di endpoint agent. Respons endpoint agent tidak dibungkus `data`.
- Satu data dibungkus `{"data": {...}}`, daftar dibungkus `{"data": [...]}`.
- Semua jam memakai zona `Asia/Jakarta`.
- Dokumentasi OpenAPI dari Scramble: `http://localhost:8000/docs/api` (tampilan, tema gelap) dan `http://localhost:8000/docs/api.json` (spesifikasi, bisa diimpor ke Postman atau dipakai generator tipe TypeScript). Cuma bisa dibuka kalau `APP_ENV=local`, dan butuh database yang sudah terisi di `.env` karena halamannya memakai sesi. Isinya dibaca langsung dari route, form request, dan resource, jadi selalu ikut kode.

| Status | Arti |
| --- | --- |
| 200, 201, 204 | Berhasil, data baru dibuat, berhasil tanpa isi |
| 400 | Login bukan dari domain frontend |
| 401 | Belum login |
| 403 | Login, tapi role-nya tidak berhak, atau laptop sudah di-revoke |
| 404 | Data tidak ada, termasuk data milik kelas lain yang diakses lewat slug kelas ini |
| 422 | Validasi gagal: `{"message": "...", "errors": {"field": ["pesan"]}}` |
| 429 | Terlalu banyak percobaan (login, login agent, link masuk) |

## Role

- `users.role`: `koordinator` atau `peserta`.
- `classroom_members.role`: `admin` (ketua kelas, disebut juga admin kelas) atau `anggota`. Satu kelas cuma punya satu ketua. Web menampilkan role `admin` sebagai "Ketua Kelas".

## Pemetaan kode kursi ke gedung.glb

`seat_code` selalu disimpan dan dikirim dalam huruf kecil. Input huruf besar (`A1`) diubah jadi `a1`. Nama titiknya di `gedung.glb`:

```
seat_ + slug kelas dengan "-" diganti "_" + _ + seat_code
```

| Slug kelas | Kode kursi yang sah | Contoh titik |
| --- | --- | --- |
| `developer` | `a1`–`a6`, `b1`–`b4`, `c1`–`c6` | `seat_developer_b3` |
| `data-science` | `a1`–`a8`, `b1`–`b8` | `seat_data_science_a1` |

Di web: `` `seat_${slug.replaceAll('-', '_')}_${seatCode}` ``. Daftar kodenya ada di `config/kelas3d.php`. Kelas dengan slug lain belum punya kursi, jadi `seat_code` anggotanya harus `null`.

## Aturan app_rules

Dicocokkan oleh agent di laptop; server cuma menerima kategori dan label hasilnya.

- `match_on: process`: nama proses aplikasi yang fokus sama persis dengan `pattern`, huruf besar-kecil diabaikan. Di Windows namanya termasuk `.exe`.
- `match_on: title`: judul window mengandung `pattern`, huruf besar-kecil diabaikan.
- `os: null` berlaku di semua OS.
- Kalau beberapa aturan cocok, `priority` terbesar menang. Aturan judul untuk AI chat (30) dan Jupyter (20) sengaja lebih tinggi dari aturan proses browser (5).
- Tidak ada yang cocok berarti `lainnya`.

## Endpoint

### Auth

#### `POST /api/login`

Siapa: tamu. Maksimal 10 percobaan per menit per IP.

```json
{ "email": "budi@example.com", "password": "rahasia123" }
```

Respons 200, bentuknya sama dengan `GET /api/me`. Gagal: 422 dengan `errors.email` "Email atau password salah.", atau 400 kalau request bukan dari domain frontend. Akun tanpa password (akun LMS nanti) tidak bisa login di sini.

#### `POST /api/login/link`

Siapa: tamu, untuk peserta dan ketua kelas yang masuk tanpa password. Maksimal 10 kali per menit. Syarat domain frontend dan CSRF-nya sama dengan `POST /api/login`.

```json
{ "token": "kode-panjang-40-karakter" }
```

`token` diambil dari URL link masuk yang dibuat agent. Respons 200, bentuknya sama dengan `GET /api/me`. Gagal: 422 `errors.token` kalau link tidak valid, sudah dipakai, atau kedaluwarsa.

#### `POST /api/logout`

Siapa: yang sudah login. Respons 204.

#### `GET /api/me`

Siapa: yang sudah login.

```json
{
  "data": {
    "id": 5,
    "name": "Budi",
    "email": "budi@example.com",
    "role": "peserta",
    "avatar": {
      "tipe_badan": "laki",
      "rambut": "rambut_1",
      "warna": { "kulit": "#c68642", "rambut": "#2b1b17", "celana": "#1f2937" }
    },
    "classrooms": [
      { "id": 1, "name": "Developer", "slug": "developer", "role": "admin", "seat_code": "a1" }
    ]
  }
}
```

`avatar` bernilai `null` kalau belum dipilih; web bisa memakai itu untuk membuka pemilih avatar di login pertama. Koordinator tidak jadi anggota kelas mana pun, jadi `classrooms`-nya kosong; daftar kelasnya diambil dari `GET /api/classrooms`.

### Avatar

#### `PATCH /api/users/{id}/avatar`

Siapa: pemilik akun lewat `/api/users/me/avatar`, atau koordinator untuk user mana pun.

```json
{
  "tipe_badan": "perempuan",
  "rambut": "rambut_2",
  "warna": { "kulit": "#c68642", "rambut": "#2b1b17", "celana": "#1f2937" }
}
```

Aturan: `tipe_badan` `laki` atau `perempuan`; `rambut` `rambut_1` sampai `rambut_5` (`rambut_5` = hijab); `warna` wajib berisi persis `kulit`, `rambut`, dan `celana` dengan format `#rrggbb`. Respons 200: user seperti `GET /api/me`, tanpa `classrooms`.

### Kelas

Bentuk satu kelas:

```json
{ "id": 1, "name": "Developer", "slug": "developer", "network_cidr": "192.168.10.0/24", "public_ip": "103.10.20.30" }
```

#### `GET /api/classrooms`

Siapa: yang sudah login. Koordinator mendapat semua kelas, peserta cuma kelas yang ia ikuti. Urut nama. Respons 200: `{"data": [kelas, ...]}`.

#### `POST /api/classrooms`

Siapa: koordinator.

```json
{ "name": "Developer", "slug": "developer", "network_cidr": "192.168.10.0/24", "public_ip": "103.10.20.30" }
```

Aturan: `name` wajib; `slug` wajib, unik, cuma huruf kecil, angka, dan tanda hubung; `network_cidr` boleh `null`, kalau diisi harus CIDR seperti `192.168.10.0/24`; `public_ip` boleh `null`, kalau diisi harus IP. Respons 201: kelas.

#### `GET /api/classrooms/{slug}`

Siapa: koordinator, anggota kelas itu. Respons 200: kelas.

#### `PUT /api/classrooms/{slug}`

Siapa: koordinator. Body dan aturannya sama dengan POST, semua field dikirim. Respons 200: kelas.

### Jadwal

Bentuk satu jadwal:

```json
{ "id": 1, "type": "kelas", "day_of_week": 1, "starts_at": "08:00", "ends_at": "16:00" }
```

`day_of_week`: 1 = Senin sampai 7 = Minggu. Jadwal `istirahat` berada di dalam rentang jadwal `kelas` di hari yang sama.

#### `GET /api/classrooms/{slug}/schedules`

Siapa: koordinator, anggota kelas itu. Urut hari lalu jam mulai. Respons 200: `{"data": [jadwal, ...]}`.

#### `POST /api/classrooms/{slug}/schedules`

Siapa: koordinator.

```json
{ "type": "istirahat", "day_of_week": 1, "starts_at": "12:00", "ends_at": "13:00" }
```

Aturan: `type` `kelas` atau `istirahat`; `day_of_week` 1–7; jam format `HH:MM`; `ends_at` setelah `starts_at`. Respons 201: jadwal.

#### `PUT /api/classrooms/{slug}/schedules/{id}`

Siapa: koordinator. Body sama dengan POST, semua field dikirim. Respons 200: jadwal.

#### `DELETE /api/classrooms/{slug}/schedules/{id}`

Siapa: koordinator. Respons 204.

### Anggota dan kursi

`{id}` di path adalah id keanggotaan (`classroom_members.id`), bukan id user. Bentuk satu anggota:

```json
{
  "id": 3,
  "role": "anggota",
  "seat_code": "b2",
  "user": { "id": 7, "name": "Budi", "email": "budi@example.com", "role": "peserta", "avatar": null }
}
```

#### `GET /api/classrooms/{slug}/members`

Siapa: koordinator, ketua kelas itu. Urut `seat_code`; yang belum punya kursi ada di awal. Respons 200: `{"data": [anggota, ...]}`.

#### `POST /api/classrooms/{slug}/members`

Siapa: koordinator, ketua kelas itu.

```json
{ "email": "budi@example.com", "name": "Budi", "seat_code": "B2" }
```

- Kalau email belum terdaftar, akun peserta dibuat dengan `name` itu (wajib). `password` opsional, minimal 8 karakter kalau diisi; peserta masuk lewat kode install dan link dari agent, jadi biasanya dikosongkan.
- Kalau email sudah terdaftar, akunnya cuma ditambahkan ke kelas. `name` dan `password` diabaikan, jadi ketua kelas tidak bisa mengganti password orang lain.
- `seat_code` boleh kosong. Kalau diisi, harus ada di daftar kursi kelas itu dan belum dipakai anggota lain.
- Ditolak dengan 422: email milik koordinator, atau user yang sudah jadi anggota kelas itu.

Respons 201: anggota dengan role `anggota`.

#### `PATCH /api/classrooms/{slug}/members/{id}`

Siapa: koordinator, ketua kelas itu. Mengubah `role` cuma boleh koordinator; ketua kelas mendapat 403.

```json
{ "seat_code": "c6" }
```

```json
{ "role": "admin" }
```

Field boleh dikirim salah satu. `"seat_code": null` mengosongkan kursi. Menjadikan anggota `admin` otomatis menurunkan ketua lama di kelas itu jadi `anggota`. Respons 200: anggota.

#### `DELETE /api/classrooms/{slug}/members/{id}`

Siapa: koordinator, ketua kelas itu. Akun user tetap ada, cuma keanggotaannya yang dihapus. Respons 204.

### Laptop (device)

Peserta tidak memakai password. Alurnya:

1. Ketua kelas atau koordinator membuat perintah install pribadi untuk satu anggota.
2. Anggota itu paste perintahnya di terminal. Installer memasang agent, lalu `kelas3d login <kode>` menukar kode jadi token.
3. Laptop langsung aktif atas nama anggota itu, tanpa approve.
4. Untuk membuka web, agent meminta link masuk sekali pakai (`kelas3d buka`).

Koordinator tetap login web dengan email dan password.

#### `POST /api/classrooms/{slug}/members/{id}/install-code`

Siapa: koordinator, ketua kelas itu. `{id}` adalah id keanggotaan. Tanpa body. Respons 201:

```json
{
  "data": {
    "code": "BCDF-GHJK",
    "expires_at": "2026-11-10T09:00:00+07:00",
    "commands": {
      "windows": "irm http://localhost:8000/install.ps1 | iex; kelas3d login BCDF-GHJK",
      "macos_linux": "curl -fsSL http://localhost:8000/install.sh | sh -s -- BCDF-GHJK"
    }
  }
}
```

Kode berlaku sekali selama 1 hari. Membuat kode baru membatalkan kode lama milik anggota yang sama. Alamat installer diambil dari `INSTALL_URL` di `.env` (default `APP_URL`). Script installer-nya ada di repo `kelas3d-agent`. Di macOS dan Linux, kodenya diteruskan ke installer (`sh -s -- <kode>`) dan installer yang menjalankan `kelas3d login` dengan path lengkap, karena folder biner yang baru ditambahkan ke PATH belum berlaku di shell peserta.

#### `GET /api/classrooms/{slug}/devices`

Siapa: koordinator, ketua kelas itu. Laptop milik anggota kelas, terbaru dulu:

```json
{
  "data": [
    {
      "id": 4, "hostname": "laptop-budi", "os": "windows", "agent_version": "0.1.0", "status": "approved",
      "approved_at": "...", "last_seen_at": "...", "user": { "id": 7, "name": "Budi" }
    }
  ]
}
```

#### `PATCH /api/devices/{id}`

Siapa: koordinator, atau ketua kelas dari kelas pemilik laptop.

```json
{ "status": "revoked" }
```

Cuma `revoked` yang diterima. Token agent langsung dihapus, jadi heartbeat berikutnya ditolak 401. Untuk memasang lagi, buat kode install baru. Respons 200: laptop.

### Agent

#### `POST /api/agent/login`

Siapa: agent, tanpa token. Maksimal 10 kali per menit.

```json
{ "code": "BCDF-GHJK", "hostname": "laptop-budi", "os": "windows", "agent_version": "0.1.0" }
```

Tanda hubung, spasi, dan huruf kecil di `code` diabaikan. `os`: `windows`, `macos`, atau `linux`. Respons 200:

```json
{ "token": "5|abc...", "device_id": 4, "user": { "id": 7, "name": "Budi" } }
```

Simpan `token` dan pakai sebagai `Authorization: Bearer` di endpoint agent lain. Gagal: 422 `errors.code` kalau kode salah, sudah dipakai, atau kedaluwarsa.

#### `POST /api/agent/web-link`

Siapa: agent dengan token laptop aktif. Tanpa body. Respons 200:

```json
{ "url": "http://localhost:3000/masuk?token=kode-panjang-40-karakter", "expires_in": 120 }
```

Agent membuka `url` di browser. Halaman `/masuk` di web mengambil `token` dari URL lalu memanggil `POST /api/login/link`. Link berlaku sekali selama 2 menit.

#### `GET /api/agent/config`

Siapa: agent dengan token laptop aktif.

```json
{
  "heartbeat_interval": 10,
  "idle_limit": 300,
  "classrooms": [
    {
      "slug": "developer",
      "name": "Developer",
      "network_cidr": "192.168.10.0/24",
      "schedules": [
        { "id": 1, "type": "kelas", "day_of_week": 1, "starts_at": "08:00", "ends_at": "16:00" },
        { "id": 2, "type": "istirahat", "day_of_week": 1, "starts_at": "12:00", "ends_at": "13:00" }
      ]
    }
  ],
  "app_rules": [
    { "match_on": "title", "pattern": "ChatGPT", "os": null, "activity": "vibe_coding", "label": "ChatGPT", "priority": 30 }
  ]
}
```

`app_rules` cuma yang aktif dan berlaku untuk OS laptop ini, urut prioritas tertinggi dulu. Agent cuma mengirim heartbeat di dalam jadwal `kelas`.

#### `POST /api/agent/heartbeat`

Siapa: agent dengan token laptop aktif (403 kalau laptop di-revoke).

```json
{
  "activity": "ngoding",
  "app_label": "VS Code",
  "project": "toko-online",
  "file_name": "auth.ts",
  "language": "TypeScript",
  "idle_seconds": 12,
  "local_ip": "192.168.10.23",
  "agent_version": "0.1.0",
  "last_announcement_id": 12,
  "last_nudge_id": 6,
  "sent_at": "2026-11-06T09:15:20+07:00",
  "queued": []
}
```

- Wajib: `idle_seconds`, `agent_version`, `sent_at`. `activity` salah satu enum aktivitas atau `null`.
- `queued` berisi maksimal 500 heartbeat tertunda dengan field yang sama (tanpa `agent_version`, `last_*`, dan `queued`). Dipakai untuk riwayat saja, urut `sent_at`, dan tidak di-broadcast. Yang lebih tua dari heartbeat terakhir atau dari sesi yang sedang jalan diabaikan.

Respons 200:

```json
{
  "announcements": [{ "id": 13, "message": "Istirahat 15 menit, lanjut jam 10.30" }],
  "nudges": [{ "id": 7, "about": "Budi", "message": "Coba cek Budi, dari tadi idle" }]
}
```

`announcements` berisi pengumuman untuk semua kelas atau kelas pemilik laptop dengan id di atas `last_announcement_id`; kalau `last_announcement_id` kosong, pengumuman hari ini. `nudges` cuma terisi untuk ketua kelas: teguran berstatus `terkirim` dengan id di atas `last_nudge_id`. Maksimal 20 per jenis.

Yang terjadi di server:

1. **Ruangan.** `local_ip` masuk `network_cidr` sebuah kelas, atau IP request sama dengan `public_ip`, atau kelas tanpa pengaturan jaringan yang jadwal kelasnya sedang jalan. Kalau tidak ada yang cocok, peserta dihitung `keluar`.
2. **Keadaan,** berurutan:
   - Di luar jaringan atau di luar jadwal kelas: `keluar`.
   - Jam istirahat terjadwal: `istirahat` dengan alasan `jadwal_istirahat`.
   - `idle_seconds` 300 ke atas: `istirahat` dengan alasan `idle`.
   - Selain itu: `di_kelas`.
3. **Sesi.** Sesi di `activity_sessions` ditutup dan dibuka lagi tiap ruangan, keadaan, aktivitas, project, atau bahasa berganti. Waktu `keluar` tidak dicatat sebagai sesi.
4. **Presence.** `presences` diperbarui. Saat `keluar`, aplikasi, project, dan file dikosongkan.
5. **Broadcast.** `PresenceChanged` dikirim kalau ada yang berubah.

Angka-angkanya ada di `config/kelas3d.php`.

### Presence dan rekap

#### `GET /api/classrooms/{slug}/presences`

Siapa: koordinator, anggota kelas itu. Satu item per anggota, urut kode kursi. Anggota yang belum pernah kirim heartbeat atau sedang di kelas lain dianggap `keluar`.

```json
{
  "data": [
    {
      "member_id": 3,
      "role": "anggota",
      "seat_code": "a2",
      "user": { "id": 7, "name": "Budi", "avatar": { "tipe_badan": "laki", "rambut": "rambut_1", "warna": { "kulit": "#c68642", "rambut": "#2b1b17", "celana": "#1f2937" } } },
      "state": "di_kelas",
      "state_reason": null,
      "activity": "ngoding",
      "app_label": "VS Code",
      "state_since": "2026-11-09T02:00:00.000000Z",
      "last_heartbeat_at": "2026-11-09T02:15:20.000000Z",
      "help_request": { "id": 5, "note": "Error migrate", "status": "menunggu" },
      "project": "toko-online",
      "file_name": "auth.ts",
      "language": "TypeScript"
    }
  ],
  "coordinators": [{ "id": 1, "name": "Koordinator", "avatar": null }]
}
```

- `project`, `file_name`, dan `language` cuma ada untuk koordinator dan ketua kelas.
- `help_request` berisi permintaan bantuan yang belum selesai, atau `null`.
- `coordinators` berisi koordinator yang sedang berkunjung ke kelas ini.
- Waktu di respons REST memakai UTC (`02:00:00Z` = 09.00 WIB), sedangkan payload event realtime memakai `+07:00`. Di web, cukup `new Date(...)` untuk keduanya.

#### `GET /api/users/{id}/activity`

Siapa: pemiliknya (`/api/users/me/activity`), koordinator, atau ketua kelas dari kelas user itu. Query `from` dan `to` format `YYYY-MM-DD`, default 7 hari terakhir.

```json
{
  "data": {
    "from": "2026-11-09",
    "to": "2026-11-09",
    "per_day": [{ "date": "2026-11-09", "di_kelas": 7200, "istirahat": 3600 }],
    "per_project": [{ "project": "toko-online", "seconds": 5400 }, { "project": "tugas-pbo", "seconds": 1800 }],
    "per_language": [{ "language": "PHP", "seconds": 5400 }, { "language": "Java", "seconds": 1800 }]
  }
}
```

Semua dalam detik. Sesi yang masih jalan dihitung sampai sekarang. Rekap project dan bahasa cuma dari waktu `di_kelas`.

### Kunjungan koordinator

#### `POST /api/coordinator/visit`

Siapa: koordinator.

```json
{ "classroom": "developer" }
```

`classroom` wajib ada, isinya slug kelas tujuan atau `null` untuk kembali ke ruang koordinator. Posisi terakhir disimpan di cache dan muncul di `coordinators` pada `GET /api/classrooms/{slug}/presences`. Kelas asal dan tujuan menerima `CoordinatorMoved`. Respons 200: `{"data": {"from": null, "to": "developer"}}`.

### Pengumuman

#### `POST /api/announcements`

Siapa: koordinator.

```json
{ "message": "Istirahat 15 menit, lanjut jam 10.30", "classroom": "developer" }
```

`classroom` kosong atau `null` berarti untuk semua kelas. Respons 201: `{"data": {"id": 13, "message": "...", "classroom": "developer", "created_at": "..."}}`. Pengumuman dikirim sebagai `AnnouncementSent` ke web, dan ke agent lewat balasan heartbeat.

### Angkat tangan

Bentuk satu permintaan bantuan:

```json
{
  "id": 5, "classroom": "developer", "user": { "id": 7, "name": "Budi" }, "note": "Error migrate",
  "status": "menunggu", "handled_by": null, "resolved_at": null, "created_at": "..."
}
```

#### `POST /api/help-requests`

Siapa: anggota kelas itu, termasuk ketua.

```json
{ "classroom": "developer", "note": "Error migrate" }
```

Selama permintaan sebelumnya belum `selesai`, permintaan baru ditolak 422. Respons 201.

#### `PATCH /api/help-requests/{id}`

Siapa: koordinator dan ketua kelas itu boleh mengisi `ditangani` atau `selesai`. Peserta yang angkat tangan cuma boleh `selesai` (menurunkan tangan).

```json
{ "status": "ditangani" }
```

Staf yang mengubah status tercatat di `handled_by`. Respons 200. Pembuatan dan tiap perubahan status dikirim sebagai `HelpRequested`.

### Teguran lewat ketua kelas

Bentuk satu teguran:

```json
{
  "id": 7, "classroom": "developer",
  "from": { "id": 1, "name": "Koordinator" }, "to": { "id": 3, "name": "Sinta" }, "about": { "id": 7, "name": "Budi" },
  "message": "Coba cek Budi, dari tadi idle", "status": "terkirim", "read_at": null, "handled_at": null, "created_at": "..."
}
```

#### `GET /api/nudges`

Siapa: yang login. Ketua kelas mendapat teguran yang ditujukan kepadanya, koordinator mendapat teguran yang ia kirim. Cuma yang belum `selesai`, terbaru dulu.

#### `POST /api/nudges`

Siapa: koordinator.

```json
{ "classroom": "developer", "about_user_id": 7, "message": "Coba cek Budi, dari tadi idle" }
```

`about_user_id` harus anggota kelas itu. Teguran otomatis ditujukan ke ketua kelasnya; kalau kelas belum punya ketua, ditolak 422. Respons 201. Teguran dikirim sebagai `NudgeSent` di channel staf, dan ke laptop ketua kelas lewat balasan heartbeat.

#### `PATCH /api/nudges/{id}`

Siapa: ketua kelas penerima teguran.

```json
{ "status": "dibaca" }
```

`status`: `dibaca` atau `selesai`. `read_at` terisi saat pertama ditandai, `handled_at` saat `selesai`. Respons 200. Tiap perubahan juga dikirim sebagai `NudgeSent`.

### Aturan aplikasi

Bentuk satu aturan:

```json
{ "id": 3, "match_on": "process", "pattern": "Cursor.exe", "os": "windows", "activity": "vibe_coding", "label": "Cursor", "priority": 20, "is_active": true }
```

Cara pencocokannya ada di bagian Aturan app_rules. Perubahan terpakai saat agent mengambil `GET /api/agent/config` berikutnya, jadi agent di laptop tidak perlu di-update.

#### `GET /api/app-rules`

Siapa: koordinator. Semua aturan termasuk yang nonaktif, urut prioritas tertinggi dulu.

#### `POST /api/app-rules`

Siapa: koordinator.

```json
{ "match_on": "process", "pattern": "Cursor.exe", "os": "windows", "activity": "vibe_coding", "label": "Cursor", "priority": 20 }
```

Aturan: `match_on` `process` atau `title`; `pattern` wajib; `os` `windows`, `macos`, `linux`, atau `null` (semua OS); `activity` salah satu enum aktivitas; `label` wajib, maksimal 100 karakter; `priority` 0–1000; `is_active` opsional, default `true`. Respons 201.

#### `PATCH /api/app-rules/{id}`

Siapa: koordinator. Field boleh sebagian, misalnya `{"is_active": false}` untuk mematikan sementara. Respons 200.

#### `DELETE /api/app-rules/{id}`

Siapa: koordinator. Respons 204.

## Realtime

Laravel Reverb di `ws://localhost:8080`, otorisasi channel lewat `POST http://localhost:8000/api/broadcasting/auth` dengan sesi web yang sama (cookie dan `X-XSRF-TOKEN`). Contoh Laravel Echo di web:

```ts
const echo = new Echo({
  broadcaster: 'reverb',
  key: process.env.NEXT_PUBLIC_REVERB_APP_KEY,
  wsHost: 'localhost',
  wsPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws'],
  authEndpoint: 'http://localhost:8000/api/broadcasting/auth',
  // Echo perlu fetch/axios yang mengirim cookie dan X-XSRF-TOKEN, lihat Cara memanggil API.
});

echo.private('classroom.developer').listen('.PresenceChanged', (e) => { /* ganti animasi avatar */ });
```

Nama event memakai titik di depan (`.PresenceChanged`) karena dikirim dengan nama pendek, bukan nama kelas PHP. `NEXT_PUBLIC_REVERB_APP_KEY` diisi nilai `REVERB_APP_KEY` di `.env` API.

| Channel | Yang boleh mendengar | Event |
| --- | --- | --- |
| `private-classroom.{slug}` | Koordinator, anggota kelas itu | `PresenceChanged`, `HelpRequested`, `AnnouncementSent`, `CoordinatorMoved` |
| `private-classroom.{slug}.staff` | Koordinator, ketua kelas itu | `NudgeSent` |

Payload:

```json
// PresenceChanged: tanpa project dan file, staf mengambil detailnya lewat REST
{ "user_id": 7, "state": "istirahat", "state_reason": "idle", "activity": "ngoding", "app_label": "VS Code", "state_since": "2026-11-09T09:05:00+07:00" }

// HelpRequested: saat angkat tangan dan tiap status berubah
{ "id": 5, "user": { "id": 7, "name": "Budi" }, "note": "Error migrate", "status": "menunggu", "handled_by": null }

// AnnouncementSent
{ "id": 13, "message": "Istirahat 15 menit", "from": "Koordinator", "for_all": false, "created_at": "2026-11-09T10:15:00+07:00" }

// CoordinatorMoved: null = ruang koordinator, dikirim ke kelas asal dan tujuan
{ "coordinator": { "id": 1, "name": "Koordinator", "avatar": null }, "from": null, "to": "developer" }

// NudgeSent: saat dikirim dan tiap status berubah
{ "id": 7, "from": { "id": 1, "name": "Koordinator" }, "to": { "id": 3, "name": "Sinta" }, "about": { "id": 7, "name": "Budi" }, "message": "Coba cek Budi", "status": "terkirim" }
```

`PresenceChanged` untuk peserta yang pindah kelas atau keluar jaringan juga dikirim ke kelas asalnya dengan `state` `keluar`.

Scheduler `presences:check` jalan tiap 30 detik. Ia memakai aturan yang sama dengan heartbeat:

- Tanpa heartbeat 2 menit: `istirahat` dengan alasan `terputus`.
- Tanpa heartbeat 30 menit, atau jam kelas selesai: `keluar`.
- Jam istirahat mulai: semua yang di kelas jadi `istirahat`.

## Menjalankan di lokal

```bash
composer dev
```

Perintah ini menjalankan `serve` (port 8000), `queue:listen` (event broadcast lewat antrean), `pail` (log), `reverb:start` (port 8080), dan `schedule:work` (scheduler) sekaligus. Kalau mau dijalankan terpisah, kelimanya tetap harus nyala supaya realtime jalan.

## Riwayat perubahan

### 2026-11-07 · Tahap A, Pondasi

File baru:

- Hasil `composer create-project laravel/laravel` 13.40.0: struktur standar `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `routes/`, `storage/`, `tests/`, `artisan`, `composer.json`, `composer.lock`, `phpunit.xml`, `.env.example`, `.editorconfig`, `.gitattributes`, `.gitignore`.
- Dari `php artisan install:api` (Sanctum): `routes/api.php`, `config/sanctum.php`, `database/migrations/2026_11_07_162555_create_personal_access_tokens_table.php`.
- Dari `php artisan install:broadcasting --reverb --without-node`: `routes/channels.php`, `config/broadcasting.php`, `config/reverb.php`. Event-nya baru dibuat di tahap C.
- Dari `pest --init`: `tests/Pest.php`. Dari `php artisan config:publish cors`: `config/cors.php`.
- `app/Enums/`: `UserRole`, `MemberRole`, `ScheduleType`, `DeviceOs`, `DeviceStatus`, `PresenceState`, `StateReason`, `Activity`, `MatchOn`, `HelpStatus`, `NudgeStatus`. Tiap enum di database punya backed enum dan di-cast di model.
- `database/migrations/2026_11_07_170001` sampai `170010`: `classrooms`, `classroom_members`, `class_schedules`, `devices`, `presences`, `activity_sessions`, `app_rules`, `help_requests`, `nudges`, `announcements`.
- `app/Models/`: `Classroom`, `ClassroomMember`, `ClassSchedule`, `Device`, `Presence`, `ActivitySession`, `AppRule`, `HelpRequest`, `Nudge`, `Announcement`. `Classroom` di-bind lewat slug (`#[RouteKey('slug')]`). `Presence` memakai `user_id` sebagai primary key. `Device` bisa memiliki token Sanctum.
- `database/factories/`: factory untuk sepuluh model di atas.
- `database/seeders/ClassroomSeeder.php`: kelas Developer dan Data Science, jadwal kelas Senin–Jumat 08.00–16.00, istirahat 12.00–13.00 (Jumat 11.30–13.00).
- `database/seeders/AppRuleSeeder.php`: 27 aturan awal untuk `ngoding`, `vibe_coding`, `ngolah_data`, `browsing`, dan `main_game`.
- `config/kelas3d.php`: daftar kode kursi per kelas, pilihan avatar, dan akun koordinator untuk seeder dari `.env`.
- `app/Policies/ClassroomPolicy.php`, `app/Policies/UserPolicy.php`: hak akses kelas, jadwal, anggota, dan avatar.
- `app/Http/Requests/`: `LoginRequest`, `ClassroomRequest`, `ScheduleRequest`, `MemberRequest`, `AvatarRequest`.
- `app/Http/Resources/`: `UserResource`, `ClassroomResource`, `ScheduleResource`, `MemberResource`.
- `app/Http/Controllers/`: `AuthController`, `ClassroomController`, `ScheduleController`, `MemberController`, `UserController`.
- `tests/Feature/`: `AuthTest`, `ClassroomTest`, `ScheduleTest`, `MemberTest`, `AvatarTest`, `SeederTest`.
- `resources/views/.gitkeep`: folder views harus ada supaya `php artisan optimize` tidak gagal.
- `dokumentasi.md`: catatan ini.

File diubah:

- `database/migrations/0001_01_01_000000_create_users_table.php`: kolom `users` sesuai rencana (`lms_user_id`, `password` nullable, `role`, `avatar`), tanpa `email_verified_at`, `remember_token`, dan tabel `password_reset_tokens`.
- `app/Models/User.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`: role, avatar, relasi, dan akun koordinator dari `SEED_KOORDINATOR_EMAIL` dan `SEED_KOORDINATOR_PASSWORD`. Seeder berhenti dengan pesan jelas kalau keduanya kosong.
- `config/auth.php`: guard `sanctum` dengan provider `devices`, jadi token yang diterima cuma milik `Device`.
- `config/sanctum.php`: `guard` dikosongkan; web memakai guard session `web` langsung.
- `config/cors.php`: origin dari `FRONTEND_URL` dan `supports_credentials` aktif.
- `config/app.php`: zona waktu `Asia/Jakarta`.
- `bootstrap/app.php`: route `api` dan `channels`, plus `statefulApi()` untuk Sanctum SPA.
- `routes/api.php`: endpoint tahap A. `routes/web.php`: halaman awal diganti respons JSON singkat.
- `.env.example`: MariaDB `kelas3d`, `FRONTEND_URL`, `SESSION_DOMAIN=null` (cookie host `localhost` terbaca dari port 3000 maupun 8000), `SANCTUM_STATEFUL_DOMAINS`, variabel Reverb, dan akun koordinator seeder.
- `phpunit.xml`: tes memakai MariaDB database `kelas3d_test`.
- `composer.json`: nama dan deskripsi proyek, Pest menggantikan PHPUnit, skrip `setup` tanpa npm.
- `README.md`: diganti ringkasan proyek.

Dihapus dari skeleton:

- Dua file panduan bawaan skeleton untuk AI agent, serta paket dev `laravel/pao` yang ikut bersamanya.
- `package.json`, `vite.config.js`, `.npmrc`, `resources/css`, `resources/js`, `resources/views/welcome.blade.php`: aset frontend tidak dipakai di repo API.
- `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`.

### 2026-11-08 · Dokumentasi API dengan Scramble

File baru:

- `config/scramble.php`: hasil publish config `dedoc/scramble` 0.13.53. Diubah: judul "Kelas 3D API", penjelasan cara auth web dan agent di halaman depan docs, dan tema `dark` (bisa diganti `light` atau `system` yang ikut tema OS).
- `app/Http/Requests/StoreMemberRequest.php` dan `app/Http/Requests/UpdateMemberRequest.php`: pecahan `MemberRequest`, supaya Scramble mendokumentasikan body POST (email, name, password, seat_code) dan PATCH (seat_code, role) secara terpisah. Aturannya tidak berubah.
- `app/Http/Requests/Concerns/SeatCodeRules.php`: aturan `seat_code` dan pengubahan ke huruf kecil yang dipakai kedua request itu.

File diubah:

- `composer.json`, `composer.lock`: tambah `dedoc/scramble`.
- `app/Http/Controllers/*Controller.php`: docblock singkat tiap endpoint, isinya fungsi dan siapa yang boleh memakai; ini jadi judul dan deskripsi di docs. Endpoint pembuat data ditandai `@status 201`. `MemberController` memakai dua request baru.
- `app/Http/Controllers/UserController.php`: `#[PathParameter]` menjelaskan alias `me`.
- `app/Http/Resources/ScheduleResource.php`, `MemberResource.php`: `@mixin` model aslinya (`ClassSchedule`, `ClassroomMember`) supaya tipe kolomnya terbaca.
- `app/Http/Resources/UserResource.php`: bentuk `avatar` ditulis di `@var`.
- `README.md`: alamat docs.

Dihapus:

- `app/Http/Requests/MemberRequest.php`: diganti dua request di atas.

### 2026-11-08 · Tahap B, C, dan D

Dikerjakan berurutan dalam satu jalan atas permintaan Key.

File baru, tahap B (device dan heartbeat):

- `app/Support/PresenceRules.php`: aturan ruangan (`locate`) dan keadaan (`resolve`), tanpa HTTP dan tanpa database. Dipakai heartbeat dan scheduler.
- `app/Support/PresenceRecorder.php`: menyimpan hasil aturan ke `presences` dan `activity_sessions`, termasuk heartbeat dari antrean dan pengecekan scheduler.
- `app/Http/Middleware/EnsureDeviceApproved.php`: endpoint agent menolak laptop yang belum di-approve.
- `app/Http/Controllers/AgentController.php`: kode device, polling token, config, dan heartbeat.
- `app/Http/Controllers/DeviceController.php`: daftar laptop per kelas, konfirmasi kode, approve dan revoke.
- `app/Http/Controllers/PresenceController.php`: keadaan awal ruangan.
- `app/Http/Requests/`: `DeviceCodeRequest`, `DeviceTokenRequest`, `ConfirmDeviceRequest`, `UpdateDeviceRequest`, `HeartbeatRequest`, `ActivityRequest`.
- `app/Http/Resources/DeviceResource.php`, `PresenceResource.php`.
- `app/Policies/DevicePolicy.php`.
- `tests/Unit/PresenceRulesTest.php`, `tests/Feature/DeviceLoginTest.php`, `HeartbeatTest.php`, `PresenceTest.php`.

File baru, tahap C (realtime):

- `app/Events/`: `PresenceChanged`, `HelpRequested`, `AnnouncementSent`, `CoordinatorMoved`, `NudgeSent`. Semuanya lewat antrean (`ShouldBroadcast`), nama event pendek lewat `broadcastAs`, dan payload dihitung saat event dibuat.
- `app/Http/Controllers/CoordinatorController.php`, `app/Http/Requests/VisitRequest.php`: kunjungan koordinator.
- `tests/Feature/RealtimeTest.php`.

File baru, tahap D (interaksi):

- `app/Http/Controllers/AnnouncementController.php`, `HelpRequestController.php`, `NudgeController.php`.
- `app/Http/Requests/`: `AnnouncementRequest`, `StoreHelpRequest`, `UpdateHelpRequest`, `StoreNudgeRequest`, `UpdateNudgeRequest`.
- `app/Http/Resources/AnnouncementResource.php`, `HelpRequestResource.php`, `NudgeResource.php`.
- `app/Policies/NudgePolicy.php`.
- `tests/Feature/InteractionTest.php`.

File diubah:

- `config/kelas3d.php`: interval heartbeat, batas idle, batas tanpa heartbeat, masa berlaku kode device, dan URL halaman konfirmasi device.
- `routes/api.php`: semua endpoint tahap B, C, dan D.
- `routes/channels.php`: channel `classroom.{slug}` dan `classroom.{slug}.staff`; channel bawaan `App.Models.User.{id}` dihapus.
- `routes/console.php`: perintah `presences:check` dan jadwalnya tiap 30 detik.
- `bootstrap/app.php`: otorisasi channel dipindah ke `/api/broadcasting/auth` dengan sesi web.
- `app/Models/User.php`: `isAdminOver()` untuk policy laptop dan rekap, serta `visiting()` dan `visit()` untuk posisi koordinator.
- `app/Policies/ClassroomPolicy.php`: `requestHelp` dan `coordinate` (aksi khusus koordinator).
- `app/Policies/UserPolicy.php`: `viewActivity`.
- `app/Http/Controllers/UserController.php`: rekap aktivitas.
- `app/Providers/AppServiceProvider.php`: `composer dev` ikut menjalankan Reverb dan scheduler.
- `config/scramble.php`: penjelasan auth agent di halaman depan docs.
- `tests/Pest.php`: helper `kelasDeveloper()`, `laptop()`, dan `laporan()`, plus tes unit memakai TestCase Laravel.

### 2026-11-08 · CRUD aturan aplikasi

Koordinator sekarang bisa mengelola `app_rules` dari web, sesuai tabel Role di rencana. Sebelumnya aturan cuma ada dari seeder.

File baru:

- `app/Http/Controllers/AppRuleController.php`, `app/Http/Requests/AppRuleRequest.php`, `app/Http/Resources/AppRuleResource.php`.
- `tests/Feature/AppRuleTest.php`.

File diubah:

- `routes/api.php`: endpoint `/api/app-rules`. Kunjungan, pengumuman, teguran, dan aturan aplikasi memakai Gate `koordinator`.
- `app/Providers/AppServiceProvider.php`: Gate `koordinator`.
- `app/Policies/ClassroomPolicy.php`: method `coordinate` dihapus, diganti Gate di atas.

### 2026-11-08 · Peserta tanpa password: kode install pribadi

Keputusan Key: peserta dan ketua kelas tidak perlu login dengan password. Alur kode dari agent, konfirmasi di web, lalu approve ketua diganti kode pribadi yang dibuat ketua.

File baru:

- `app/Support/InstallCode.php`: membuat dan menukar kode install pribadi (sekali pakai, 1 hari).
- `app/Http/Requests/AgentLoginRequest.php`, `app/Http/Requests/LinkLoginRequest.php`.

File diubah:

- `app/Http/Controllers/AgentController.php`: `login` (tukar kode jadi token) dan `webLink` (link masuk web) menggantikan `deviceCode` dan `token`.
- `app/Http/Controllers/AuthController.php`: `loginWithLink`. Login web sekarang selalu memakai guard `web` secara eksplisit, supaya tidak ikut guard `sanctum` kalau guard default sempat berganti.
- `app/Http/Controllers/MemberController.php`: `installCode`.
- `app/Http/Controllers/DeviceController.php`: `confirm` dihapus, `update` cuma untuk revoke.
- `app/Http/Requests/UpdateDeviceRequest.php`: cuma menerima `revoked`.
- `app/Http/Requests/StoreMemberRequest.php`: password akun baru opsional.
- `config/kelas3d.php`: `install_code_ttl`, `web_link_ttl`, `install_url`, `web_link_url` menggantikan pengaturan kode device lama.
- `routes/api.php`: `POST /api/agent/login`, `POST /api/agent/web-link`, `POST /api/login/link`, `POST /api/classrooms/{slug}/members/{id}/install-code`.
- `.env.example`: contoh `INSTALL_URL` (dikomentari, default `APP_URL`).
- `tests/Feature/DeviceLoginTest.php`: ditulis ulang untuk alur baru. `tests/Feature/MemberTest.php`: password opsional.

Dihapus:

- `app/Http/Requests/DeviceCodeRequest.php`, `DeviceTokenRequest.php`, `ConfirmDeviceRequest.php`.
- Endpoint `POST /api/agent/device-code`, `POST /api/agent/token`, `POST /api/devices/confirm`.


### 2026-11-08 · Perintah install macOS dan Linux

Perintah `macos_linux` berganti dari `curl … | sh && kelas3d login <kode>` ke `curl … | sh -s -- <kode>`. Di bentuk lama, `kelas3d login` jalan di shell peserta, yang belum mengenal folder biner baru di PATH (misalnya macOS biasa atau Arch tanpa `~/.local/bin`), jadi login gagal. Di bentuk baru, installer `kelas3d-agent` yang menjalankan login dengan path lengkap. Perintah Windows tidak berubah, karena `install.ps1` memperbarui PATH sesi PowerShell yang sama.

File diubah:

- `app/Http/Controllers/MemberController.php`: isi `commands.macos_linux`.
- `tests/Feature/DeviceLoginTest.php`: perintah macOS dan Linux dicek utuh.
