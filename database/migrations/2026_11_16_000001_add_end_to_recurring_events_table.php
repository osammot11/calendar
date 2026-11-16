<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_events', function (Blueprint $table) {
            $table->string('end_mode')->nullable();
            $table->unsignedInteger('occurrence_count')->nullable();
            $table->date('ends_on')->nullable();
            $table->dateTime('until_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_events', function (Blueprint $table) {
            $table->dropColumn(['end_mode', 'occurrence_count', 'ends_on', 'until_at']);
        });
    }
};
