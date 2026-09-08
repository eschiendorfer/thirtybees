<?php

/**
 * Minimal audit record for an authenticated MCP tool call.
 */
class McpAuditLogCore extends ObjectModel
{
    public $id_mcp_audit_log;
    public $id_mcp_token;
    public $id_employee;
    public $tool_name;
    public $success;
    public $duration_ms;
    public $result_count;
    public $date_add;

    public static $definition = [
        'table' => 'mcp_audit_log',
        'primary' => 'id_mcp_audit_log',
        'primaryKeyDbType' => 'bigint(20) unsigned',
        'fields' => [
            'id_mcp_token' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbType' => 'int(11) unsigned', 'dbNullable' => true],
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true, 'dbType' => 'int(11) unsigned'],
            'tool_name' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 64],
            'success' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'dbType' => 'tinyint(1)', 'dbNullable' => false],
            'duration_ms' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbType' => 'int(11) unsigned'],
            'result_count' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbType' => 'int(11) unsigned', 'dbNullable' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
        ],
        'keys' => [
            'mcp_audit_log' => [
                'id_mcp_token' => ['type' => ObjectModel::KEY, 'columns' => ['id_mcp_token']],
                'id_employee' => ['type' => ObjectModel::KEY, 'columns' => ['id_employee']],
                'date_add' => ['type' => ObjectModel::KEY, 'columns' => ['date_add']],
            ],
        ],
    ];

    public static function record(
        int $idToken,
        int $idEmployee,
        string $toolName,
        bool $success,
        int $durationMs,
        ?int $resultCount
    ): void {
        try {
            $log = new static();
            $log->id_mcp_token = $idToken > 0 ? $idToken : null;
            $log->id_employee = $idEmployee;
            $log->tool_name = mb_substr($toolName, 0, 64);
            $log->success = $success;
            $log->duration_ms = max(0, $durationMs);
            $log->result_count = $resultCount !== null ? max(0, $resultCount) : null;
            $log->add(true, true);
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('MCP audit log could not be written', 2);
        }
    }

    public static function purgeOlderThan(int $days): void
    {
        $days = max(1, min(3650, $days));
        Db::getInstance()->delete(
            static::$definition['table'],
            '`date_add` < DATE_SUB(NOW(), INTERVAL '.(int) $days.' DAY)'
        );
    }
}
