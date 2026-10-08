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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_tasks')]
#[Description('Elenca fino a 100 task, con stato, progetto e slot pianificato. Per default mostra le task aperte; filtra per progetto o stato se necessario.')]
#[IsReadOnly]
class ListTasks extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['open', 'done', 'all']),
            'project_id' => $schema->integer(),
        ];
    }

    public function handle(Request $request, CalendarMcpActions $calendar): ResponseFactory
    {
        return Response::structured($calendar->tasks($request->all()));
    }
}
