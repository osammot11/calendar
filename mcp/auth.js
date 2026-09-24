import { createRemoteJWKSet, jwtVerify } from "jose";

export function authConfig(env = process.env) {
    const issuer = env.MCP_OAUTH_ISSUER;
    const audience = env.MCP_PUBLIC_URL;
    const allowedSubject = env.MCP_ALLOWED_SUB;

    if (!issuer?.startsWith("https://") || !audience?.startsWith("https://") || !allowedSubject) {
        throw new Error("MCP_OAUTH_ISSUER, MCP_PUBLIC_URL and MCP_ALLOWED_SUB are required");
    }

    return {
        issuer,
        audience,
        allowedSubject,
        jwks: createRemoteJWKSet(new URL(".well-known/jwks.json", issuer)),
    };
}

export async function verifyAccessToken(header, config) {
    const match = /^Bearer (\S+)$/i.exec(header || "");
    if (!match) return null;

    try {
        const { payload } = await jwtVerify(match[1], config.jwks, {
            issuer: config.issuer,
            audience: config.audience,
            algorithms: ["RS256"],
        });
        const scopes = typeof payload.scope === "string" ? payload.scope.split(/\s+/) : [];

        if (payload.sub !== config.allowedSubject || !scopes.includes("calendar:read") || !scopes.includes("calendar:write")) {
            return null;
        }

        return {
            token: match[1],
            clientId: String(payload.azp || payload.client_id || "chatgpt"),
            scopes,
            expiresAt: payload.exp,
        };
    } catch {
        return null;
    }
}

export function resourceMetadata(config) {
    return {
        resource: config.audience,
        authorization_servers: [config.issuer],
        scopes_supported: ["calendar:read", "calendar:write"],
    };
}

export function authChallenge(config) {
    const metadataUrl = new URL("/.well-known/oauth-protected-resource/mcp", config.audience);
    return `Bearer resource_metadata="${metadataUrl}", scope="calendar:read calendar:write"`;
}
