<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        ['email' => $email, 'password' => $password] = config('kelas3d.koordinator');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('Isi SEED_KOORDINATOR_EMAIL dan SEED_KOORDINATOR_PASSWORD di .env dulu.');
        }

        User::create([
            'name' => 'Koordinator',
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Koordinator,
        ]);

        $this->call([ClassroomSeeder::class, AppRuleSeeder::class]);
    }
}
