<?php

namespace Thirtybees\Core\Mcp;

final class McpAuthentication
{
    public const CONFIGURATION_ENABLED = 'TB_MCP_ENABLED';
    public const CONFIGURATION_AUDIT_RETENTION_DAYS = 'TB_MCP_AUDIT_RETENTION_DAYS';

    public function authenticate(?string $authorizationHeader): ?McpIdentity
    {
        if (!(bool) \Configuration::get(static::CONFIGURATION_ENABLED)) {
            return null;
        }

        if (!preg_match('/^Bearer\s+([^\s]+)$/i', trim((string) $authorizationHeader), $matches)) {
            return null;
        }

        if (!\McpToken::storageExists()) {
            return null;
        }

        $token = \McpToken::findActiveByPlaintext($matches[1]);
        if ($token === null) {
            return null;
        }
        try {
            $token->markUsed();
        } catch (\Throwable $e) {
            // Authentication must not fail only because the usage timestamp could not be updated.
        }

        return new McpIdentity((int) $token->id_employee, (int) $token->id);
    }
}
