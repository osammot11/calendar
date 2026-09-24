import { McpServer } from "@modelcontextprotocol/server";
import * as z from "zod/v4";

const date = z.string().regex(/^\d{4}-\d{2}-\d{2}$/);
const time = z.string().regex(/^([01]\d|2[0-3]):[0-5]\d$/);
const localDateTime = z.string().regex(/^\d{4}-\d{2}-\d{2}T([01]\d|2[0-3]):[0-5]\d$/);
const taskFields = {
    title: z.string().trim().min(1).max(255),
    project_id: z.number().int().positive(),
    description: z.string().nullable(),
    duration_minutes: z.number().int().min(5).max(2400).multipleOf(5),
    priority: z.number().int().min(1).max(5),
    deadline: date.nullable(),
    is_max_priority: z.boolean(),
    is_pinned: z.boolean(),
    pinned_start_at: localDateTime.nullable(),
    earliest_start_time: time.nullable(),
    latest_end_time: time.nullable(),
};

function result(data) {
    return {
        content: [{ type: "text", text: JSON.stringify(data) }],
        structuredContent: data,
    };
}

function tool(server, name, description, schema, annotations, run) {
    server.registerTool(name, {
        title: name.replaceAll("_", " "),
        description,
        inputSchema: schema,
        annotations: { openWorldHint: false, ...annotations },
    }, async (input) => {
        try {
            return result(await run(input));
        } catch (error) {
            return { isError: true, content: [{ type: "text", text: error.message }] };
        }
    });
}

export function createCalendarServer(bridge) {
    const server = new McpServer({ name: "calendario-lavoro", version: "1.0.0" });

    tool(server, "list_projects", "Elenca i progetti del calendario personale con ID, priorita e deadline.", z.object({}),
        { readOnlyHint: true, destructiveHint: false },
        () => bridge("/projects"));

    tool(server, "list_tasks", "Elenca fino a 100 task. Per default mostra le task aperte; puoi filtrare per progetto e stato.",
        z.object({ status: z.enum(["open", "done", "all"]).optional(), project_id: z.number().int().positive().optional() }),
        { readOnlyHint: true, destructiveHint: false },
        ({ status, project_id }) => {
            const query = new URLSearchParams();
            if (status) query.set("status", status);
            if (project_id) query.set("project_id", String(project_id));
            return bridge(`/tasks?${query}`);
        });

    tool(server, "get_agenda", "Legge gli eventi pianificati e i blocchi occupati in un intervallo massimo di 31 giorni. Date e orari sono nel fuso Europe/Rome.",
        z.object({ from: date, to: date }),
        { readOnlyHint: true, destructiveHint: false },
        ({ from, to }) => bridge(`/agenda?${new URLSearchParams({ from, to })}`));

    tool(server, "create_task", "Crea una task nel progetto indicato e la pianifica. Per un appuntamento fissato imposta is_pinned=true e pinned_start_at come data e ora locale Europe/Rome (YYYY-MM-DDTHH:mm). Chiedi i dati mancanti prima di chiamare lo strumento.",
        z.object({
            title: taskFields.title,
            project_id: taskFields.project_id,
            description: taskFields.description.optional(),
            duration_minutes: taskFields.duration_minutes.optional(),
            priority: taskFields.priority.optional(),
            deadline: taskFields.deadline.optional(),
            is_max_priority: taskFields.is_max_priority.optional(),
            is_pinned: taskFields.is_pinned.optional(),
            pinned_start_at: taskFields.pinned_start_at.optional(),
            earliest_start_time: taskFields.earliest_start_time.optional(),
            latest_end_time: taskFields.latest_end_time.optional(),
        }),
        { readOnlyHint: false, destructiveHint: false },
        (input) => bridge("/tasks", { method: "POST", body: input }));

    tool(server, "update_task", "Modifica una task esistente usando il suo ID. I campi non indicati restano invariati. Gli orari di appuntamenti fissati sono locali Europe/Rome.",
        z.object({ task_id: z.number().int().positive(), ...Object.fromEntries(Object.entries(taskFields).map(([key, value]) => [key, value.optional()])) }),
        { readOnlyHint: false, destructiveHint: true },
        ({ task_id, ...changes }) => bridge(`/tasks/${task_id}`, { method: "PATCH", body: changes }));

    tool(server, "complete_task", "Segna come completata una task identificata dal suo ID. Non elimina la cronologia degli eventi passati.",
        z.object({ task_id: z.number().int().positive() }),
        { readOnlyHint: false, destructiveHint: false },
        ({ task_id }) => bridge(`/tasks/${task_id}/complete`, { method: "POST" }));

    return server;
}
