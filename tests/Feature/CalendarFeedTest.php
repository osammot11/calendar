<?php

namespace Tests\Feature;

use App\Models\BusyBlock;
use App\Models\Project;
use App\Models\ScheduledBlock;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://calendar.example.test',
            'planner.calendar_feed_token' => 'feed-secret',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-07-06 09:00:00', 'Europe/Rome'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_calendar_feed_requires_the_configured_token(): void
    {
        $this->get('/calendar-feed/wrong-token.ics')->assertNotFound();
    }

    public function test_calendar_feed_adds_two_hour_alarm_only_to_pinned_tasks(): void
    {
        $project = Project::create([
            'name' => 'Cheflow',
            'color' => '#10d130',
            'priority' => 4,
        ]);
        $pinnedTask = $this->task($project, 'Riunione importante', [
            'is_pinned' => true,
            'pinned_start_at' => Carbon::parse('2026-07-06 14:00:00', 'Europe/Rome'),
        ]);
        $normalTask = $this->task($project, 'Task normale');
        ScheduledBlock::create([
            'task_id' => $pinnedTask->id,
            'start_at' => Carbon::parse('2026-07-06 14:00:00', 'Europe/Rome'),
            'end_at' => Carbon::parse('2026-07-06 15:00:00', 'Europe/Rome'),
            'minutes' => 60,
        ]);
        ScheduledBlock::create([
            'task_id' => $normalTask->id,
            'start_at' => Carbon::parse('2026-07-06 16:00:00', 'Europe/Rome'),
            'end_at' => Carbon::parse('2026-07-06 17:00:00', 'Europe/Rome'),
            'minutes' => 60,
        ]);
        BusyBlock::create([
            'title' => 'Blocco occupato',
            'start_at' => Carbon::parse('2026-07-06 18:00:00', 'Europe/Rome'),
            'end_at' => Carbon::parse('2026-07-06 19:00:00', 'Europe/Rome'),
        ]);

        $response = $this->get('/calendar-feed/feed-secret.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $content = $response->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('SUMMARY:Riunione importante', $content);
        $this->assertStringContainsString('DTSTART:20260706T120000Z', $content);
        $this->assertStringContainsString('BEGIN:VALARM', $content);
        $this->assertStringContainsString('TRIGGER:-PT2H', $content);
        $this->assertStringContainsString('SUMMARY:Task normale', $content);
        $this->assertStringContainsString('SUMMARY:Blocco occupato', $content);
        $this->assertSame(1, substr_count($content, 'BEGIN:VALARM'));
    }

    private function task(Project $project, string $title, array $overrides = []): Task
    {
        return Task::create($overrides + [
            'project_id' => $project->id,
            'title' => $title,
            'duration_minutes' => 60,
            'priority' => 3,
            'is_max_priority' => false,
            'is_pinned' => false,
            'status' => 'open',
        ]);
    }
}
