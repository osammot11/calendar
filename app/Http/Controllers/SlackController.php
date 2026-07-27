<?php

namespace App\Http\Controllers;

use App\Services\Slack\SlackRequestVerifier;
use App\Services\Slack\SlackTaskWizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SlackController extends Controller
{
    public function __construct(
        private readonly SlackRequestVerifier $verifier,
        private readonly SlackTaskWizardService $wizard,
    ) {
    }

    public function command(Request $request): JsonResponse
    {
        if (! $this->verifier->verify($request)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $userId = (string) $request->input('user_id');
        if (! $this->isAllowedUser($userId)) {
            return $this->ephemeral('Non sei autorizzato a creare task in questo calendario.');
        }

        try {
            $this->wizard->startFromCommand($userId, (string) $request->input('text', ''));
        } catch (Throwable $exception) {
            Log::error('Slack task command failed', ['exception' => $exception]);

            return $this->ephemeral('Non sono riuscito ad avviare il wizard. Controlla configurazione Slack e log Laravel.');
        }

        return $this->ephemeral('Perfetto, ti ho scritto in DM per creare la task.');
    }

    public function events(Request $request): JsonResponse|Response
    {
        if (! $this->verifier->verify($request)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        if ($request->input('type') === 'url_verification') {
            return response((string) $request->input('challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        $eventId = (string) $request->input('event_id');
        if ($eventId !== '' && ! Cache::add('slack:event:'.$eventId, true, now()->addHours(6))) {
            return response()->json(['ok' => true]);
        }

        $event = (array) $request->input('event', []);
        $userId = (string) data_get($event, 'user');
        $channelId = (string) data_get($event, 'channel');
        $text = (string) data_get($event, 'text', '');

        if (
            data_get($event, 'type') === 'message'
            && data_get($event, 'channel_type') === 'im'
            && ! data_get($event, 'subtype')
            && $this->isAllowedUser($userId)
        ) {
            try {
                $this->wizard->handleMessage($userId, $channelId, $text);
            } catch (Throwable $exception) {
                Log::error('Slack task event failed', ['exception' => $exception]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function interactions(Request $request): JsonResponse
    {
        if (! $this->verifier->verify($request)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $payload = json_decode((string) $request->input('payload', ''), true) ?: [];
        $userId = (string) data_get($payload, 'user.id');
        if (! $this->isAllowedUser($userId)) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => 'Non sei autorizzato a usare questo calendario.',
            ]);
        }

        try {
            $this->wizard->handleInteraction($payload);
        } catch (Throwable $exception) {
            Log::error('Slack task interaction failed', ['exception' => $exception]);
        }

        return response()->json(['ok' => true]);
    }

    private function isAllowedUser(string $userId): bool
    {
        $allowed = config('services.slack.allowed_user_ids', []);

        return $userId !== '' && in_array($userId, $allowed, true);
    }

    private function ephemeral(string $text): JsonResponse
    {
        return response()->json([
            'response_type' => 'ephemeral',
            'text' => $text,
        ]);
    }
}
