<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('worked_at')->nullable()->after('completed_at');
        });

        DB::table('tasks')
            ->where('status', 'done')
            ->orderBy('id')
            ->chunkById(100, function ($tasks): void {
                $blocks = DB::table('scheduled_blocks')
                    ->whereIn('task_id', $tasks->pluck('id'))
                    ->orderBy('start_at')
                    ->get()
                    ->groupBy('task_id');

                foreach ($tasks as $task) {
                    $block = $blocks->get($task->id)?->first(
                        fn ($candidate) => $candidate->start_at <= ($task->completed_at ?? now()->toDateTimeString())
                    );

                    DB::table('tasks')->where('id', $task->id)->update([
                        'worked_at' => $block?->start_at ?? $task->completed_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('worked_at');
        });
    }
};
