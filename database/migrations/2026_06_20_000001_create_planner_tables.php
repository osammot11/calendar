<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->default('#6750a4');
            $table->unsignedTinyInteger('priority')->default(3);
            $table->date('deadline')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedTinyInteger('priority')->default(3);
            $table->date('deadline')->nullable();
            $table->boolean('is_max_priority')->default(false);
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        Schema::create('date_work_overrides', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        Schema::create('busy_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->timestamps();
        });

        Schema::create('scheduled_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedInteger('minutes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_blocks');
        Schema::dropIfExists('busy_blocks');
        Schema::dropIfExists('date_work_overrides');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
    }
};
