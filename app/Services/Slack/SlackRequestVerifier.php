<?php

namespace App\Services\Slack;

use Illuminate\Http\Request;

class SlackRequestVerifier
{
    private const VERSION = 'v0';
    private const MAX_DRIFT_SECONDS = 300;

    public function verify(Request $request): bool
    {
        $secret = (string) config('services.slack.signing_secret');
        if ($secret === '') {
            return false;
        }

        $timestamp = (int) $request->header('X-Slack-Request-Timestamp', 0);
        if ($timestamp === 0 || abs(now()->timestamp - $timestamp) > self::MAX_DRIFT_SECONDS) {
            return false;
        }

        $signature = (string) $request->header('X-Slack-Signature', '');
        $base = self::VERSION.':'.$timestamp.':'.$request->getContent();
        $expected = self::VERSION.'='.hash_hmac('sha256', $base, $secret);

        return hash_equals($expected, $signature);
    }
}
