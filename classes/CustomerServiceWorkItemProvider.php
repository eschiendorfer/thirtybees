<?php

/**
 * Read model for the shared customer-service work queue.
 *
 * It deliberately derives work items from their domain entities. No second
 * workflow state is persisted here.
 */
class CustomerServiceWorkItemProviderCore
{
    public const STATUS_OPEN = 'open';
    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';
    public const STATUS_INQUIRY = 'inquiry';
    public const STATUS_CLOSED = 'closed';
    private const RANK_CLOSED = 1;
    private const RANK_INQUIRY = 2;
    private const RANK_WAITING_CUSTOMER = 3;
    public const RANK_OPEN = 4;

    /** @var array<string, bool> */
    private $tableExists = [];

    /** @return int[] */
    public static function getSupportedEntityTypes(): array
    {
        return [
            (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE,
            (int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE,
            (int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE,
            (int)\CoreExtension\EntityTypeEnum::PRODUCT_WISH_VALUE,
            (int)\CoreExtension\EntityTypeEnum::CUSTOMER_THREAD_VALUE,
        ];
    }

    public static function isSupportedEntityType(int $entityType): bool
    {
        return in_array($entityType, static::getSupportedEntityTypes(), true);
    }

    public function entityExists(int $entityType, int $idEntity): bool
    {
        if ($idEntity <= 0 || !static::isSupportedEntityType($entityType)) {
            return false;
        }

        $tables = [
            (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE => ['order_cancellation', 'id_order_cancellation'],
            (int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE => ['order_return', 'id_order_return'],
            (int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE => ['order_service_case', 'id_order_service_case'],
            (int)\CoreExtension\EntityTypeEnum::PRODUCT_WISH_VALUE => ['genzo_crm_product_wish', 'id_product_wish'],
            (int)\CoreExtension\EntityTypeEnum::CUSTOMER_THREAD_VALUE => ['customer_thread', 'id_customer_thread'],
        ];
        [$table, $primary] = $tables[$entityType];
        if (!$this->hasTable($table)) {
            return false;
        }

        return (bool)Db::readOnly()->getValue(
            'SELECT 1 FROM `'._DB_PREFIX_.bqSQL($table).'` WHERE `'.bqSQL($primary).'` = '.(int)$idEntity
        );
    }

    /**
     * SQL selecting one row per domain case or free conversation.
     */
    public function getBaseSql(array $shopIds): string
    {
        $unions = [];
        $threadAggregate = $this->getThreadAggregateSql();

        if ($this->hasTable('order_cancellation')) {
            $cancellationType = (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE;
            $entityRank = 'CASE'
                .' WHEN oc.`status` = "'.pSQL(OrderCancellation::STATUS_DONE).'" THEN '.self::RANK_CLOSED
                .' ELSE '.self::RANK_OPEN.' END';
            $unions[] = 'SELECT '.$cancellationType.' AS `entity_type`, oc.`id_order_cancellation` AS `id_entity`,'
                .' o.`id_shop`, o.`id_customer`, COALESCE(NULLIF(TRIM(CONCAT(c.`firstname`, " ", c.`lastname`)), ""), ta.`thread_email`, "") AS `customer`,'
                .' COALESCE(NULLIF(c.`email`, ""), ta.`thread_email`, "") AS `email`,'
                .' '.$entityRank.' AS `entity_status_rank`, COALESCE(ta.`thread_status_rank`, 0) AS `thread_status_rank`,'
                .' ta.`id_customer_thread`, oc.`date_upd` AS `entity_date_upd`, ta.`thread_date_upd`'
                .' FROM `'._DB_PREFIX_.'order_cancellation` oc'
                .' INNER JOIN `'._DB_PREFIX_.'orders` o ON o.`id_order` = oc.`id_order`'
                .' LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.`id_customer` = o.`id_customer`'
                .' LEFT JOIN ('.$threadAggregate.') ta ON ta.`entity_type` = '.$cancellationType.' AND ta.`id_entity` = oc.`id_order_cancellation`';
        }

        if ($this->hasTable('order_return')) {
            $returnType = (int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE;
            $entityRank = 'CASE oret.`state`'
                .' WHEN '.(int)OrderReturn::STATE_WAITING_FOR_PACKAGE.' THEN '.self::RANK_WAITING_CUSTOMER
                .' WHEN '.(int)OrderReturn::STATE_RETURN_DENIED.' THEN '.self::RANK_CLOSED
                .' WHEN '.(int)OrderReturn::STATE_RETURN_COMPLETED.' THEN '.self::RANK_CLOSED
                .' WHEN '.(int)OrderReturn::STATE_RETURN_EXPIRED.' THEN '.self::RANK_CLOSED
                .' ELSE '.self::RANK_OPEN.' END';
            $unions[] = 'SELECT '.$returnType.' AS `entity_type`, oret.`id_order_return` AS `id_entity`,'
                .' o.`id_shop`, oret.`id_customer`, COALESCE(NULLIF(TRIM(CONCAT(c.`firstname`, " ", c.`lastname`)), ""), ta.`thread_email`, "") AS `customer`,'
                .' COALESCE(NULLIF(c.`email`, ""), ta.`thread_email`, "") AS `email`,'
                .' '.$entityRank.' AS `entity_status_rank`, COALESCE(ta.`thread_status_rank`, 0) AS `thread_status_rank`,'
                .' ta.`id_customer_thread`, oret.`date_upd` AS `entity_date_upd`, ta.`thread_date_upd`'
                .' FROM `'._DB_PREFIX_.'order_return` oret'
                .' INNER JOIN `'._DB_PREFIX_.'orders` o ON o.`id_order` = oret.`id_order`'
                .' LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.`id_customer` = oret.`id_customer`'
                .' LEFT JOIN ('.$threadAggregate.') ta ON ta.`entity_type` = '.$returnType.' AND ta.`id_entity` = oret.`id_order_return`';
        }

        if ($this->hasTable('order_service_case')) {
            $serviceCaseType = (int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE;
            $entityRank = 'CASE osc.`status`'
                .' WHEN "'.pSQL(OrderServiceCase::STATUS_WAITING).'" THEN '.self::RANK_INQUIRY
                .' WHEN "'.pSQL(OrderServiceCase::STATUS_RESOLVED).'" THEN '.self::RANK_CLOSED
                .' WHEN "'.pSQL(OrderServiceCase::STATUS_REJECTED).'" THEN '.self::RANK_CLOSED
                .' WHEN "'.pSQL(OrderServiceCase::STATUS_EXPIRED).'" THEN '.self::RANK_CLOSED
                .' ELSE '.self::RANK_OPEN.' END';
            $unions[] = 'SELECT '.$serviceCaseType.' AS `entity_type`, osc.`id_order_service_case` AS `id_entity`,'
                .' o.`id_shop`, o.`id_customer`, COALESCE(NULLIF(TRIM(CONCAT(c.`firstname`, " ", c.`lastname`)), ""), ta.`thread_email`, "") AS `customer`,'
                .' COALESCE(NULLIF(c.`email`, ""), ta.`thread_email`, "") AS `email`,'
                .' '.$entityRank.' AS `entity_status_rank`, COALESCE(ta.`thread_status_rank`, 0) AS `thread_status_rank`,'
                .' ta.`id_customer_thread`, osc.`date_upd` AS `entity_date_upd`, ta.`thread_date_upd`'
                .' FROM `'._DB_PREFIX_.'order_service_case` osc'
                .' INNER JOIN `'._DB_PREFIX_.'orders` o ON o.`id_order` = osc.`id_order`'
                .' LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.`id_customer` = o.`id_customer`'
                .' LEFT JOIN ('.$threadAggregate.') ta ON ta.`entity_type` = '.$serviceCaseType.' AND ta.`id_entity` = osc.`id_order_service_case`';
        }

        if ($this->hasTable('genzo_crm_product_wish') && $this->hasColumn('genzo_crm_product_wish', 'result')) {
            $productWishType = (int)\CoreExtension\EntityTypeEnum::PRODUCT_WISH_VALUE;
            $unions[] = 'SELECT '.$productWishType.' AS `entity_type`, pw.`id_product_wish` AS `id_entity`,'
                .' COALESCE(c.`id_shop`, '.(int)Context::getContext()->shop->id.') AS `id_shop`, pw.`id_customer`,'
                .' COALESCE(NULLIF(TRIM(CONCAT(c.`firstname`, " ", c.`lastname`)), ""), ta.`thread_email`, "") AS `customer`,'
                .' COALESCE(NULLIF(c.`email`, ""), ta.`thread_email`, "") AS `email`,'
                .' CASE WHEN pw.`result` IS NULL OR pw.`result` = "" THEN '.self::RANK_OPEN.' ELSE '.self::RANK_CLOSED.' END AS `entity_status_rank`,'
                .' COALESCE(ta.`thread_status_rank`, 0) AS `thread_status_rank`, ta.`id_customer_thread`,'
                .' pw.`date_upd` AS `entity_date_upd`, ta.`thread_date_upd`'
                .' FROM `'._DB_PREFIX_.'genzo_crm_product_wish` pw'
                .' LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.`id_customer` = pw.`id_customer`'
                .' LEFT JOIN ('.$threadAggregate.') ta ON ta.`entity_type` = '.$productWishType.' AND ta.`id_entity` = pw.`id_product_wish`';
        }

        if ($this->hasTable('customer_thread')) {
            $customerThreadType = (int)\CoreExtension\EntityTypeEnum::CUSTOMER_THREAD_VALUE;
            $structuredExists = $this->getStructuredThreadExclusionSql('ct');
            $unions[] = 'SELECT '.$customerThreadType.' AS `entity_type`, ct.`id_customer_thread` AS `id_entity`,'
                .' ct.`id_shop`, ct.`id_customer`, COALESCE(NULLIF(TRIM(CONCAT(c.`firstname`, " ", c.`lastname`)), ""), ct.`email`, "") AS `customer`, ct.`email`,'
                .' 0 AS `entity_status_rank`, '.$this->getThreadStatusRankSql('ct.`status`').' AS `thread_status_rank`,'
                .' ct.`id_customer_thread`, ct.`date_upd` AS `entity_date_upd`, ct.`date_upd` AS `thread_date_upd`'
                .' FROM `'._DB_PREFIX_.'customer_thread` ct'
                .' LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.`id_customer` = ct.`id_customer`'
                .' WHERE NOT ('.$structuredExists.')';
        }

        if (!$unions) {
            return 'SELECT 0 AS `entity_type`, 0 AS `id_entity`, 0 AS `id_shop`, 0 AS `id_customer`, "" AS `customer`, "" AS `email`,'
                .' 0 AS `entity_status_rank`, 0 AS `thread_status_rank`, 0 AS `id_customer_thread`, NULL AS `entity_date_upd`, NULL AS `thread_date_upd` WHERE 0';
        }

        $shopIds = array_values(array_filter(array_map('intval', $shopIds)));
        $shopRestriction = $shopIds ? ' WHERE work.`id_shop` IN ('.implode(',', $shopIds).')' : '';
        $rank = 'GREATEST(work.`entity_status_rank`, work.`thread_status_rank`)';
        $normalizedStatus = 'CASE '.$rank
            .' WHEN '.self::RANK_OPEN.' THEN "'.self::STATUS_OPEN.'"'
            .' WHEN '.self::RANK_WAITING_CUSTOMER.' THEN "'.self::STATUS_WAITING_CUSTOMER.'"'
            .' WHEN '.self::RANK_INQUIRY.' THEN "'.self::STATUS_INQUIRY.'"'
            .' ELSE "'.self::STATUS_CLOSED.'" END';

        $assignmentSelect = $this->hasTable('entity_employee_assignment')
            ? 'COALESCE(eea.`id_employee`, 0)'
            : '0';
        $assignmentJoin = $this->hasTable('entity_employee_assignment')
            ? ' LEFT JOIN `'._DB_PREFIX_.'entity_employee_assignment` eea'
                .' ON eea.`entity_type` = work.`entity_type` AND eea.`id_entity` = work.`id_entity`'
            : '';

        return 'SELECT CONCAT(work.`entity_type`, ":", work.`id_entity`) AS `work_item_key`, work.*,'
            .' '.$rank.' AS `status_priority`,'
            .' '.$normalizedStatus.' AS `normalized_status`,'
            .' CASE WHEN work.`thread_date_upd` IS NULL THEN work.`entity_date_upd`'
            .' WHEN work.`entity_date_upd` IS NULL THEN work.`thread_date_upd`'
            .' ELSE GREATEST(work.`entity_date_upd`, work.`thread_date_upd`) END AS `last_activity`,'
            .' '.$assignmentSelect.' AS `id_employee_assigned`'
            .' FROM ('.implode(' UNION ALL ', $unions).') work'
            .$assignmentJoin
            .$shopRestriction;
    }

    private function getThreadAggregateSql(): string
    {
        $rank = $this->getThreadStatusRankSql('ct.`status`');

        return 'SELECT ct.`entity_type`, ct.`id_entity`,'
            .' CAST(SUBSTRING_INDEX(GROUP_CONCAT(ct.`id_customer_thread` ORDER BY '.$rank.' DESC, ct.`date_upd` DESC), ",", 1) AS UNSIGNED) AS `id_customer_thread`,'
            .' SUBSTRING_INDEX(GROUP_CONCAT(ct.`email` ORDER BY '.$rank.' DESC, ct.`date_upd` DESC), ",", 1) AS `thread_email`,'
            .' MAX('.$rank.') AS `thread_status_rank`, MAX(ct.`date_upd`) AS `thread_date_upd`'
            .' FROM `'._DB_PREFIX_.'customer_thread` ct'
            .' WHERE ct.`entity_type` IN ('
            .(int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE.','
            .(int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE.','
            .(int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE.','
            .(int)\CoreExtension\EntityTypeEnum::PRODUCT_WISH_VALUE.')'
            .' AND ct.`id_entity` > 0 GROUP BY ct.`entity_type`, ct.`id_entity`';
    }

    private function getThreadStatusRankSql(string $field): string
    {
        return 'CASE '.$field
            .' WHEN "'.pSQL(CustomerThread::STATUS_OPEN).'" THEN '.self::RANK_OPEN
            .' WHEN "'.pSQL(CustomerThread::STATUS_WAITING_CUSTOMER).'" THEN '.self::RANK_WAITING_CUSTOMER
            .' WHEN "'.pSQL(CustomerThread::STATUS_IN_PROGRESS).'" THEN '.self::RANK_INQUIRY
            .' ELSE '.self::RANK_CLOSED.' END';
    }

    private function getStructuredThreadExclusionSql(string $alias): string
    {
        $checks = [];
        $definitions = [
            [(int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE, 'order_cancellation', 'id_order_cancellation'],
            [(int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE, 'order_return', 'id_order_return'],
            [(int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE, 'order_service_case', 'id_order_service_case'],
            [(int)\CoreExtension\EntityTypeEnum::PRODUCT_WISH_VALUE, 'genzo_crm_product_wish', 'id_product_wish'],
        ];
        foreach ($definitions as [$entityType, $table, $primary]) {
            if (!$this->hasTable($table)) {
                continue;
            }
            $checks[] = '('.$alias.'.`entity_type` = '.(int)$entityType.' AND EXISTS ('
                .'SELECT 1 FROM `'._DB_PREFIX_.bqSQL($table).'` linked_entity'
                .' WHERE linked_entity.`'.bqSQL($primary).'` = '.$alias.'.`id_entity`))';
        }

        return $checks ? implode(' OR ', $checks) : '0';
    }

    private function hasTable(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExists)) {
            $this->tableExists[$table] = (bool)Db::readOnly()->getValue(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()'
                .' AND TABLE_NAME = "'.pSQL(_DB_PREFIX_.$table).'"'
            );
        }

        return $this->tableExists[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        return (bool)Db::readOnly()->getValue(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
            .' AND TABLE_NAME = "'.pSQL(_DB_PREFIX_.$table).'" AND COLUMN_NAME = "'.pSQL($column).'"'
        );
    }
}
