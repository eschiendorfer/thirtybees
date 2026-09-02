<?php

class CancelEligibilityServiceCore
{
    public const REASON_ALREADY_SHIPPED = 'already_shipped';
    public const REASON_ALREADY_PICKED = 'already_picked';
    public const REASON_NOTHING_CANCELABLE = 'nothing_cancelable';

    public function canCancelProducts(Order $order): bool
    {
        return !in_array(
            (int)$order->getCurrentState(),
            [
                (int)Configuration::get('PS_OS_CANCELED'),
                (int)Configuration::get('PS_OS_ERROR'),
                (int)Configuration::get('PS_OS_REFUND'),
            ],
            true
        );
    }

    public function getOrderDetailCancelableQuantity(
        Order $order,
        OrderDetail $orderDetail,
        $orderDetailExtension = null
    ): int {
        $product = [
            'id_order_detail' => (int)$orderDetail->id,
            'product_quantity' => (int)$orderDetail->product_quantity,
            'customized_product_quantity' => 0,
            'product_quantity_refunded' => (int)$orderDetail->product_quantity_refunded,
            'product_quantity_return' => (int)$orderDetail->product_quantity_return,
        ];

        return $this->getOrderProductCancelableQuantity($order, $product, $orderDetailExtension);
    }

    public function getOrderProductCancelableQuantity(
        Order $order,
        array $product,
        $orderDetailExtension = null
    ): int {
        if (!$this->canCancelProducts($order)) {
            return 0;
        }

        // Picking and shipping are position-specific. A blocked line must not
        // affect other outstanding products from the same split/preorder order.
        if ($this->isOrderDetailInPicking($order, (int)($product['id_order_detail'] ?? 0))) {
            return 0;
        }

        $orderedQuantity = $this->getOrderedQuantity($product);
        if ($orderedQuantity <= 0) {
            return 0;
        }

        $shippingQuantity = $this->getShippingQuantity($order, $product, $orderedQuantity, $orderDetailExtension);
        $refundedQuantity = (int)($product['product_quantity_refunded'] ?? 0);
        $returnedQuantity = (int)($product['product_quantity_return'] ?? 0);

        return max(0, $orderedQuantity - $shippingQuantity - $refundedQuantity - $returnedQuantity);
    }

    public function getOrderProductBlockReasons(
        Order $order,
        array $product,
        $orderDetailExtension = null
    ): array {
        $reasons = [];

        $orderedQuantity = $this->getOrderedQuantity($product);
        if ($this->getShippingQuantity($order, $product, $orderedQuantity, $orderDetailExtension) > 0) {
            $reasons[] = self::REASON_ALREADY_SHIPPED;
        }

        if ($this->isOrderDetailInPicking($order, (int)($product['id_order_detail'] ?? 0))) {
            $reasons[] = self::REASON_ALREADY_PICKED;
        }

        if (!$reasons && $this->getOrderProductCancelableQuantity($order, $product, $orderDetailExtension) <= 0) {
            $reasons[] = self::REASON_NOTHING_CANCELABLE;
        }

        return $reasons;
    }

    public function getShippingQuantity(Order $order, array $product, int $orderedQuantity, $orderDetailExtension = null): int
    {
        $hasOrderDetailExtension = $this->hasOrderDetailExtensionClass();
        if ($orderDetailExtension === null && $hasOrderDetailExtension) {
            try {
                $orderDetailExtension = new \CrmModule\OrderDetailExtension((int)$product['id_order_detail']);
            } catch (Exception $e) {
                $orderDetailExtension = null;
            }
        }

        $hasLoadedOrderDetailExtension = $orderDetailExtension !== null
            && Validate::isLoadedObject($orderDetailExtension);
        $shippingQuantity = $hasLoadedOrderDetailExtension ? (int)$orderDetailExtension->shipping_quantity : 0;
        if ($hasOrderDetailExtension && $hasLoadedOrderDetailExtension) {
            $shippingQuantity = $shippingQuantity > 0 ? $orderedQuantity : 0;
        } elseif ($order->hasBeenShipped()) {
            $shippingQuantity = $orderedQuantity;
        }

        return min($orderedQuantity, max(0, $shippingQuantity));
    }

    public function isOrderDetailInPicking(Order $order, int $idOrderDetail): bool
    {
        if ((int)$order->id <= 0 || $idOrderDetail <= 0) {
            return false;
        }

        $table = $this->getPickingListDetailTable();
        if ($table === '') {
            return false;
        }

        try {
            return (bool)Db::readOnly()->getValue(
                (new DbQuery())
                    ->select('1')
                    ->from($table)
                    ->where('`id_order` = '.(int)$order->id)
                    ->where('`id_order_detail` = '.$idOrderDetail)
            );
        } catch (Exception $e) {
            // If the picking state cannot be read, do not expose the position
            // as cancelable. A retry is safer than canceling stock in motion.
            return true;
        }
    }

    protected function getOrderedQuantity(array $product): int
    {
        return max(
            0,
            (int)($product['product_quantity'] ?? 0) - (int)($product['customized_product_quantity'] ?? 0)
        );
    }

    protected function getPickingListDetailTable(): string
    {
        if (!class_exists('\\ErpModule\\PickingListDetailErp') && defined('_PS_MODULE_DIR_')) {
            $autoload = _PS_MODULE_DIR_.'genzo_erp/autoload.php';
            if (file_exists($autoload)) {
                include_once $autoload;
            }
        }

        if (class_exists('\\ErpModule\\PickingListDetailErp')) {
            return \ErpModule\PickingListDetailErp::$definition['table'];
        }

        return '';
    }

    protected function hasOrderDetailExtensionClass(): bool
    {
        if (!class_exists('\\CrmModule\\OrderDetailExtension') && defined('_PS_MODULE_DIR_')) {
            $autoload = _PS_MODULE_DIR_.'genzo_crm/autoload.php';
            if (file_exists($autoload)) {
                include_once $autoload;
            }
        }

        return class_exists('\\CrmModule\\OrderDetailExtension');
    }
}
