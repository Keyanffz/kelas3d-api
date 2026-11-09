<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aplikasi lain yang jendelanya terbuka: [{activity, label}]. Null kalau agent belum bisa membacanya.
        Schema::table('presences', function (Blueprint $table) {
            $table->json('open_apps')->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropColumn('open_apps');
        });
    }
};
