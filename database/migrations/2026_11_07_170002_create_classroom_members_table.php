<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'anggota'])->default('anggota');
            $table->string('seat_code')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'user_id']);
            $table->unique(['classroom_id', 'seat_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_members');
    }
};
