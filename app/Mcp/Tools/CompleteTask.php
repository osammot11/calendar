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

#[Name('complete_task')]
#[Description('Segna una task come completata tramite ID. Conserva lo slot passato e attribuisce le ore di analytics al giorno pianificato.')]
class CompleteTask extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return ['task_id' => $schema->integer()->required()];
    }

    public function handle(Request $request, CalendarMcpActions $calendar): ResponseFactory
    {
        $input = $request->validate(['task_id' => ['required', 'integer', 'exists:tasks,id']]);

        return Response::structured($calendar->completeTask($input['task_id']));
    }
}
