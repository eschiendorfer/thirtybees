<?php

/**
 * Class OrderCancellationCore
 */
class OrderCancellationCore extends ObjectModel
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    public const REFUND_METHOD_STORE_CREDIT = 'store_credit';
    public const REFUND_METHOD_ORIGINAL_PAYMENT = 'original_payment';

    public static $definition = [
        'table' => 'order_cancellation',
        'primary' => 'id_order_cancellation',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbNullable' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16, 'required' => true, 'dbDefault' => self::STATUS_REQUESTED],
            'requested_refund_method' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32, 'dbNullable' => true],
            'quoted_refund_total_tax_incl' => ['type' => self::TYPE_PRICE, 'validate' => 'isPrice', 'dbNullable' => true],
            'quoted_fee_tax_incl' => ['type' => self::TYPE_PRICE, 'validate' => 'isPrice', 'dbNullable' => true],
            'migrated' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'dbType' => 'tinyint(1) unsigned', 'dbDefault' => '0'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false],
        ],
        'keys' => [
            'order_cancellation' => [
                'id_order' => ['type' => ObjectModel::KEY, 'columns' => ['id_order']],
                'status' => ['type' => ObjectModel::KEY, 'columns' => ['status']],
            ],
        ],
    ];

    /** @var int */
    public $id;
    /** @var int */
    public $id_order;
    /** @var int|null */
    public $id_employee;
    /** @var string */
    public $status = self::STATUS_REQUESTED;
    /** @var string|null */
    public $requested_refund_method;
    /** @var float|null */
    public $quoted_refund_total_tax_incl;
    /** @var float|null */
    public $quoted_fee_tax_incl;
    /** @var bool */
    public $migrated = false;
    /** @var string */
    public $date_add;
    /** @var string */
    public $date_upd;

    public static function createAppliedForOrder(
        Order $order,
        array $quantitiesByOrderDetail,
        int $idEmployee = 0,
        bool $migrated = false
    ): ?self {
        if (!Validate::isLoadedObject($order)) {
            return null;
        }

        $rows = [];
        foreach ($quantitiesByOrderDetail as $idOrderDetail => $quantity) {
            $quantity = max(0, (int)$quantity);
            if ($quantity <= 0) {
                continue;
            }

            $orderDetail = new OrderDetail((int)$idOrderDetail);
            if (!Validate::isLoadedObject($orderDetail) || (int)$orderDetail->id_order !== (int)$order->id) {
                return null;
            }

            $rows[(int)$idOrderDetail] = $quantity;
        }

        if (!$rows) {
            return null;
        }

        $db = Db::getInstance();
        $db->execute('START TRANSACTION');

        try {
            $orderCancellation = new self();
            $orderCancellation->id_order = (int)$order->id;
            $orderCancellation->id_employee = $idEmployee > 0 ? $idEmployee : null;
            $orderCancellation->status = self::STATUS_APPLIED;
            $orderCancellation->migrated = $migrated;

            if (!$orderCancellation->add(true, true)) {
                throw new PrestaShopException('Order cancellation could not be saved.');
            }

            foreach ($rows as $idOrderDetail => $quantity) {
                $detail = new OrderCancellationDetail();
                $detail->id_order_cancellation = (int)$orderCancellation->id;
                $detail->id_order_detail = (int)$idOrderDetail;
                $detail->product_quantity = (int)$quantity;

                if (!$detail->add()) {
                    throw new PrestaShopException('Order cancellation detail could not be saved.');
                }
            }

            $db->execute('COMMIT');
            return $orderCancellation;
        } catch (Exception $exception) {
            $db->execute('ROLLBACK');
            return null;
        }
    }

    public function update($nullValues = false)
    {
        $idOrderDetails = $this->getOrderDetailIds();
        $result = parent::update($nullValues);
        if ($result) {
            $this->syncOrderDetailIds($idOrderDetails);
        }

        return $result;
    }

    public function delete()
    {
        $idOrderDetails = $this->getOrderDetailIds();
        $result = parent::delete();
        if ($result) {
            Db::getInstance()->delete('order_cancellation_detail', '`id_order_cancellation` = ' . (int)$this->id);
            $this->syncOrderDetailIds($idOrderDetails);
        }

        return $result;
    }

    private function getOrderDetailIds(): array
    {
        if ((int)$this->id <= 0) {
            return [];
        }

        $rows = Db::getInstance()->getArray(
            (new DbQuery())
                ->select('DISTINCT `id_order_detail`')
                ->from('order_cancellation_detail')
                ->where('`id_order_cancellation` = ' . (int)$this->id)
        );

        return array_map('intval', array_column($rows, 'id_order_detail'));
    }

    private function syncOrderDetailIds(array $idOrderDetails): void
    {
        foreach (array_unique(array_map('intval', $idOrderDetails)) as $idOrderDetail) {
            OrderCancellationDetail::syncCancelledQuantity($idOrderDetail);
        }
    }
}
