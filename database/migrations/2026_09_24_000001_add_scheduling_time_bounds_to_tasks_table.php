<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->time('earliest_start_time')->nullable()->after('duration_minutes');
            $table->time('latest_end_time')->nullable()->after('earliest_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['earliest_start_time', 'latest_end_time']);
        });
    }
};
