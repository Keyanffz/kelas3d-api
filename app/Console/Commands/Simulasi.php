<?php

namespace App\Console\Commands;

use App\Enums\HelpStatus;
use App\Enums\MemberRole;
use App\Enums\PresenceState;
use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Events\HelpRequested;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Models\HelpRequest;
use App\Models\Presence;
use App\Models\User;
use App\Support\PresenceRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

// Khusus lokal: anggota palsu yang keadaannya berubah-ubah, supaya web bisa dicek dengan banyak avatar tanpa 30 laptop.
// Keadaannya lewat jalur heartbeat sungguhan (PresenceRecorder), jadi event dan rekap ikut terisi.
class Simulasi extends Command
{
    protected $signature = 'kelas3d:simulasi
        {kelas=developer : Slug kelas}
        {--jumlah=12 : Banyak anggota palsu}
        {--interval=5 : Jeda antarputaran dalam detik}
        {--istirahat=0 : Buat jam istirahat sementara mulai sekarang selama sekian menit}
        {--putaran=0 : Berhenti sesudah sekian putaran, 0 = sampai Ctrl+C}
        {--hapus : Hapus anggota palsu kelas ini lalu berhenti}';

    protected $description = 'Simulasi presence anggota palsu (cuma untuk APP_ENV=local)';

    private const NAMA = ['Budi', 'Sinta', 'Rizky', 'Ayu', 'Fajar', 'Dewi', 'Bayu', 'Nadia', 'Galih', 'Putri', 'Yoga', 'Laras', 'Dimas', 'Intan', 'Reza', 'Salsa'];

    // Project, file, dan bahasa untuk persona ngoding.
    private const KODE = [
        ['toko-online', 'auth.ts', 'TypeScript'],
        ['tugas-pbo', 'Main.java', 'Java'],
        ['api-absensi', 'routes.php', 'PHP'],
        ['dashboard-iot', 'main.go', 'Go'],
    ];

    // Bobot pilihan persona tiap kali seorang anggota palsu ganti kegiatan.
    private const PERSONA = ['ngoding' => 35, 'vibe_coding' => 15, 'ngolah_data' => 10, 'browsing' => 12, 'main_game' => 3, 'idle' => 13, 'keluar' => 12];

    private bool $running = true;

    public function handle(PresenceRecorder $recorder): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Simulasi cuma boleh jalan di lokal (APP_ENV=local).');

            return self::FAILURE;
        }

        $classroom = Classroom::where('slug', $this->argument('kelas'))->firstOrFail();
        $prefix = "sim-{$classroom->slug}-";

        if ($this->option('hapus')) {
            $count = User::where('email', 'like', "{$prefix}%@simulasi.test")->delete();
            $this->info("{$count} anggota palsu {$classroom->name} dihapus.");

            return self::SUCCESS;
        }

        $users = $this->members($classroom, $prefix, (int) $this->option('jumlah'));
        $schedules = $this->schedules($classroom);
        // Kelas dengan pengaturan jaringan cuma menerima heartbeat dari jaringannya.
        $localIp = $classroom->network_cidr ? preg_replace('/\.\d+\/\d+$/', '.200', $classroom->network_cidr) : null;

        $this->trap([SIGINT, SIGTERM], fn () => $this->running = false);
        $this->info("{$users->count()} anggota palsu di {$classroom->name}. Ctrl+C untuk berhenti; jadwal sementara ikut dihapus.");

        // null = sedang di luar kelas. Semua mulai dari luar, lalu masuk bertahap.
        $reports = $users->mapWithKeys(fn (User $user) => [$user->id => null])->all();

        try {
            for ($round = 1; $this->running; $round++) {
                foreach ($users as $user) {
                    $reports[$user->id] = $this->step($recorder, $classroom, $user, $reports[$user->id], $localIp);
                }
                if ($round === (int) $this->option('putaran')) {
                    break;
                }
                sleep(max(1, (int) $this->option('interval')));
            }
        } finally {
            ClassSchedule::whereKey($schedules)->delete();
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array<string, mixed>|null
     */
    private function step(PresenceRecorder $recorder, Classroom $classroom, User $user, ?array $report, ?string $localIp): ?array
    {
        if ($report === null) {
            if (random_int(1, 100) > 30) {
                return null;
            }
            $report = $this->report($this->persona(['keluar']));
        } elseif (random_int(1, 100) <= 15) {
            $persona = $this->persona();
            if ($persona === 'keluar') {
                $presence = Presence::find($user->id);
                $presence && $recorder->apply($presence, PresenceState::Keluar, null, null, [], now(), true);

                return null;
            }
            $report = $this->report($persona);
        }

        $recorder->heartbeat($user, [...$report, 'local_ip' => $localIp], $classroom->public_ip, now());
        $this->help($classroom, $user);

        return $report;
    }

    /**
     * @param  list<string>  $except
     */
    private function persona(array $except = []): string
    {
        $weights = Arr::except(self::PERSONA, $except);
        $pick = random_int(1, array_sum($weights));
        foreach ($weights as $persona => $weight) {
            if (($pick -= $weight) <= 0) {
                return $persona;
            }
        }

        return 'ngoding';
    }

    /**
     * @return array<string, mixed>
     */
    private function report(string $persona): array
    {
        [$project, $file, $language] = Arr::random(self::KODE);
        $coding = ['activity' => 'ngoding', 'app_label' => 'VS Code', 'project' => $project, 'file_name' => $file, 'language' => $language];

        return [
            ...match ($persona) {
                'vibe_coding' => ['activity' => 'vibe_coding', 'app_label' => 'ChatGPT'],
                'ngolah_data' => ['activity' => 'ngolah_data', 'app_label' => 'Jupyter', 'project' => 'analisis-penjualan', 'file_name' => 'eda.ipynb', 'language' => 'Python'],
                'browsing' => ['activity' => 'browsing', 'app_label' => 'Firefox'],
                'main_game' => ['activity' => 'main_game', 'app_label' => 'Minecraft'],
                default => $coding,
            },
            // Lewat batas idle (300 detik), jadi istirahat karena idle.
            'idle_seconds' => $persona === 'idle' ? 400 : 5,
        ];
    }

    private function help(Classroom $classroom, User $user): void
    {
        $open = HelpRequest::where('user_id', $user->id)->where('status', '!=', HelpStatus::Selesai)->first();

        if ($open && random_int(1, 100) <= 20) {
            $done = $open->status === HelpStatus::Ditangani;
            $open->update(['status' => $done ? HelpStatus::Selesai : HelpStatus::Ditangani, 'resolved_at' => $done ? now() : null]);
            HelpRequested::dispatch($open->refresh());
        } elseif (! $open && random_int(1, 100) <= 3) {
            $help = $classroom->helpRequests()->create([
                'user_id' => $user->id,
                'note' => Arr::random(['Error migrate', 'npm install gagal', 'Bingung relasi tabel', null]),
                'status' => HelpStatus::Menunggu,
            ]);
            HelpRequested::dispatch($help);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function members(Classroom $classroom, string $prefix, int $count): Collection
    {
        $taken = $classroom->members()->pluck('seat_code')->filter()->all();
        $free = array_values(array_diff(config("kelas3d.seats.{$classroom->slug}", []), $taken));

        return collect(range(1, $count))->map(function (int $i) use ($classroom, $prefix, &$free) {
            $user = User::firstOrCreate(
                ['email' => "{$prefix}{$i}@simulasi.test"],
                ['name' => self::NAMA[($i - 1) % count(self::NAMA)], 'role' => UserRole::Peserta, 'avatar' => $this->avatar()],
            );
            // Kalau kursi habis, sisanya tanpa kursi: cuma muncul di mode daftar.
            $classroom->members()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => MemberRole::Anggota, 'seat_code' => array_shift($free)],
            );

            return $user;
        });
    }

    /**
     * Jadwal kelas sepanjang hari ini, plus jam istirahat kalau diminta. Dihapus lagi saat simulasi berhenti.
     *
     * @return list<int>
     */
    private function schedules(Classroom $classroom): array
    {
        $now = now();
        $ids = [$classroom->schedules()->create([
            'type' => ScheduleType::Kelas,
            'day_of_week' => $now->dayOfWeekIso,
            'starts_at' => '00:00',
            'ends_at' => '23:59',
        ])->id];

        if ($minutes = (int) $this->option('istirahat')) {
            $end = $now->copy()->addMinutes($minutes);
            $ids[] = $classroom->schedules()->create([
                'type' => ScheduleType::Istirahat,
                'day_of_week' => $now->dayOfWeekIso,
                'starts_at' => $now->format('H:i'),
                'ends_at' => $end->isSameDay($now) ? $end->format('H:i') : '23:59',
            ])->id;
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function avatar(): array
    {
        $perempuan = random_int(0, 1) === 1;
        $rambut = Arr::random($perempuan ? ['rambut_3', 'rambut_4', 'rambut_5'] : ['rambut_1', 'rambut_2']);

        return [
            'tipe_badan' => $perempuan ? 'perempuan' : 'laki',
            'rambut' => $rambut,
            'warna' => [
                'kulit' => Arr::random(['#f1c27d', '#e0ac69', '#c68642', '#8d5524']),
                // Warna hijab ikut warna rambut.
                'rambut' => Arr::random($rambut === 'rambut_5' ? ['#1e3a8a', '#7f1d1d', '#e7e5e4', '#065f46'] : ['#2b1b17', '#4a3728', '#1c1c1c', '#6b4423']),
                'celana' => Arr::random(['#1f2937', '#374151', '#1e3a8a', '#3f3f46', '#78350f']),
            ],
        ];
    }
}
