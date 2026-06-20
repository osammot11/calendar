<?php

namespace Database\Seeders;

use App\Models\BusyBlock;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\SchedulerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin',
            'password' => Hash::make('password'),
        ]);

        $product = Project::query()->firstOrCreate(
            ['name' => 'Product Launch'],
            ['color' => '#6750a4', 'priority' => 5, 'deadline' => now()->addWeeks(3)->toDateString()]
        );
        $ops = Project::query()->firstOrCreate(
            ['name' => 'Operations'],
            ['color' => '#006a6a', 'priority' => 3, 'deadline' => now()->addWeeks(6)->toDateString()]
        );

        if (WorkSchedule::query()->doesntExist()) {
            foreach (range(1, 5) as $weekday) {
                WorkSchedule::create([
                    'weekday' => $weekday,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                ]);
            }
        }

        if (Task::query()->doesntExist()) {
            Task::create([
                'project_id' => $product->id,
                'title' => 'Preparare roadmap commerciale',
                'description' => 'Prima bozza con milestone e owner.',
                'duration_minutes' => 240,
                'priority' => 4,
                'deadline' => now()->addDays(5)->toDateString(),
                'is_max_priority' => true,
            ]);
            Task::create([
                'project_id' => $product->id,
                'title' => 'Rivedere pagina pricing',
                'duration_minutes' => 150,
                'priority' => 3,
                'deadline' => now()->addDays(9)->toDateString(),
            ]);
            Task::create([
                'project_id' => $ops->id,
                'title' => 'Pulizia backlog supporto',
                'duration_minutes' => 180,
                'priority' => 5,
            ]);
        }

        BusyBlock::query()->firstOrCreate([
            'title' => 'Riunione settimanale',
            'start_at' => now()->next('Monday')->setTime(10, 0),
            'end_at' => now()->next('Monday')->setTime(11, 0),
        ]);

        app(SchedulerService::class)->recalculate();
    }
}
