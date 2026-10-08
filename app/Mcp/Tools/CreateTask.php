<?php

namespace App\Mcp\Tools;

use App\Services\CalendarMcpActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_task')]
#[Description('Crea e pianifica una task nel progetto indicato. Per un appuntamento fissato imposta is_pinned=true e pinned_start_at. Chiedi titolo e progetto se mancanti.')]
class CreateTask extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return TaskFields::schema($schema, true);
    }

    public function handle(Request $request, CalendarMcpActions $calendar): ResponseFactory
    {
        return Response::structured($calendar->createTask($request->all()));
    }
}
