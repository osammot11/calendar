<?php

namespace App\Services;

use App\Models\BusyBlock;
use App\Models\ScheduledBlock;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CalendarFeedService
{
    private const PINNED_REMINDER_TRIGGER = '-PT2H';

    public function generate(): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Calendar Planner//Smart Work Calendar//IT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Calendario Lavorativo',
            'X-WR-TIMEZONE:Europe/Rome',
        ];

        foreach ($this->scheduledEvents() as $event) {
            array_push($lines, ...$event);
        }

        foreach ($this->busyEvents() as $event) {
            array_push($lines, ...$event);
        }

        $lines[] = 'END:VCALENDAR';

        return collect($lines)
            ->map(fn (string $line) => $this->foldLine($line))
            ->implode("\r\n")."\r\n";
    }

    private function scheduledEvents(): Collection
    {
        return ScheduledBlock::query()
            ->with('task.project')
            ->orderBy('start_at')
            ->get()
            ->map(function (ScheduledBlock $block) {
                $task = $block->task;
                $project = $task->project;
                $lines = [
                    'BEGIN:VEVENT',
                    'UID:task-'.$block->id.'@'.$this->host(),
                    'DTSTAMP:'.$this->dateTime(now()),
                    'DTSTART:'.$this->dateTime($block->start_at),
                    'DTEND:'.$this->dateTime($block->end_at),
                    'SUMMARY:'.$this->escape($task->title),
                    'DESCRIPTION:'.$this->escape(implode("\n", array_filter([
                        'Progetto: '.$project->name,
                        'Priorita task: '.$task->priority.'/5',
                        'Priorita progetto: '.$project->priority.'/5',
                        $task->is_max_priority ? 'Priorita massima: si' : null,
                        $task->is_pinned ? 'Evento fissato a calendario' : null,
                        $task->description,
                    ]))),
                    'CATEGORIES:'.$this->escape($project->name),
                    'STATUS:'.($task->status === 'done' ? 'COMPLETED' : 'CONFIRMED'),
                ];

                if ($task->is_pinned) {
                    array_push($lines, ...[
                        'BEGIN:VALARM',
                        'ACTION:DISPLAY',
                        'DESCRIPTION:'.$this->escape($task->title),
                        'TRIGGER:'.self::PINNED_REMINDER_TRIGGER,
                        'END:VALARM',
                    ]);
                }

                $lines[] = 'END:VEVENT';

                return $lines;
            });
    }

    private function busyEvents(): Collection
    {
        return BusyBlock::query()
            ->orderBy('start_at')
            ->get()
            ->map(fn (BusyBlock $block) => [
                'BEGIN:VEVENT',
                'UID:busy-'.$block->id.'@'.$this->host(),
                'DTSTAMP:'.$this->dateTime(now()),
                'DTSTART:'.$this->dateTime($block->start_at),
                'DTEND:'.$this->dateTime($block->end_at),
                'SUMMARY:'.$this->escape($block->title),
                'DESCRIPTION:'.$this->escape('Blocco occupato'),
                'STATUS:CONFIRMED',
                'END:VEVENT',
            ]);
    }

    private function dateTime(CarbonInterface $date): string
    {
        return $date->copy()->utc()->format('Ymd\THis\Z');
    }

    private function escape(string $value): string
    {
        return str_replace(
            ["\\", "\r\n", "\n", ';', ','],
            ["\\\\", "\\n", "\\n", "\;", "\,"],
            $value
        );
    }

    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        return rtrim(chunk_split($line, 75, "\r\n "));
    }

    private function host(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }
}
