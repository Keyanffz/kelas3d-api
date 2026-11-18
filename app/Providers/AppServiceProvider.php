<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Aksi khusus koordinator: kunjungan, pengumuman, teguran, dan aturan aplikasi.
        Gate::define('koordinator', fn (User $user) => $user->isKoordinator());

        // users/me/... merujuk ke user yang sedang login. Di sini, bukan di routes/api.php,
        // karena file route tidak dibaca lagi setelah `route:cache`.
        Route::bind('user', fn (string $value) => $value === 'me' ? request()->user() : User::findOrFail($value));

        // `composer dev` ikut menjalankan Reverb dan scheduler cek heartbeat.
        if ($this->app->runningInConsole()) {
            DevCommands::artisan('reverb:start', 'reverb');
            DevCommands::artisan('schedule:work', 'schedule');
        }
    }
}
