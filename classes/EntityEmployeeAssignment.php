<?php

/**
 * Assigns an arbitrary domain entity to one employee.
 *
 * Process status and workflow data deliberately remain on the domain entity.
 */
class EntityEmployeeAssignmentCore extends ObjectModel
{
    /** @var int */
    public $entity_type;

    /** @var int */
    public $id_entity;

    /** @var int */
    public $id_employee;

    /** @var string */
    public $date_add;

    /** @var string */
    public $date_upd;

    public static $definition = [
        'table' => 'entity_employee_assignment',
        'primary' => 'id_entity_employee_assignment',
        'fields' => [
            'entity_type' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_entity' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
        ],
        'keys' => [
            'entity_employee_assignment' => [
                'entity' => ['type' => ObjectModel::UNIQUE_KEY, 'columns' => ['entity_type', 'id_entity']],
                'id_employee' => ['type' => ObjectModel::KEY, 'columns' => ['id_employee']],
            ],
        ],
    ];

    public static function getEmployeeId(int $entityType, int $idEntity): int
    {
        if ($entityType <= 0 || $idEntity <= 0) {
            return 0;
        }

        return (int)Db::readOnly()->getValue(
            (new DbQuery())
                ->select('`id_employee`')
                ->from(static::$definition['table'])
                ->where('`entity_type` = '.(int)$entityType)
                ->where('`id_entity` = '.(int)$idEntity)
        );
    }

    /**
     * A missing row represents an unassigned entity.
     */
    public static function assign(int $entityType, int $idEntity, int $idEmployee): bool
    {
        if ($entityType <= 0 || $idEntity <= 0) {
            return false;
        }

        $db = Db::getInstance();
        if ($idEmployee <= 0) {
            return $db->delete(
                static::$definition['table'],
                '`entity_type` = '.(int)$entityType.' AND `id_entity` = '.(int)$idEntity
            );
        }

        $employee = new Employee($idEmployee);
        if (!Validate::isLoadedObject($employee) || !(bool)$employee->active) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        return $db->execute(
            'INSERT INTO `'._DB_PREFIX_.bqSQL(static::$definition['table']).'`'
            .' (`entity_type`, `id_entity`, `id_employee`, `date_add`, `date_upd`) VALUES ('
            .(int)$entityType.', '.(int)$idEntity.', '.(int)$idEmployee.', "'.pSQL($now).'", "'.pSQL($now).'")'
            .' ON DUPLICATE KEY UPDATE `id_employee` = VALUES(`id_employee`), `date_upd` = VALUES(`date_upd`)'
        );
    }
}
