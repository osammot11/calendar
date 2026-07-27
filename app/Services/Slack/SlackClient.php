<?php

namespace App\Services\Slack;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SlackClient
{
    public function openDirectMessage(string $userId): string
    {
        $response = $this->post('conversations.open', [
            'users' => $userId,
            'return_im' => true,
        ]);

        return (string) data_get($response, 'channel.id');
    }

    public function postMessage(string $channel, string $text, array $blocks = []): array
    {
        return $this->post('chat.postMessage', array_filter([
            'channel' => $channel,
            'text' => $text,
            'blocks' => $blocks === [] ? null : $blocks,
        ], fn ($value) => $value !== null));
    }

    private function post(string $method, array $payload): array
    {
        $token = (string) config('services.slack.bot_token');
        if ($token === '') {
            throw new RuntimeException('Slack bot token non configurato.');
        }

        $response = Http::withToken($token)
            ->asJson()
            ->post("https://slack.com/api/{$method}", $payload)
            ->throw()
            ->json();

        if (! data_get($response, 'ok')) {
            throw new RuntimeException('Errore Slack API: '.(data_get($response, 'error') ?: 'unknown_error'));
        }

        return $response;
    }
}
