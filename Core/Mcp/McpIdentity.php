<?php

namespace Thirtybees\Core\Mcp;

final class McpIdentity
{
    public function __construct(
        public readonly int $idEmployee,
        public readonly int $idToken
    ) {
    }
}
