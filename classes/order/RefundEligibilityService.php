<?php

class RefundEligibilityServiceCore
{
    /** @var RefundPolicy */
    protected $policy;

    /** @var CancelEligibilityService */
    protected $cancelEligibility;

    public function __construct(?RefundPolicy $policy = null, ?CancelEligibilityService $cancelEligibility = null)
    {
        $this->policy = $policy ?: new RefundPolicy();
        $this->cancelEligibility = $cancelEligibility ?: new CancelEligibilityService();
    }

    public function getOrderDetailActionCapabilities(Order $order, OrderDetail $orderDetail): array
    {
        $resume = OrderSlip::getProductSlipResume((int)$orderDetail->id);
        $product = [
            'id_order_detail' => (int)$orderDetail->id,
            'product_quantity' => (int)$orderDetail->product_quantity,
            'customized_product_quantity' => 0,
            'product_quantity_refunded' => (int)$orderDetail->product_quantity_refunded,
            'product_quantity_return' => (int)$orderDetail->product_quantity_return,
            'quantity_refundable' => (int)$orderDetail->product_quantity - (int)$resume['product_quantity'],
            'amount_refundable_tax_incl' => (float)$orderDetail->total_price_tax_incl - (float)$resume['amount_tax_incl'],
        ];

        return $this->getOrderProductActionCapabilities($order, $product);
    }

    public function getOrderProductActionCapabilities(Order $order, array $product, $orderDetailExtension = null): array
    {
        $orderedQuantity = (int)$product['product_quantity'] - (int)$product['customized_product_quantity'];
        $refundedQuantity = (int)$product['product_quantity_refunded'];
        $returnedQuantity = (int)$product['product_quantity_return'];
        $shippingQuantity = $this->getShippingQuantity($order, $product, $orderedQuantity, $orderDetailExtension);
        $openReturnQuantity = OrderReturn::getOpenReturnQuantityByOrderDetail((int)$product['id_order_detail']);
        $blockedQuantity = $refundedQuantity + $returnedQuantity + $openReturnQuantity;
        $refundableQuantity = min(
            max(0, (int)$product['quantity_refundable']),
            max(0, $orderedQuantity - $refundedQuantity)
        );
        $cancelableQuantity = $this->cancelEligibility->getOrderProductCancelableQuantity(
            $order,
            $product,
            CancelEligibilityService::CONTEXT_BACK_OFFICE,
            $orderDetailExtension
        );

        return [
            'cancelable_quantity' => $cancelableQuantity,
            'returnable_quantity' => max(0, $shippingQuantity - $blockedQuantity),
            'serviceable_quantity' => max(0, $orderedQuantity - $blockedQuantity),
            'open_return_quantity' => $openReturnQuantity,
            'shipping_quantity' => $shippingQuantity,
            'refundable_quantity' => $refundableQuantity,
            'amount_refundable_tax_incl' => max(0.0, (float)$product['amount_refundable_tax_incl']),
        ];
    }

    public function getOpenOrderReturnRows(Order $order, int $idLang): array
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('orx.`id_order_return`, orx.`state`, orx.`date_add`, orsl.`name` AS `state_name`')
                ->from('order_return', 'orx')
                ->leftJoin(
                    'order_return_state_lang',
                    'orsl',
                    'orsl.`id_order_return_state` = orx.`state` AND orsl.`id_lang` = '.(int)$idLang
                )
                ->where('orx.`id_order` = '.(int)$order->id)
                ->where('orx.`state` IN ('.implode(',', array_map('intval', $this->policy->getOpenOrderReturnStates())).')')
                ->orderBy('orx.`id_order_return` DESC')
        );
    }

    public function getOrderReturnQuantities(int $idOrderReturn): array
    {
        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_order_detail`, SUM(`product_quantity`) AS `quantity`')
                ->from('order_return_detail')
                ->where('`id_order_return` = '.(int)$idOrderReturn)
                ->groupBy('`id_order_detail`')
        );

        $quantities = [];
        foreach ($rows as $row) {
            $quantities[(int)$row['id_order_detail']] = (int)$row['quantity'];
        }

        return $quantities;
    }

    public function getOpenCancellationQuantities(Order $order): array
    {
        if ($order->hasBeenShipped()) {
            return [];
        }

        $creditedRows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('osd.`id_order_detail`, SUM(osd.`product_quantity`) AS `quantity`')
                ->from('order_slip_detail', 'osd')
                ->innerJoin('order_slip', 'os', 'os.`id_order_slip` = osd.`id_order_slip`')
                ->where('os.`id_order` = '.(int)$order->id)
                ->where('os.`reason_entity_type` = \''.pSQL(RefundPolicy::REASON_CANCELLATION).'\'')
                ->where('os.`reason_id_entity` = '.(int)$order->id)
                ->groupBy('osd.`id_order_detail`')
        );

        $creditedQuantities = [];
        foreach ($creditedRows as $row) {
            $creditedQuantities[(int)$row['id_order_detail']] = (int)$row['quantity'];
        }

        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_order_detail`, `product_quantity_refunded`')
                ->from('order_detail')
                ->where('`id_order` = '.(int)$order->id)
                ->where('`product_quantity_refunded` > 0')
        );

        $quantities = [];
        foreach ($rows as $row) {
            $idOrderDetail = (int)$row['id_order_detail'];
            $openQuantity = (int)$row['product_quantity_refunded'] - (int)($creditedQuantities[$idOrderDetail] ?? 0);
            if ($openQuantity > 0) {
                $quantities[$idOrderDetail] = $openQuantity;
            }
        }

        return $quantities;
    }

    public function isValidOrderReturn(Order $order, int $idOrderReturn): bool
    {
        if ($idOrderReturn <= 0) {
            return false;
        }

        return (bool)Db::readOnly()->getValue(
            (new DbQuery())
                ->select('1')
                ->from('order_return')
                ->where('`id_order_return` = '.(int)$idOrderReturn)
                ->where('`id_order` = '.(int)$order->id)
                ->where('`state` IN ('.implode(',', array_map('intval', $this->policy->getOpenOrderReturnStates())).')')
        );
    }

    public function isValidCancellationReference(Order $order, int $idOrder): bool
    {
        return (int)$order->id > 0 && (int)$order->id === $idOrder;
    }

    public function getOrderDetailRemainingCreditAmounts(OrderDetail $orderDetail): array
    {
        $resume = OrderSlip::getProductSlipResume((int)$orderDetail->id);

        return [
            'tax_excl' => $this->policy->roundPriceAmount(max(
                0.0,
                (float)$orderDetail->total_price_tax_excl - (float)$resume['amount_tax_excl']
            )),
            'tax_incl' => $this->policy->roundPriceAmount(max(
                0.0,
                (float)$orderDetail->total_price_tax_incl - (float)$resume['amount_tax_incl']
            )),
        ];
    }

    public function getRemainingShippingCreditAmounts(Order $order): array
    {
        $row = Db::readOnly()->getRow(
            (new DbQuery())
                ->select('COALESCE(SUM(`total_shipping_tax_excl`), 0) AS `tax_excl`')
                ->select('COALESCE(SUM(`total_shipping_tax_incl`), 0) AS `tax_incl`')
                ->from('order_slip')
                ->where('`id_order` = '.(int)$order->id)
        );

        return [
            'tax_excl' => $this->policy->roundPriceAmount(max(
                0.0,
                (float)$order->total_shipping_tax_excl - (float)$row['tax_excl']
            )),
            'tax_incl' => $this->policy->roundPriceAmount(max(
                0.0,
                (float)$order->total_shipping_tax_incl - (float)$row['tax_incl']
            )),
        ];
    }

    protected function getShippingQuantity(Order $order, array $product, int $orderedQuantity, $orderDetailExtension = null): int
    {
        return $this->cancelEligibility->getShippingQuantity($order, $product, $orderedQuantity, $orderDetailExtension);
    }
}
