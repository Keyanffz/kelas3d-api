<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('match_on', ['process', 'title']);
            $table->string('pattern');
            $table->enum('os', ['windows', 'macos', 'linux'])->nullable(); // null = semua OS
            $table->enum('activity', ['ngoding', 'vibe_coding', 'ngolah_data', 'browsing', 'main_game', 'lainnya']);
            $table->string('label');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_rules');
    }
};
