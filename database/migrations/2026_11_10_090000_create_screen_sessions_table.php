<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screen_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->enum('status', ['menunggu_ketua', 'menunggu_mahasiswa', 'menyambung', 'live', 'ditolak', 'kedaluwarsa', 'selesai'])
                ->default('menunggu_ketua');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('close_reason', ['ditolak_ketua', 'ditolak_mahasiswa', 'izin_browser', 'dihentikan', 'terputus', 'batas_waktu'])->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['target_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_sessions');
    }
};
