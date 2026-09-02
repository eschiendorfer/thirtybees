<?php

/**
 * Class OrderCancellationDetailCore
 */
class OrderCancellationDetailCore extends ObjectModel
{
    public static $definition = [
        'table' => 'order_cancellation_detail',
        'primary' => 'id_order_cancellation_detail',
        'fields' => [
            'id_order_cancellation' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_order_detail' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '0'],
            'id_product_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '0'],
            'product_reference' => ['type' => self::TYPE_STRING, 'validate' => 'isReference', 'size' => 64, 'dbNullable' => true],
            'product_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255, 'dbNullable' => true],
            'product_quantity' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true, 'dbDefault' => '0'],
            'quoted_product_amount_tax_incl' => ['type' => self::TYPE_PRICE, 'validate' => 'isPrice', 'dbNullable' => true],
            'quoted_fee_tax_incl' => ['type' => self::TYPE_PRICE, 'validate' => 'isPrice', 'dbNullable' => true],
            'quoted_fee_policy' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32, 'dbNullable' => true],
        ],
        'keys' => [
            'order_cancellation_detail' => [
                'id_order_cancellation' => ['type' => ObjectModel::KEY, 'columns' => ['id_order_cancellation']],
                'id_order_detail' => ['type' => ObjectModel::KEY, 'columns' => ['id_order_detail']],
            ],
        ],
    ];

    /** @var int */
    public $id;
    /** @var int */
    public $id_order_cancellation;
    /** @var int */
    public $id_order_detail;
    /** @var int */
    public $id_product = 0;
    /** @var int */
    public $id_product_attribute = 0;
    /** @var string|null */
    public $product_reference;
    /** @var string|null */
    public $product_name;
    /** @var int */
    public $product_quantity;
    /** @var float|null */
    public $quoted_product_amount_tax_incl;
    /** @var float|null */
    public $quoted_fee_tax_incl;
    /** @var string|null */
    public $quoted_fee_policy;

    public function add($autoDate = true, $nullValues = false)
    {
        $result = parent::add($autoDate, $nullValues);
        if ($result) {
            self::syncCancelledQuantity((int)$this->id_order_detail);
        }

        return $result;
    }

    public function update($nullValues = false)
    {
        $previousIdOrderDetail = $this->getStoredOrderDetailId();
        $result = parent::update($nullValues);
        if ($result) {
            self::syncCancelledQuantity((int)$this->id_order_detail);
            if ($previousIdOrderDetail > 0 && $previousIdOrderDetail !== (int)$this->id_order_detail) {
                self::syncCancelledQuantity($previousIdOrderDetail);
            }
        }

        return $result;
    }

    public function delete()
    {
        $idOrderDetail = (int)$this->id_order_detail ?: $this->getStoredOrderDetailId();
        $result = parent::delete();
        if ($result && $idOrderDetail > 0) {
            self::syncCancelledQuantity($idOrderDetail);
        }

        return $result;
    }

    public static function syncCancelledQuantity(int $idOrderDetail): bool
    {
        if ($idOrderDetail <= 0) {
            return false;
        }

        $quantity = (int)Db::getInstance()->getValue(
            (new DbQuery())
                ->select('COALESCE(SUM(ocd.`product_quantity`), 0)')
                ->from('order_cancellation_detail', 'ocd')
                ->innerJoin('order_cancellation', 'oc', 'oc.`id_order_cancellation` = ocd.`id_order_cancellation`')
                ->where('ocd.`id_order_detail` = ' . (int)$idOrderDetail)
                ->where(
                    '(oc.`status` IN (\''
                    .pSQL(OrderCancellation::STATUS_OPEN).'\', \''
                    .pSQL(OrderCancellation::STATUS_QUANTITY_CANCELLED).'\')'
                    .' OR (oc.`status` = \''.pSQL(OrderCancellation::STATUS_DONE).'\' AND ('
                    .'oc.`migrated` = 1'
                    .' OR EXISTS ('
                    .'SELECT 1 FROM `'._DB_PREFIX_.'order_slip` osl'
                    .' WHERE osl.`reason_entity_type` = '.(int)RefundPolicy::REASON_CANCELLATION
                    .' AND osl.`reason_id_entity` = oc.`id_order_cancellation`'
                    .')'
                    .')))'
                )
        );

        // TODO: legacy compatibility cache. Rename this column to product_quantity_cancelled/product_quantity_cancellation or remove it.
        return (bool)Db::getInstance()->update(
            'order_detail',
            ['product_quantity_refunded' => max(0, $quantity)],
            '`id_order_detail` = ' . (int)$idOrderDetail
        );
    }

    public static function getQuantitiesForCancellation(int $idOrderCancellation): array
    {
        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_order_detail`, `product_quantity`')
                ->from('order_cancellation_detail')
                ->where('`id_order_cancellation` = '.(int)$idOrderCancellation)
        );
        $quantities = [];
        foreach ($rows as $row) {
            $quantities[(int)$row['id_order_detail']] = (int)$row['product_quantity'];
        }

        return $quantities;
    }

    private function getStoredOrderDetailId(): int
    {
        if ((int)$this->id <= 0) {
            return 0;
        }

        return (int)Db::getInstance()->getValue(
            (new DbQuery())
                ->select('`id_order_detail`')
                ->from('order_cancellation_detail')
                ->where('`id_order_cancellation_detail` = ' . (int)$this->id)
        );
    }
}
