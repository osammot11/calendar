<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_project_and_task(): void
    {
        $this->actingAs(User::factory()->create());

        WorkSchedule::create([
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $this->postJson('/planner-api/projects', [
            'name' => 'Clienti',
            'color' => '#006a6a',
            'priority' => 4,
            'deadline' => null,
        ])->assertOk()->assertJsonPath('projects.0.name', 'Clienti');

        $project = Project::first();

        $this->postJson('/planner-api/tasks', [
            'project_id' => $project->id,
            'title' => 'Preparare offerta',
            'description' => null,
            'duration_minutes' => 90,
            'priority' => 3,
            'deadline' => null,
            'is_max_priority' => false,
            'is_pinned' => false,
            'pinned_start_at' => null,
            'status' => 'open',
        ])->assertOk()->assertJson(fn ($json) => $json->has('events')->has('tasks')->etc());

        $this->assertDatabaseHas(Task::class, ['title' => 'Preparare offerta']);
    }
}
