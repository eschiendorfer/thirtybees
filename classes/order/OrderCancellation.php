<?php

/**
 * Class OrderCancellationCore
 */
class OrderCancellationCore extends ObjectModel implements CustomerThreadContextSourceInterfaceCore
{
    public const STATUS_OPEN = 'open';
    public const STATUS_QUANTITY_CANCELLED = 'quantity_cancelled';
    public const STATUS_DONE = 'done';

    public const REFUND_METHOD_STORE_CREDIT = 'store_credit';
    public const REFUND_METHOD_ORIGINAL_PAYMENT = 'original_payment';

    public static $definition = [
        'table' => 'order_cancellation',
        'primary' => 'id_order_cancellation',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbNullable' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32, 'required' => true, 'dbDefault' => self::STATUS_OPEN],
            'requested_refund_method' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32, 'dbNullable' => true],
            'quoted_refund_total_tax_incl' => ['type' => self::TYPE_PRICE, 'validate' => 'isPrice', 'dbNullable' => true],
            'quoted_fee_tax_incl' => ['type' => self::TYPE_PRICE, 'validate' => 'isPrice', 'dbNullable' => true],
            // Positive values refund shipping; negative values are newly required shipping.
            'quoted_shipping_tax_incl' => ['type' => self::TYPE_PRICE, 'validate' => 'isNegativePrice', 'dbNullable' => true],
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
    public $status = self::STATUS_OPEN;
    /** @var string|null */
    public $requested_refund_method;
    /** @var float|null */
    public $quoted_refund_total_tax_incl;
    /** @var float|null */
    public $quoted_fee_tax_incl;
    /** @var float|null */
    public $quoted_shipping_tax_incl;
    /** @var bool */
    public $migrated = false;
    /** @var string */
    public $date_add;
    /** @var string */
    public $date_upd;

    public static function createForOrder(
        Order $order,
        array $quantitiesByOrderDetail,
        int $idEmployee = 0,
        bool $migrated = false,
        array $quote = [],
        bool $manageTransaction = true
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

            $rows[(int)$idOrderDetail] = [
                'quantity' => $quantity,
                'id_product' => (int)$orderDetail->product_id,
                'id_product_attribute' => (int)$orderDetail->product_attribute_id,
                'product_reference' => (string)$orderDetail->product_reference,
                'product_name' => (string)$orderDetail->product_name,
            ];
        }

        if (!$rows) {
            return null;
        }

        $db = Db::getInstance();
        if ($manageTransaction) {
            $db->execute('START TRANSACTION');
        }

        try {
            $orderCancellation = new self();
            $orderCancellation->id_order = (int)$order->id;
            $orderCancellation->id_employee = $idEmployee > 0 ? $idEmployee : null;
            $orderCancellation->status = $migrated
                ? self::STATUS_DONE
                : ($order->hasBeenPaid() ? self::STATUS_QUANTITY_CANCELLED : self::STATUS_OPEN);
            $orderCancellation->migrated = $migrated;
            $orderCancellation->requested_refund_method = !empty($quote['refund_method'])
                ? (string)$quote['refund_method']
                : null;
            $orderCancellation->quoted_refund_total_tax_incl = array_key_exists('refund_total_tax_incl', $quote)
                ? (float)$quote['refund_total_tax_incl']
                : null;
            $orderCancellation->quoted_fee_tax_incl = array_key_exists('fee_tax_incl', $quote)
                ? (float)$quote['fee_tax_incl']
                : null;
            $orderCancellation->quoted_shipping_tax_incl = array_key_exists('shipping_tax_incl', $quote)
                ? (float)$quote['shipping_tax_incl']
                : null;

            if (!$orderCancellation->add(true, true)) {
                throw new PrestaShopException('Order cancellation could not be saved.');
            }

            foreach ($rows as $idOrderDetail => $row) {
                $detail = new OrderCancellationDetail();
                $detail->id_order_cancellation = (int)$orderCancellation->id;
                $detail->id_order_detail = (int)$idOrderDetail;
                $detail->id_product = (int)$row['id_product'];
                $detail->id_product_attribute = (int)$row['id_product_attribute'];
                $detail->product_reference = (string)$row['product_reference'];
                $detail->product_name = (string)$row['product_name'];
                $detail->product_quantity = (int)$row['quantity'];
                $detailQuote = (array)($quote['details'][(int)$idOrderDetail] ?? []);
                $detail->quoted_product_amount_tax_incl = array_key_exists('product_amount_tax_incl', $detailQuote)
                    ? (float)$detailQuote['product_amount_tax_incl']
                    : null;
                $detail->quoted_fee_tax_incl = array_key_exists('fee_tax_incl', $detailQuote)
                    ? (float)$detailQuote['fee_tax_incl']
                    : null;
                $detail->quoted_fee_policy = !empty($detailQuote['fee_policy'])
                    ? (string)$detailQuote['fee_policy']
                    : null;

                if (!$detail->add()) {
                    throw new PrestaShopException('Order cancellation detail could not be saved.');
                }
            }

            if ($manageTransaction) {
                $db->execute('COMMIT');
            }
            return $orderCancellation;
        } catch (Exception $exception) {
            if ($manageTransaction) {
                $db->execute('ROLLBACK');
            }
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomerThreadContextData(): array
    {
        $order = new Order((int)$this->id_order);
        $reference = Validate::isLoadedObject($order) ? (string)$order->reference : '';
        $details = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('ocd.`id_order_detail`, ocd.`product_quantity` AS `quantity`, ocd.`quoted_product_amount_tax_incl`, COALESCE(od.`product_id`, ocd.`id_product`) AS `id_product`, COALESCE(od.`product_attribute_id`, ocd.`id_product_attribute`) AS `id_product_attribute`, COALESCE(od.`product_reference`, ocd.`product_reference`) AS `product_reference`, COALESCE(od.`product_name`, ocd.`product_name`) AS `product_name`')
                ->from('order_cancellation_detail', 'ocd')
                ->leftJoin('order_detail', 'od', 'od.`id_order_detail` = ocd.`id_order_detail`')
                ->where('ocd.`id_order_cancellation` = '.(int)$this->id)
                ->orderBy('ocd.`id_order_cancellation_detail` ASC')
        );
        $hasCustomerQuote = !empty($details);
        $quotedProductsTotal = 0.0;
        foreach ($details as &$detail) {
            if ($detail['quoted_product_amount_tax_incl'] === null) {
                $hasCustomerQuote = false;
            } else {
                $quotedProductsTotal += (float)$detail['quoted_product_amount_tax_incl'];
            }
            unset($detail['quoted_product_amount_tax_incl']);
        }
        unset($detail);
        $isPaid = Validate::isLoadedObject($order) && $order->hasBeenPaid();
        $requiresManualOrderReview = !$isPaid
            && $hasCustomerQuote
            && (float)$this->quoted_shipping_tax_incl < 0.0
            && Tools::roundPrice($quotedProductsTotal + (float)$this->quoted_shipping_tax_incl) <= 0.0;
        if ((string)$this->status === self::STATUS_DONE) {
            $nextAction = 'Completed';
        } elseif ((string)$this->status === self::STATUS_OPEN && !$isPaid) {
            $nextAction = $requiresManualOrderReview ? 'Check manually' : 'Adjust order';
        } elseif (
            (string)$this->status === self::STATUS_QUANTITY_CANCELLED
            && $hasCustomerQuote
            && $isPaid
            && (float)$this->quoted_refund_total_tax_incl > 0.0
        ) {
            $nextAction = 'Confirm refund';
        } else {
            $nextAction = 'Check manually';
        }

        return [
            'context_type' => 'order_cancellation',
            'title' => 'Order cancellation',
            'reference' => '#'.(int)$this->id,
            'related_order_id' => (int)$this->id_order,
            'related_order_reference' => $reference,
            'product_quantity_label' => 'Cancelled quantity',
            'fields' => [
                ['label' => 'Next action', 'value' => $nextAction, 'translate_value' => true, 'is_status' => true],
            ],
            'products' => $details,
            'action' => [
                'label' => 'Open cancellation',
                'controller' => 'AdminOrderCancellations',
                'params' => [
                    'vieworder_cancellation' => 1,
                    'id_order_cancellation' => (int)$this->id,
                ],
            ],
        ];
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
