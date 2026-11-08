<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('state', ['di_kelas', 'istirahat', 'keluar']);
            $table->enum('activity', ['ngoding', 'vibe_coding', 'ngolah_data', 'browsing', 'main_game', 'lainnya'])->nullable();
            $table->string('app_label')->nullable();
            $table->string('project')->nullable();
            $table->string('language')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable(); // null = sesi yang sedang jalan
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_sessions');
    }
};
