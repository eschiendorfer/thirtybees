<?php

namespace Thirtybees\Core\Mcp;

use InvalidArgumentException;

final class McpToolRegistry
{
    /**
     * @var array<string, array{handler: callable, description: string, annotations: array<string, bool|string>}>
     */
    private array $tools = [];

    public function register(string $name, string $description, callable $handler, array $annotations = []): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name)) {
            throw new InvalidArgumentException('Invalid MCP tool name: '.$name);
        }

        if (isset($this->tools[$name])) {
            throw new InvalidArgumentException('MCP tool already registered: '.$name);
        }

        $this->tools[$name] = [
            'handler' => $handler,
            'description' => $description,
            'annotations' => $annotations,
        ];
    }

    /**
     * @return array<string, array{handler: callable, description: string, annotations: array<string, bool|string>}>
     */
    public function all(): array
    {
        return $this->tools;
    }
}
