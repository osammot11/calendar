<?php

namespace App\Mcp\Tools;

use App\Services\CalendarMcpActions;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('recalculate_planning')]
#[Description('Ricalcola l\'intera pianificazione come il pulsante "Ricalcola pianificazione" dell\'app. Puo spostare le task future; usa solo quando l\'utente lo chiede esplicitamente.')]
#[IsDestructive]
class RecalculatePlanning extends Tool
{
    public function handle(Request $request, CalendarMcpActions $calendar): ResponseFactory
    {
        return Response::structured($calendar->recalculatePlanning());
    }
}
