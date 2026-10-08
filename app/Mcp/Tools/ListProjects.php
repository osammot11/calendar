<?php

namespace App\Mcp\Tools;

use App\Services\CalendarMcpActions;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_projects')]
#[Description('Elenca i progetti del calendario con ID, nome, priorita e deadline. Usa questo strumento per trovare il project_id prima di creare una task.')]
#[IsReadOnly]
class ListProjects extends Tool
{
    public function handle(Request $request, CalendarMcpActions $calendar): ResponseFactory
    {
        return Response::structured($calendar->projects());
    }
}
