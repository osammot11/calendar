import assert from "node:assert/strict";
import { before, test } from "node:test";
import { createLocalJWKSet, exportJWK, generateKeyPair, SignJWT } from "jose";
import { authChallenge, resourceMetadata, verifyAccessToken } from "./auth.js";

let privateKey;
let config;

before(async () => {
    const pair = await generateKeyPair("RS256");
    privateKey = pair.privateKey;
    const jwk = await exportJWK(pair.publicKey);
    config = {
        issuer: "https://login.example.test/",
        audience: "https://calendar.example.test/mcp",
        allowedSubject: "auth0|owner",
        jwks: createLocalJWKSet({ keys: [{ ...jwk, kid: "test-key", alg: "RS256" }] }),
    };
});

async function token(overrides = {}) {
    return new SignJWT({ scope: "calendar:read calendar:write", ...overrides })
        .setProtectedHeader({ alg: "RS256", kid: "test-key" })
        .setIssuer(config.issuer)
        .setAudience(config.audience)
        .setSubject(overrides.sub || config.allowedSubject)
        .setIssuedAt()
        .setExpirationTime("1h")
        .sign(privateKey);
}

test("accepts only the configured owner with both scopes", async () => {
    const valid = await verifyAccessToken(`Bearer ${await token()}`, config);
    assert.deepEqual(valid.scopes, ["calendar:read", "calendar:write"]);
    assert.equal(await verifyAccessToken(`Bearer ${await token({ sub: "auth0|other" })}`, config), null);
    assert.equal(await verifyAccessToken(`Bearer ${await token({ scope: "calendar:read" })}`, config), null);
    assert.equal(await verifyAccessToken(`Bearer ${await token()}`, { ...config, audience: "https://another.example.test/mcp" }), null);
    assert.equal(await verifyAccessToken("Bearer nonsense", config), null);
    assert.equal(await verifyAccessToken(null, config), null);
});

test("publishes OAuth resource metadata and challenge", () => {
    assert.equal(resourceMetadata(config).resource, config.audience);
    assert.deepEqual(resourceMetadata(config).authorization_servers, [config.issuer]);
    assert.match(authChallenge(config), /oauth-protected-resource\/mcp/);
});
