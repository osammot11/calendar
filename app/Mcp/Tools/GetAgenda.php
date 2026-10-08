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

#[Name('get_agenda')]
#[Description('Legge eventi pianificati e impegni occupati in un intervallo massimo di 31 giorni. Le date sono nel fuso Europe/Rome.')]
#[IsReadOnly]
class GetAgenda extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Data iniziale YYYY-MM-DD.')->required(),
            'to' => $schema->string()->description('Data finale YYYY-MM-DD.')->required(),
        ];
    }

    public function handle(Request $request, CalendarMcpActions $calendar): ResponseFactory
    {
        return Response::structured($calendar->agenda($request->all()));
    }
}
