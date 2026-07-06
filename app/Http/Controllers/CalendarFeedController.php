<?php

namespace App\Http\Controllers;

use App\Services\CalendarFeedService;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    public function __invoke(string $token, CalendarFeedService $feed): Response
    {
        abort_unless($this->isValidToken($token), 404);

        return response($feed->generate(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendar-feed.ics"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function isValidToken(string $token): bool
    {
        $configuredToken = config('planner.calendar_feed_token');

        return is_string($configuredToken)
            && $configuredToken !== ''
            && hash_equals($configuredToken, $token);
    }
}
