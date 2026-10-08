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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_task')]
#[Description('Modifica una task tramite ID. I campi omessi restano invariati. Usa list_tasks per trovare ID e valori correnti; gli orari sono locali Europe/Rome.')]
#[IsDestructive]
class UpdateTask extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return ['task_id' => $schema->integer()->required(), ...TaskFields::schema($schema, false)];
    }

    public function handle(Request $request, CalendarMcpActions $calendar): ResponseFactory
    {
        return Response::structured($calendar->updateTask($request->all()));
    }
}
