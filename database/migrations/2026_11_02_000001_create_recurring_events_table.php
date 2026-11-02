<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->string('start_mode');
            $table->dateTime('starts_at');
            $table->time('latest_start_time')->nullable();
            $table->unsignedSmallInteger('interval_count');
            $table->string('interval_unit');
            $table->unsignedInteger('next_index')->default(0);
            $table->dateTime('next_at');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('recurring_event_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('recurrence_at')->nullable();
            $table->unique(['recurring_event_id', 'recurrence_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['recurring_event_id', 'recurrence_at']);
            $table->dropConstrainedForeignId('recurring_event_id');
            $table->dropColumn('recurrence_at');
        });

        Schema::dropIfExists('recurring_events');
    }
};
