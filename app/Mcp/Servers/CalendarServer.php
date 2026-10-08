<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CompleteTask;
use App\Mcp\Tools\CreateTask;
use App\Mcp\Tools\GetAgenda;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ListTasks;
use App\Mcp\Tools\UpdateTask;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Calendario lavorativo')]
#[Version('1.0.0')]
#[Instructions('Calendario personale in fuso Europe/Rome. Prima di creare o modificare task, usa list_projects per conoscere gli ID dei progetti. Gli appuntamenti fissati usano is_pinned e pinned_start_at. Chiedi i dati mancanti prima delle scritture.')]
class CalendarServer extends Server
{
    protected array $tools = [
        ListProjects::class,
        ListTasks::class,
        GetAgenda::class,
        CreateTask::class,
        UpdateTask::class,
        CompleteTask::class,
    ];
}
