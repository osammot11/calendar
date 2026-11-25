<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Posizione scelta a mano trascinando la task nel calendario:
            // ha la precedenza su tutto, appuntamenti fissati compresi.
            $table->dateTime('manual_start_at')->nullable()->after('pinned_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('manual_start_at');
        });
    }
};
