import assert from "node:assert/strict";
import { test } from "node:test";
import { createMcpHandler } from "@modelcontextprotocol/server";
import { createCalendarServer } from "./tools.js";

async function call(handler, method, params = {}) {
    const response = await handler.fetch(new Request("http://localhost/mcp", {
        method: "POST",
        headers: { Accept: "application/json, text/event-stream", "Content-Type": "application/json" },
        body: JSON.stringify({ jsonrpc: "2.0", id: 1, method, params }),
    }));
    assert.equal(response.status, 200);
    const body = await response.text();
    return JSON.parse(body.startsWith("event:") ? body.split("data: ")[1] : body);
}

test("advertises only the intended tools and forwards validated writes", async () => {
    const seen = [];
    const handler = createMcpHandler(() => createCalendarServer(async (path, options) => {
        seen.push({ path, options });
        return { task: { id: 7, title: options?.body?.title } };
    }));

    try {
        const listed = await call(handler, "tools/list");
        assert.deepEqual(listed.result.tools.map((tool) => tool.name), [
            "list_projects", "list_tasks", "get_agenda", "create_task", "update_task", "complete_task",
        ]);
        assert.equal(listed.result.tools.find((tool) => tool.name === "get_agenda").annotations.readOnlyHint, true);

        const created = await call(handler, "tools/call", {
            name: "create_task",
            arguments: { title: "Offerta", project_id: 2, duration_minutes: 45 },
        });
        assert.equal(created.result.structuredContent.task.id, 7);
        assert.deepEqual(seen[0], {
            path: "/tasks",
            options: { method: "POST", body: { title: "Offerta", project_id: 2, duration_minutes: 45 } },
        });

        const invalid = await call(handler, "tools/call", {
            name: "create_task",
            arguments: { title: "", project_id: 2 },
        });
        assert.ok(invalid.error || invalid.result?.isError);
        assert.equal(seen.length, 1);
    } finally {
        await handler.close();
    }
});
