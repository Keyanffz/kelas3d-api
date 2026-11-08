<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per peserta, jadi user_id langsung jadi primary key.
        Schema::create('presences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('state', ['di_kelas', 'istirahat', 'keluar']);
            $table->enum('state_reason', ['idle', 'terputus', 'jadwal_istirahat'])->nullable();
            $table->enum('activity', ['ngoding', 'vibe_coding', 'ngolah_data', 'browsing', 'main_game', 'lainnya'])->nullable();
            $table->string('app_label')->nullable();
            $table->string('project')->nullable();
            $table->string('file_name')->nullable();
            $table->string('language')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('state_since');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
