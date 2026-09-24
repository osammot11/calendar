import { createServer } from "node:http";
import { createMcpHandler } from "@modelcontextprotocol/server";
import { toNodeHandler } from "@modelcontextprotocol/node";
import { authChallenge, authConfig, resourceMetadata, verifyAccessToken } from "./auth.js";
import { createBridge } from "./bridge.js";
import { createCalendarServer } from "./tools.js";

const config = authConfig();
const bridge = createBridge();
const handler = createMcpHandler(() => createCalendarServer(bridge), {
    maxRequestBodySize: 1024 * 1024,
    onerror: (error) => console.error("MCP request failed:", error),
});
const nodeHandler = toNodeHandler(handler, {
    maxRequestBodySize: 1024 * 1024,
    onerror: (error) => console.error("MCP adapter failed:", error),
});

const server = createServer(async (request, response) => {
    const url = new URL(request.url, "http://127.0.0.1");

    if (request.method === "GET" && ["/.well-known/oauth-protected-resource", "/.well-known/oauth-protected-resource/mcp"].includes(url.pathname)) {
        response.writeHead(200, { "Content-Type": "application/json", "Cache-Control": "no-store" });
        response.end(JSON.stringify(resourceMetadata(config)));
        return;
    }

    if (url.pathname !== "/mcp") {
        response.writeHead(404);
        response.end();
        return;
    }

    const auth = await verifyAccessToken(request.headers.authorization, config);
    if (!auth) {
        response.writeHead(401, {
            "Content-Type": "application/json",
            "WWW-Authenticate": authChallenge(config),
            "Cache-Control": "no-store",
        });
        response.end(JSON.stringify({ error: "unauthorized" }));
        return;
    }

    request.auth = auth;
    await nodeHandler(request, response);
});

server.listen(Number(process.env.MCP_PORT || 3001), "127.0.0.1", () => {
    console.log(`Calendar MCP listening on 127.0.0.1:${server.address().port}`);
});

function shutdown() {
    server.close();
    handler.close();
}

process.on("SIGINT", shutdown);
process.on("SIGTERM", shutdown);
