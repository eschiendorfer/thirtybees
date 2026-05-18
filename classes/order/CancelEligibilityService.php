<?php

class CancelEligibilityServiceCore
{
    public const CONTEXT_BACK_OFFICE = 'backoffice';
    public const CONTEXT_FRONT_OFFICE = 'frontoffice';

    public const FRONT_OFFICE_OPERATION_DELETE = 'delete';
    public const FRONT_OFFICE_OPERATION_CANCEL = 'cancel';

    public const REASON_ALREADY_SHIPPED = 'already_shipped';
    public const REASON_ALREADY_PICKED = 'already_picked';
    public const REASON_NOTHING_CANCELABLE = 'nothing_cancelable';

    public function canEditProductsInBackOffice(Order $order): bool
    {
        return !$order->hasBeenPaid() && !$order->hasBeenShipped();
    }

    public function canCancelProductsInBackOffice(Order $order): bool
    {
        return $order->hasBeenPaid() && !$order->hasBeenShipped();
    }

    public function canCancelProductsInFrontOffice(Order $order): bool
    {
        return !$order->hasBeenShipped() && !$this->hasPickingRows($order);
    }

    public function getFrontOfficeCancellationOperation(Order $order): ?string
    {
        if (!$this->canCancelProductsInFrontOffice($order)) {
            return null;
        }

        return $order->hasBeenPaid()
            ? self::FRONT_OFFICE_OPERATION_CANCEL
            : self::FRONT_OFFICE_OPERATION_DELETE;
    }

    public function getOrderDetailCancelableQuantity(
        Order $order,
        OrderDetail $orderDetail,
        string $context = self::CONTEXT_BACK_OFFICE
    ): int {
        $product = [
            'id_order_detail' => (int)$orderDetail->id,
            'product_quantity' => (int)$orderDetail->product_quantity,
            'customized_product_quantity' => 0,
            'product_quantity_refunded' => (int)$orderDetail->product_quantity_refunded,
            'product_quantity_return' => (int)$orderDetail->product_quantity_return,
        ];

        return $this->getOrderProductCancelableQuantity($order, $product, $context);
    }

    public function getOrderProductCancelableQuantity(
        Order $order,
        array $product,
        string $context = self::CONTEXT_BACK_OFFICE,
        $orderDetailExtension = null
    ): int {
        if (!$this->canCancelInContext($order, $context)) {
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
        string $context = self::CONTEXT_BACK_OFFICE,
        $orderDetailExtension = null
    ): array {
        $reasons = [];

        if ($order->hasBeenShipped()) {
            $reasons[] = self::REASON_ALREADY_SHIPPED;
        }

        if ($context === self::CONTEXT_FRONT_OFFICE && $this->hasPickingRows($order)) {
            $reasons[] = self::REASON_ALREADY_PICKED;
        }

        if (!$reasons && $this->getOrderProductCancelableQuantity($order, $product, $context, $orderDetailExtension) <= 0) {
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

        $shippingQuantity = $orderDetailExtension === null ? 0 : (int)$orderDetailExtension->shipping_quantity;
        if ($hasOrderDetailExtension) {
            $shippingQuantity = $shippingQuantity > 0 ? $orderedQuantity : 0;
        } elseif ($order->hasBeenShipped()) {
            $shippingQuantity = $orderedQuantity;
        }

        return min($orderedQuantity, max(0, $shippingQuantity));
    }

    public function hasPickingRows(Order $order): bool
    {
        if ((int)$order->id <= 0) {
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
            );
        } catch (Exception $e) {
            return false;
        }
    }

    protected function canCancelInContext(Order $order, string $context): bool
    {
        if ($context === self::CONTEXT_FRONT_OFFICE) {
            return $this->canCancelProductsInFrontOffice($order);
        }

        return $this->canCancelProductsInBackOffice($order);
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
