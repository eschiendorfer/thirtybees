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
            'product_quantity' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true, 'dbDefault' => '0'],
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
    public $product_quantity;

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
                ->where('oc.`status` = \'' . pSQL(OrderCancellation::STATUS_APPLIED) . '\'')
        );

        // TODO: legacy compatibility cache. Rename this column to product_quantity_cancelled/product_quantity_cancellation or remove it.
        return (bool)Db::getInstance()->update(
            'order_detail',
            ['product_quantity_refunded' => max(0, $quantity)],
            '`id_order_detail` = ' . (int)$idOrderDetail
        );
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
