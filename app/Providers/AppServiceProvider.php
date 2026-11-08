<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Gate;
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

        // `composer dev` ikut menjalankan Reverb dan scheduler cek heartbeat.
        if ($this->app->runningInConsole()) {
            DevCommands::artisan('reverb:start', 'reverb');
            DevCommands::artisan('schedule:work', 'schedule');
        }
    }
}
