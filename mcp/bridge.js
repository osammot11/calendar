export function createBridge(env = process.env) {
    const token = env.MCP_BRIDGE_TOKEN;
    const baseUrl = env.MCP_BRIDGE_URL || "http://127.0.0.1:3002/internal/mcp";

    if (!token || token.length < 32) {
        throw new Error("MCP_BRIDGE_TOKEN must contain at least 32 characters");
    }
    const url = new URL(baseUrl);
    if (url.protocol !== "http:" || !["127.0.0.1", "localhost"].includes(url.hostname) || url.pathname !== "/internal/mcp") {
        throw new Error("MCP_BRIDGE_URL must point to the local Laravel bridge");
    }

    return async function bridge(path, { method = "GET", body } = {}) {
        const response = await fetch(`${baseUrl}${path}`, {
            method,
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: "application/json",
                ...(body ? { "Content-Type": "application/json" } : {}),
            },
            redirect: "error",
            ...(body ? { body: JSON.stringify(body) } : {}),
            signal: AbortSignal.timeout(15000),
        });

        const result = await response.json().catch(() => null);
        if (!response.ok) {
            const message = response.status === 422
                ? Object.values(result?.errors || {}).flat().join(" ") || result?.message
                : `Laravel ha risposto con HTTP ${response.status}`;
            throw new Error(message || "Operazione non riuscita");
        }

        return result;
    };
}
