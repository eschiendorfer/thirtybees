<?php

/**
 * Employee-bound access token for the MCP endpoint.
 */
class McpTokenCore extends ObjectModel
{
    public $id_mcp_token;
    public $id_employee;
    public $name;
    public $token_prefix;
    public $token_hash;
    public $active = true;
    public $last_used_at;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'mcp_token',
        'primary' => 'id_mcp_token',
        'primaryKeyDbType' => 'int(11) unsigned',
        'fields' => [
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true, 'dbType' => 'int(11) unsigned'],
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 100],
            'token_prefix' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 16],
            'token_hash' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 64],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'dbType' => 'tinyint(1)', 'dbNullable' => false],
            'last_used_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
        ],
        'keys' => [
            'mcp_token' => [
                'token_hash' => ['type' => ObjectModel::UNIQUE_KEY, 'columns' => ['token_hash']],
                'id_employee' => ['type' => ObjectModel::KEY, 'columns' => ['id_employee']],
                'active' => ['type' => ObjectModel::KEY, 'columns' => ['active']],
            ],
        ],
    ];

    /**
     * @return array{token: static, plaintext: string}
     *
     * @throws Exception
     * @throws PrestaShopException
     */
    public static function createForEmployee(int $idEmployee, string $name): array
    {
        $employee = new Employee($idEmployee);
        if (!Validate::isLoadedObject($employee) || !$employee->active) {
            throw new InvalidArgumentException('Please select an active employee.');
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100 || !Validate::isGenericName($name)) {
            throw new InvalidArgumentException('Please enter a valid token name with at most 100 characters.');
        }

        $plaintext = 'tbmcp_'.bin2hex(random_bytes(32));
        $token = new static();
        $token->id_employee = $idEmployee;
        $token->name = $name;
        $token->token_prefix = substr($plaintext, 0, 14);
        $token->token_hash = hash('sha256', $plaintext);
        $token->active = true;

        if (!$token->add()) {
            throw new RuntimeException('The MCP token could not be saved.');
        }

        return ['token' => $token, 'plaintext' => $plaintext];
    }

    /**
     * @throws PrestaShopException
     */
    public static function findActiveByPlaintext(string $plaintext): ?self
    {
        $idToken = (int) Db::readOnly()->getValue(
            (new DbQuery())
                ->select('mt.`id_mcp_token`')
                ->from('mcp_token', 'mt')
                ->innerJoin('employee', 'e', 'e.`id_employee` = mt.`id_employee` AND e.`active` = 1')
                ->where('mt.`active` = 1')
                ->where("mt.`token_hash` = '".pSQL(hash('sha256', $plaintext))."'")
        );

        if ($idToken <= 0) {
            return null;
        }

        $token = new static($idToken);

        return Validate::isLoadedObject($token) ? $token : null;
    }

    public static function storageExists(): bool
    {
        try {
            return (bool) Db::readOnly()->getValue(
                "SELECT 1 FROM `information_schema`.`TABLES`"
                ." WHERE `TABLE_SCHEMA` = DATABASE()"
                ." AND `TABLE_NAME` = '".pSQL(_DB_PREFIX_.static::$definition['table'])."'"
            );
        } catch (Throwable $e) {
            return false;
        }
    }

    public function markUsed(): void
    {
        $lastUsed = $this->last_used_at ? strtotime((string) $this->last_used_at) : false;
        if ($lastUsed !== false && $lastUsed >= time() - 60) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        if (Db::getInstance()->update(
            static::$definition['table'],
            ['last_used_at' => $now],
            '`id_mcp_token` = '.(int) $this->id
        )) {
            $this->last_used_at = $now;
        }
    }

    public function revoke(): bool
    {
        if (!$this->active) {
            return true;
        }

        $this->active = false;

        return $this->update();
    }
}
