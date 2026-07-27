<?php

namespace App\Services\Slack;

use App\Models\Project;
use App\Models\ScheduledBlock;
use App\Models\SlackTaskDraft;
use App\Models\Task;
use App\Services\SchedulerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlackTaskWizardService
{
    private const STEP_TITLE = 'title';
    private const STEP_DESCRIPTION = 'description';
    private const STEP_PROJECT = 'project';
    private const STEP_DURATION = 'duration';
    private const STEP_PRIORITY = 'priority';
    private const STEP_DEADLINE = 'deadline';
    private const STEP_TASK_TYPE = 'task_type';
    private const STEP_PINNED_START = 'pinned_start_at';
    private const STEP_MAX_PRIORITY = 'max_priority';
    private const STEP_CONFIRM = 'confirm';

    public function __construct(
        private readonly SlackClient $slack,
        private readonly SchedulerService $scheduler,
    ) {
    }

    public function startFromCommand(string $userId, string $text = ''): void
    {
        $channelId = $this->slack->openDirectMessage($userId);
        $text = trim($text);
        $activeDraft = $this->activeDraft($userId);

        if ($activeDraft && $this->isRestartCommand($text)) {
            $this->expireDraft($activeDraft);
            $activeDraft = null;
        }

        if ($activeDraft && $this->isCancelCommand($text)) {
            $activeDraft->update([
                'slack_channel_id' => $channelId,
                'expires_at' => $this->expiry(),
            ]);
            $this->cancelDraft($activeDraft);

            return;
        }

        if (! $activeDraft && $this->isCancelCommand($text)) {
            $this->slack->postMessage($channelId, 'Non hai bozze attive da annullare.');

            return;
        }

        if ($activeDraft && $text === '') {
            $activeDraft->update([
                'slack_channel_id' => $channelId,
                'expires_at' => $this->expiry(),
            ]);
            $this->sendResumePrompt($activeDraft);

            return;
        }

        if ($activeDraft) {
            $this->expireDraft($activeDraft);
        }

        $draft = $this->createDraft($userId, $channelId);
        if ($text !== '' && ! $this->isCancelCommand($text)) {
            $this->mergePayload($draft, ['title' => Str::limit($text, 255, '')], self::STEP_DESCRIPTION);
            $this->askDescription($draft);

            return;
        }

        $this->askTitle($draft);
    }

    public function handleMessage(string $userId, string $channelId, string $text): void
    {
        $draft = $this->activeDraft($userId, $channelId);
        if (! $draft) {
            $this->slack->postMessage($channelId, 'Non ho una bozza attiva. Lancia `/task` per iniziare.');

            return;
        }

        $draft->update([
            'slack_channel_id' => $channelId,
            'expires_at' => $this->expiry(),
        ]);

        $text = trim($text);
        if ($this->isCancelCommand($text)) {
            $this->cancelDraft($draft);

            return;
        }

        if ($this->isRestartCommand($text)) {
            $this->expireDraft($draft);
            $newDraft = $this->createDraft($userId, $channelId);
            $this->askTitle($newDraft);

            return;
        }

        if ($this->isSkipCommand($text)) {
            $this->skipCurrentStep($draft);

            return;
        }

        match ($draft->step) {
            self::STEP_TITLE => $this->receiveTitle($draft, $text),
            self::STEP_DESCRIPTION => $this->receiveDescription($draft, $text),
            self::STEP_DURATION => $this->receiveDuration($draft, $text),
            default => $this->sendCurrentStep($draft, 'Per questo passaggio usa i pulsanti o il selettore qui sotto.'),
        };
    }

    public function handleInteraction(array $payload): void
    {
        $userId = (string) data_get($payload, 'user.id');
        $channelId = (string) (data_get($payload, 'channel.id') ?: data_get($payload, 'container.channel_id'));
        $action = (array) data_get($payload, 'actions.0', []);
        $actionId = (string) data_get($action, 'action_id');
        $value = (string) (
            data_get($action, 'value')
            ?: data_get($action, 'selected_option.value')
            ?: data_get($action, 'selected_options.0.value')
        );

        if ($actionId === 'draft_resume') {
            $draft = $this->activeDraft($userId);
            if ($draft) {
                $draft->update(['slack_channel_id' => $channelId, 'expires_at' => $this->expiry()]);
                $this->sendCurrentStep($draft, 'Riprendiamo da qui.');
            }

            return;
        }

        if ($actionId === 'draft_restart') {
            if ($draft = $this->activeDraft($userId)) {
                $this->expireDraft($draft);
            }
            $newDraft = $this->createDraft($userId, $channelId);
            $this->askTitle($newDraft);

            return;
        }

        $draft = $this->activeDraft($userId, $channelId) ?: $this->activeDraft($userId);
        if (! $draft) {
            $this->slack->postMessage($channelId, 'Questa bozza non è più attiva. Lancia `/task` per iniziare.');

            return;
        }

        $draft->update(array_filter([
            'slack_channel_id' => $channelId ?: null,
            'expires_at' => $this->expiry(),
        ], fn ($value) => $value !== null));

        if (str_starts_with($actionId, 'project_select')) {
            $this->receiveProject($draft, $value);

            return;
        }

        match ($actionId) {
            'cancel_draft' => $this->cancelDraft($draft),
            'skip_description', 'skip_deadline' => $this->skipCurrentStep($draft),
            'duration_preset' => $this->receiveDuration($draft, $value),
            'priority_select' => $this->receivePriority($draft, $value),
            'deadline_picker' => $this->receiveDeadline($draft, (string) data_get($action, 'selected_date')),
            'task_type' => $this->receiveTaskType($draft, $value),
            'pinned_datetime' => $this->receivePinnedDateTime($draft, data_get($action, 'selected_date_time')),
            'max_priority' => $this->receiveMaxPriority($draft, $value),
            'confirm_task' => $this->confirmTask($draft),
            default => $this->sendCurrentStep($draft, 'Non ho riconosciuto questa azione. Riproviamo da qui.'),
        };
    }

    private function receiveTitle(SlackTaskDraft $draft, string $title): void
    {
        if ($title === '') {
            $this->askTitle($draft, 'Mi serve un titolo per creare la task.');

            return;
        }

        $this->mergePayload($draft, ['title' => Str::limit($title, 255, '')], self::STEP_DESCRIPTION);
        $this->askDescription($draft);
    }

    private function receiveDescription(SlackTaskDraft $draft, string $description): void
    {
        $this->mergePayload($draft, ['description' => $description === '' ? null : $description], self::STEP_PROJECT);
        $this->askProject($draft);
    }

    private function receiveProject(SlackTaskDraft $draft, string $projectId): void
    {
        $project = Project::query()->find((int) $projectId);
        if (! $project) {
            $this->askProject($draft, 'Non trovo più quel progetto. Scegline uno dalla lista aggiornata.');

            return;
        }

        $this->mergePayload($draft, ['project_id' => $project->id], self::STEP_DURATION);
        $this->askDuration($draft);
    }

    private function receiveDuration(SlackTaskDraft $draft, string $duration): void
    {
        $minutes = $this->parseDuration($duration);
        if ($minutes === null) {
            $this->askDuration($draft, 'Durata non chiara. Puoi scrivere per esempio `30m`, `1h` o `1h30`.');

            return;
        }

        $this->mergePayload($draft, ['duration_minutes' => $minutes], self::STEP_PRIORITY);
        $this->askPriority($draft);
    }

    private function receivePriority(SlackTaskDraft $draft, string $priority): void
    {
        $priority = (int) $priority;
        if ($priority < 1 || $priority > 5) {
            $this->askPriority($draft, 'La priorità deve essere fra 1 e 5.');

            return;
        }

        $this->mergePayload($draft, ['priority' => $priority], self::STEP_DEADLINE);
        $this->askDeadline($draft);
    }

    private function receiveDeadline(SlackTaskDraft $draft, string $date): void
    {
        if ($date === '') {
            $this->askDeadline($draft, 'Scegli una data o usa Skip.');

            return;
        }

        $this->mergePayload($draft, ['deadline' => $date], self::STEP_TASK_TYPE);
        $this->askTaskType($draft);
    }

    private function receiveTaskType(SlackTaskDraft $draft, string $type): void
    {
        if (! in_array($type, ['auto', 'pinned'], true)) {
            $this->askTaskType($draft, 'Scegli se è una task automatica o un appuntamento fissato.');

            return;
        }

        $nextStep = $type === 'pinned' ? self::STEP_PINNED_START : self::STEP_MAX_PRIORITY;
        $this->mergePayload($draft, ['task_type' => $type], $nextStep);

        if ($type === 'pinned') {
            $this->askPinnedDateTime($draft);

            return;
        }

        $this->askMaxPriority($draft);
    }

    private function receivePinnedDateTime(SlackTaskDraft $draft, mixed $timestamp): void
    {
        if (! is_numeric($timestamp)) {
            $this->askPinnedDateTime($draft, 'Scegli giorno e ora dell’appuntamento.');

            return;
        }

        $date = Carbon::createFromTimestamp((int) $timestamp, config('app.timezone'))->toDateTimeString();
        $this->mergePayload($draft, ['pinned_start_at' => $date], self::STEP_MAX_PRIORITY);
        $this->askMaxPriority($draft);
    }

    private function receiveMaxPriority(SlackTaskDraft $draft, string $value): void
    {
        if (! in_array($value, ['yes', 'no'], true)) {
            $this->askMaxPriority($draft, 'Scegli Sì o No.');

            return;
        }

        $this->mergePayload($draft, ['is_max_priority' => $value === 'yes'], self::STEP_CONFIRM);
        $this->askConfirmation($draft);
    }

    private function skipCurrentStep(SlackTaskDraft $draft): void
    {
        if ($draft->step === self::STEP_DESCRIPTION) {
            $this->mergePayload($draft, ['description' => null], self::STEP_PROJECT);
            $this->askProject($draft);

            return;
        }

        if ($draft->step === self::STEP_DEADLINE) {
            $this->mergePayload($draft, ['deadline' => null], self::STEP_TASK_TYPE);
            $this->askTaskType($draft);

            return;
        }

        $this->sendCurrentStep($draft, 'Qui non posso saltare il passaggio.');
    }

    private function confirmTask(SlackTaskDraft $draft): void
    {
        if ($draft->isCompleted()) {
            $this->slack->postMessage($draft->slack_channel_id, 'Questa task è già stata creata.');

            return;
        }

        if ($draft->step !== self::STEP_CONFIRM) {
            $this->sendCurrentStep($draft, 'Prima completiamo tutti i campi.');

            return;
        }

        $task = DB::transaction(function () use ($draft) {
            $draft->refresh();
            if ($draft->task_id) {
                return $draft->task;
            }

            $payload = $draft->payload ?? [];
            $task = Task::create([
                'project_id' => $payload['project_id'],
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'duration_minutes' => $payload['duration_minutes'],
                'priority' => $payload['priority'],
                'deadline' => $payload['deadline'] ?? null,
                'is_max_priority' => (bool) ($payload['is_max_priority'] ?? false),
                'is_pinned' => ($payload['task_type'] ?? 'auto') === 'pinned',
                'pinned_start_at' => $payload['pinned_start_at'] ?? null,
                'status' => 'open',
            ]);

            $draft->update([
                'task_id' => $task->id,
                'completed_at' => now(),
                'expires_at' => now(),
            ]);

            return $task;
        });

        $this->scheduler->scheduleTask($task);
        $task->load('project', 'scheduledBlocks');

        $this->slack->postMessage(
            $draft->slack_channel_id,
            'Task creata.',
            $this->confirmationResultBlocks($task)
        );
    }

    private function askTitle(SlackTaskDraft $draft, ?string $prefix = null): void
    {
        $this->send($draft, $prefix, 'Scrivimi il titolo della task.');
    }

    private function askDescription(SlackTaskDraft $draft, ?string $prefix = null): void
    {
        $this->send($draft, $prefix, 'Vuoi aggiungere una descrizione?', [[
            'type' => 'actions',
            'elements' => [
                $this->button('Salta', 'skip_description', 'skip'),
                $this->button('Annulla', 'cancel_draft', 'cancel'),
            ],
        ]]);
    }

    private function askProject(SlackTaskDraft $draft, ?string $prefix = null): void
    {
        $projects = Project::query()->orderByDesc('priority')->orderBy('name')->limit(45)->get();
        if ($projects->isEmpty()) {
            $this->send($draft, $prefix, 'Non ci sono progetti. Creane almeno uno dalla web app, poi rilancia `/task`.');

            return;
        }

        $projectBlocks = $projects
            ->chunk(5)
            ->map(fn ($chunk) => [
                'type' => 'actions',
                'elements' => $chunk
                    ->map(fn (Project $project) => $this->button(
                        Str::limit($project->name, 30, ''),
                        'project_select_'.$project->id,
                        (string) $project->id
                    ))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $projectBlocks[] = [
            'type' => 'actions',
            'elements' => [$this->button('Annulla', 'cancel_draft', 'cancel')],
        ];

        $this->send($draft, $prefix, 'Scegli il progetto con un tap.', $projectBlocks);
    }

    private function askDuration(SlackTaskDraft $draft, ?string $prefix = null): void
    {
        $this->send($draft, $prefix, 'Quanto dura? Puoi anche scrivere `30m`, `1h` o `1h30`.', [[
            'type' => 'actions',
            'elements' => [
                $this->button('15m', 'duration_preset', '15'),
                $this->button('30m', 'duration_preset', '30'),
                $this->button('1h', 'duration_preset', '60'),
                $this->button('1h30', 'duration_preset', '90'),
                $this->button('2h', 'duration_preset', '120'),
            ],
        ]]);
    }

    private function askPriority(SlackTaskDraft $draft, ?string $prefix = null): void
    {
        $this->send($draft, $prefix, 'Priorità attività?', [[
            'type' => 'actions',
            'elements' => collect(range(1, 5))
                ->map(fn (int $priority) => $this->button((string) $priority, 'priority_select', (string) $priority))
                ->all(),
        ]]);
    }

    private function askDeadline(SlackTaskDraft $draft, ?string $prefix = null): void
    {
        $this->send($draft, $prefix, 'Ha una deadline specifica?', [[
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'datepicker',
                    'action_id' => 'deadline_picker',
                    'placeholder' => $this->plain('Deadline'),
                ],
                $this->button('Skip', 'skip_deadline', 'skip'),
            ],
        ]]);
    }

    private function askTaskType(SlackTaskDraft $draft, ?string $prefix = null): void
    {
        $this->send($draft, $prefix, 'È una task da pianificare automaticamente o un appuntamento fissato?', [[
            'type' => 'actions',
            'elements' => [
                $this->button('Automatica', 'task_type', 'auto'),
                $this->button('Appuntamento fissato', 'task_type', 'pinned'),
            ],
        ]]);
    }

    private function askPinnedDateTime(SlackTaskDraft $draft, ?string $prefix = null): void
    {
        $this->send($draft, $prefix, 'Scegli giorno e ora di inizio.', [[
            'type' => 'actions',
            'elements' => [[
                'type' => 'datetimepicker',
                'action_id' => 'pinned_datetime',
            ]],
        ]]);
    }

    private function askMaxPriority(SlackTaskDraft $draft, ?string $prefix = null): void
    {
        $this->send($draft, $prefix, 'Flag priorità massima?', [[
            'type' => 'actions',
            'elements' => [
                $this->button('Sì', 'max_priority', 'yes'),
                $this->button('No', 'max_priority', 'no'),
            ],
        ]]);
    }

    private function askConfirmation(SlackTaskDraft $draft): void
    {
        $this->send($draft, null, $this->summaryText($draft), [[
            'type' => 'actions',
            'elements' => [
                $this->button('Conferma', 'confirm_task', 'confirm', 'primary'),
                $this->button('Annulla', 'cancel_draft', 'cancel', 'danger'),
            ],
        ]]);
    }

    private function sendResumePrompt(SlackTaskDraft $draft): void
    {
        $this->send($draft, null, 'Hai già una bozza task aperta. Vuoi riprenderla o ricominciare?', [[
            'type' => 'actions',
            'elements' => [
                $this->button('Riprendi', 'draft_resume', 'resume', 'primary'),
                $this->button('Ricomincia', 'draft_restart', 'restart'),
                $this->button('Annulla', 'cancel_draft', 'cancel', 'danger'),
            ],
        ]]);
    }

    private function sendCurrentStep(SlackTaskDraft $draft, ?string $prefix = null): void
    {
        match ($draft->step) {
            self::STEP_TITLE => $this->askTitle($draft, $prefix),
            self::STEP_DESCRIPTION => $this->askDescription($draft, $prefix),
            self::STEP_PROJECT => $this->askProject($draft, $prefix),
            self::STEP_DURATION => $this->askDuration($draft, $prefix),
            self::STEP_PRIORITY => $this->askPriority($draft, $prefix),
            self::STEP_DEADLINE => $this->askDeadline($draft, $prefix),
            self::STEP_TASK_TYPE => $this->askTaskType($draft, $prefix),
            self::STEP_PINNED_START => $this->askPinnedDateTime($draft, $prefix),
            self::STEP_MAX_PRIORITY => $this->askMaxPriority($draft, $prefix),
            self::STEP_CONFIRM => $this->askConfirmation($draft),
            default => $this->askTitle($draft, $prefix),
        };
    }

    private function cancelDraft(SlackTaskDraft $draft): void
    {
        $this->expireDraft($draft);
        $this->slack->postMessage($draft->slack_channel_id, 'Bozza annullata.');
    }

    private function send(SlackTaskDraft $draft, ?string $prefix, string $message, array $extraBlocks = []): void
    {
        $text = trim(($prefix ? $prefix."\n\n" : '').$message);
        $blocks = [[
            'type' => 'section',
            'text' => ['type' => 'mrkdwn', 'text' => $text],
        ], ...$extraBlocks];

        $this->slack->postMessage($draft->slack_channel_id, $text, $blocks);
    }

    private function confirmationResultBlocks(Task $task): array
    {
        $block = $task->scheduledBlocks()->orderBy('start_at')->first();
        $details = "*{$task->title}*\nProgetto: {$task->project->name}";

        if ($block instanceof ScheduledBlock) {
            $details .= "\nPianificata: ".$block->start_at->format('d/m/Y H:i').' - '.$block->end_at->format('H:i');
        } else {
            $details .= "\nNon è entrata nelle fasce disponibili dell’orizzonte corrente.";
        }

        return [[
            'type' => 'section',
            'text' => ['type' => 'mrkdwn', 'text' => $details],
        ]];
    }

    private function summaryText(SlackTaskDraft $draft): string
    {
        $payload = $draft->payload ?? [];
        $project = Project::query()->find($payload['project_id'] ?? null);
        $deadline = $payload['deadline'] ?? 'nessuna';
        $type = ($payload['task_type'] ?? 'auto') === 'pinned'
            ? 'appuntamento fissato'
            : 'automatica';
        $start = isset($payload['pinned_start_at'])
            ? Carbon::parse($payload['pinned_start_at'])->format('d/m/Y H:i')
            : 'non fissato';
        $maxPriority = ($payload['is_max_priority'] ?? false) ? 'sì' : 'no';

        return implode("\n", array_filter([
            '*Riepilogo task*',
            'Titolo: '.($payload['title'] ?? '-'),
            'Descrizione: '.(($payload['description'] ?? null) ?: 'nessuna'),
            'Progetto: '.($project?->name ?? '-'),
            'Durata: '.($payload['duration_minutes'] ?? '-').' min',
            'Priorità: '.($payload['priority'] ?? '-'),
            'Deadline: '.$deadline,
            'Tipo: '.$type,
            ($payload['task_type'] ?? null) === 'pinned' ? 'Inizio fissato: '.$start : null,
            'Priorità massima: '.$maxPriority,
            '',
            'Confermi la creazione?',
        ]));
    }

    private function button(string $text, string $actionId, string $value, ?string $style = null): array
    {
        return array_filter([
            'type' => 'button',
            'text' => $this->plain($text),
            'action_id' => $actionId,
            'value' => $value,
            'style' => $style,
        ], fn ($item) => $item !== null);
    }

    private function plain(string $text): array
    {
        return [
            'type' => 'plain_text',
            'text' => $text,
            'emoji' => true,
        ];
    }

    private function activeDraft(string $userId, ?string $channelId = null): ?SlackTaskDraft
    {
        return SlackTaskDraft::query()
            ->where('slack_user_id', $userId)
            ->whereNull('completed_at')
            ->whereNull('task_id')
            ->where('expires_at', '>', now())
            ->when($channelId, fn ($query) => $query->where(function ($query) use ($channelId) {
                $query->where('slack_channel_id', $channelId)->orWhereNull('slack_channel_id');
            }))
            ->latest()
            ->first();
    }

    private function createDraft(string $userId, string $channelId): SlackTaskDraft
    {
        return SlackTaskDraft::create([
            'slack_user_id' => $userId,
            'slack_channel_id' => $channelId,
            'step' => self::STEP_TITLE,
            'payload' => [],
            'expires_at' => $this->expiry(),
        ]);
    }

    private function mergePayload(SlackTaskDraft $draft, array $payload, string $nextStep): void
    {
        $draft->update([
            'payload' => array_merge($draft->payload ?? [], $payload),
            'step' => $nextStep,
            'expires_at' => $this->expiry(),
        ]);
        $draft->refresh();
    }

    private function expireDraft(SlackTaskDraft $draft): void
    {
        $draft->update(['expires_at' => now()]);
    }

    private function expiry(): Carbon
    {
        return now()->addMinutes((int) config('services.slack.task_draft_ttl_minutes', 60));
    }

    private function parseDuration(string $value): ?int
    {
        $value = Str::of($value)->lower()->replace(' ', '')->toString();
        $minutes = null;

        if (preg_match('/^\d+$/', $value)) {
            $minutes = (int) $value;
        } elseif (preg_match('/^(\d+)m$/', $value, $matches)) {
            $minutes = (int) $matches[1];
        } elseif (preg_match('/^(\d+)h(?:(\d+)m?)?$/', $value, $matches)) {
            $minutes = ((int) $matches[1]) * 60 + (int) ($matches[2] ?? 0);
        }

        if ($minutes === null || $minutes < 15 || $minutes > 2400) {
            return null;
        }

        return $minutes;
    }

    private function isCancelCommand(string $text): bool
    {
        return in_array(Str::lower(trim($text)), ['annulla', 'cancel'], true);
    }

    private function isRestartCommand(string $text): bool
    {
        return in_array(Str::lower(trim($text)), ['ricomincia', 'restart'], true);
    }

    private function isSkipCommand(string $text): bool
    {
        return in_array(Str::lower(trim($text)), ['skip', 'salta'], true);
    }
}
