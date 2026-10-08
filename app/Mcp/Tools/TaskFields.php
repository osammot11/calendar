<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class TaskFields
{
    public static function schema(JsonSchema $schema, bool $required): array
    {
        return [
            'title' => $schema->string()->description('Titolo della task.')->required($required),
            'project_id' => $schema->integer()->description('ID di un progetto esistente.')->required($required),
            'description' => $schema->string()->nullable(),
            'duration_minutes' => $schema->integer()->description('Durata in minuti, multiplo di 5 da 5 a 2400.'),
            'priority' => $schema->integer()->description('Priorita task da 1 a 5.'),
            'deadline' => $schema->string()->nullable()->description('Data YYYY-MM-DD.'),
            'is_max_priority' => $schema->boolean(),
            'is_pinned' => $schema->boolean()->description('True per appuntamento fissato.'),
            'pinned_start_at' => $schema->string()->nullable()->description('Data e ora locale Europe/Rome nel formato YYYY-MM-DD HH:mm.'),
            'earliest_start_time' => $schema->string()->nullable()->description('Ora HH:mm; solo task automatica.'),
            'latest_end_time' => $schema->string()->nullable()->description('Ora HH:mm; solo task automatica.'),
        ];
    }
}
