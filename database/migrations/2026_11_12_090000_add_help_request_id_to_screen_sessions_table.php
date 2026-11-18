<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Share layar sukarela: mahasiswa sendiri yang membagikan layar ke yang menangani bantuannya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screen_sessions', function (Blueprint $table) {
            $table->foreignId('help_request_id')->nullable()->after('target_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('screen_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('help_request_id');
        });
    }
};
