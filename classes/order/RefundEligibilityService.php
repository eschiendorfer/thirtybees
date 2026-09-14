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

        $orderDetailExtension = null;
        if (class_exists('\\CrmModule\\OrderDetailExtension')) {
            $extension = new \CrmModule\OrderDetailExtension((int)$orderDetail->id);
            if (Validate::isLoadedObject($extension)) {
                $orderDetailExtension = $extension;
            }
        }

        return $this->getOrderProductActionCapabilities($order, $product, $orderDetailExtension);
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
        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('orx.`id_order_return`, orx.`processing_status`, orx.`date_add`')
                ->select('SUM(ord.`received_quantity`) AS `quantity`')
                ->from('order_return', 'orx')
                ->innerJoin('order_return_detail', 'ord', 'ord.`id_order_return` = orx.`id_order_return`')
                ->where('orx.`id_order` = '.(int)$order->id)
                ->groupBy('orx.`id_order_return`')
                ->orderBy('orx.`id_order_return` DESC')
        );

        if (!$rows) {
            return [];
        }

        $creditedRows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('os.`reason_id_entity` AS `id_order_return`, SUM(osd.`product_quantity`) AS `quantity`')
                ->from('order_slip', 'os')
                ->innerJoin('order_slip_detail', 'osd', 'osd.`id_order_slip` = os.`id_order_slip`')
                ->where('os.`id_order` = '.(int)$order->id)
                ->where('os.`reason_entity_type` = '.(int)RefundPolicy::REASON_ORDER_RETURN)
                ->groupBy('os.`reason_id_entity`')
        );

        $creditedQuantities = [];
        foreach ($creditedRows as $creditedRow) {
            $creditedQuantities[(int)$creditedRow['id_order_return']] = (int)$creditedRow['quantity'];
        }

        $openRows = [];
        foreach ($rows as $row) {
            $idOrderReturn = (int)$row['id_order_return'];
            $row['state_name'] = CustomerServiceStatus::getOptions(new OrderReturn($idOrderReturn))[$row['processing_status']]['label'];
            $row['state'] = $row['processing_status'];
            $row['credited_quantity'] = (int)($creditedQuantities[$idOrderReturn] ?? 0);
            if ((int)$row['quantity'] > (int)$row['credited_quantity']) {
                $openRows[] = $row;
            }
        }

        return $openRows;
    }

    public function getOrderReturnQuantities(int $idOrderReturn): array
    {
        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_order_detail`, SUM(`received_quantity`) AS `quantity`')
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

    public function getUncreditedOrderReturnQuantities(Order $order, ?int $idOrderReturn = null): array
    {
        $creditedRows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('osd.`id_order_detail`, SUM(osd.`product_quantity`) AS `quantity`')
                ->from('order_slip_detail', 'osd')
                ->innerJoin('order_slip', 'os', 'os.`id_order_slip` = osd.`id_order_slip`')
                ->where('os.`id_order` = '.(int)$order->id)
                ->where('os.`reason_entity_type` = '.(int)RefundPolicy::REASON_ORDER_RETURN)
                ->where($idOrderReturn ? 'os.`reason_id_entity` = '.(int)$idOrderReturn : '1')
                ->groupBy('osd.`id_order_detail`')
        );

        $creditedQuantities = [];
        foreach ($creditedRows as $row) {
            $creditedQuantities[(int)$row['id_order_detail']] = (int)$row['quantity'];
        }

        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('ord.`id_order_detail`, SUM(ord.`received_quantity`) AS `product_quantity_returned`')
                ->from('order_return_detail', 'ord')
                ->innerJoin('order_return', 'orx', 'orx.`id_order_return` = ord.`id_order_return`')
                ->where('orx.`id_order` = '.(int)$order->id)
                ->where($idOrderReturn ? 'orx.`id_order_return` = '.(int)$idOrderReturn : '1')
                ->groupBy('ord.`id_order_detail`')
        );

        $quantities = [];
        foreach ($rows as $row) {
            $idOrderDetail = (int)$row['id_order_detail'];
            $openQuantity = (int)$row['product_quantity_returned'] - (int)($creditedQuantities[$idOrderDetail] ?? 0);
            if ($openQuantity > 0) {
                $quantities[$idOrderDetail] = $openQuantity;
            }
        }

        return $quantities;
    }

    public function getOpenCancellationRows(Order $order): array
    {
        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('oc.`id_order_cancellation`, oc.`status`, oc.`date_add`')
                ->select('SUM(ocd.`product_quantity`) AS `quantity`')
                ->from('order_cancellation', 'oc')
                ->innerJoin('order_cancellation_detail', 'ocd', 'ocd.`id_order_cancellation` = oc.`id_order_cancellation`')
                ->where('oc.`id_order` = '.(int)$order->id)
                ->where('oc.`status` = \''.pSQL(OrderCancellation::STATUS_QUANTITY_CANCELLED).'\'')
                ->groupBy('oc.`id_order_cancellation`')
                ->orderBy('oc.`id_order_cancellation` DESC')
        );

        if (!$rows) {
            return [];
        }

        $creditedRows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('os.`reason_id_entity` AS `id_order_cancellation`, SUM(osd.`product_quantity`) AS `quantity`')
                ->from('order_slip', 'os')
                ->innerJoin('order_slip_detail', 'osd', 'osd.`id_order_slip` = os.`id_order_slip`')
                ->where('os.`id_order` = '.(int)$order->id)
                ->where('os.`reason_entity_type` = '.(int)RefundPolicy::REASON_CANCELLATION)
                ->groupBy('os.`reason_id_entity`')
        );

        $creditedQuantities = [];
        foreach ($creditedRows as $creditedRow) {
            $creditedQuantities[(int)$creditedRow['id_order_cancellation']] = (int)$creditedRow['quantity'];
        }

        $openRows = [];
        foreach ($rows as $row) {
            $idOrderCancellation = (int)$row['id_order_cancellation'];
            $row['credited_quantity'] = (int)($creditedQuantities[$idOrderCancellation] ?? 0);
            if ((int)$row['quantity'] > (int)$row['credited_quantity']) {
                $openRows[] = $row;
            }
        }

        return $openRows;
    }

    public function getUncreditedCancelledQuantities(Order $order, ?int $idOrderCancellation = null): array
    {
        $creditedRows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('osd.`id_order_detail`, SUM(osd.`product_quantity`) AS `quantity`')
                ->from('order_slip_detail', 'osd')
                ->innerJoin('order_slip', 'os', 'os.`id_order_slip` = osd.`id_order_slip`')
                ->where('os.`id_order` = '.(int)$order->id)
                ->where('os.`reason_entity_type` = '.(int)RefundPolicy::REASON_CANCELLATION)
                ->where($idOrderCancellation ? 'os.`reason_id_entity` = '.(int)$idOrderCancellation : '1')
                ->groupBy('osd.`id_order_detail`')
        );

        $creditedQuantities = [];
        foreach ($creditedRows as $row) {
            $creditedQuantities[(int)$row['id_order_detail']] = (int)$row['quantity'];
        }

        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('ocd.`id_order_detail`, SUM(ocd.`product_quantity`) AS `product_quantity_cancelled`')
                ->from('order_cancellation_detail', 'ocd')
                ->innerJoin('order_cancellation', 'oc', 'oc.`id_order_cancellation` = ocd.`id_order_cancellation`')
                ->where('oc.`id_order` = '.(int)$order->id)
                ->where('oc.`status` = \''.pSQL(OrderCancellation::STATUS_QUANTITY_CANCELLED).'\'')
                ->where($idOrderCancellation ? 'oc.`id_order_cancellation` = '.(int)$idOrderCancellation : '1')
                ->groupBy('ocd.`id_order_detail`')
        );

        $quantities = [];
        foreach ($rows as $row) {
            $idOrderDetail = (int)$row['id_order_detail'];
            $openQuantity = (int)$row['product_quantity_cancelled'] - (int)($creditedQuantities[$idOrderDetail] ?? 0);
            if ($openQuantity > 0) {
                $quantities[$idOrderDetail] = $openQuantity;
            }
        }

        return $quantities;
    }

    public function getCreditableServiceCaseRows(Order $order): array
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('osc.`id_order_service_case`, osc.`case_type`, osc.`status`, osc.`date_add`')
                ->select('SUM(oscd.`product_quantity`) AS `quantity`')
                ->from('order_service_case', 'osc')
                ->innerJoin('order_service_case_detail', 'oscd', 'oscd.`id_order_service_case` = osc.`id_order_service_case`')
                ->where('osc.`id_order` = '.(int)$order->id)
                ->groupBy('osc.`id_order_service_case`')
                ->orderBy('osc.`id_order_service_case` DESC')
        );
    }

    public function getServiceCaseOrderDetailIds(int $idOrderServiceCase): array
    {
        $rows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_order_detail`')
                ->from('order_service_case_detail')
                ->where('`id_order_service_case` = '.(int)$idOrderServiceCase)
        );

        return array_map('intval', array_column($rows, 'id_order_detail'));
    }

    public function isValidServiceCaseReference(Order $order, int $idOrderServiceCase): bool
    {
        if ($idOrderServiceCase <= 0) {
            return false;
        }

        return (bool)Db::readOnly()->getValue(
            (new DbQuery())
                ->select('1')
                ->from('order_service_case', 'osc')
                ->innerJoin('order_service_case_detail', 'oscd', 'oscd.`id_order_service_case` = osc.`id_order_service_case`')
                ->where('osc.`id_order_service_case` = '.(int)$idOrderServiceCase)
                ->where('osc.`id_order` = '.(int)$order->id)
        );
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
        ) && (bool)$this->getUncreditedOrderReturnQuantities($order, $idOrderReturn);
    }

    public function isValidCancellationReference(Order $order, int $idOrderCancellation): bool
    {
        if ($idOrderCancellation <= 0) {
            return false;
        }

        return (bool)Db::readOnly()->getValue(
            (new DbQuery())
                ->select('1')
                ->from('order_cancellation')
                ->where('`id_order_cancellation` = '.(int)$idOrderCancellation)
                ->where('`id_order` = '.(int)$order->id)
                ->where('`status` = \''.pSQL(OrderCancellation::STATUS_QUANTITY_CANCELLED).'\'')
        ) && (bool)$this->getUncreditedCancelledQuantities($order, $idOrderCancellation);
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

    /** Credits reserve their amount immediately, including refunds awaiting payment. */
    public function getRemainingOrderCreditAmount(Order $order, int $excludeCancellation = 0): float
    {
        $legacyPaid = (new RefundDiscountService())->getLegacyPaidVoucherAmount($order);
        $paid = $legacyPaid;
        $refundsBySlip = [];
        $otherRefunds = 0.0;
        foreach ($this->getCreditPaymentRows($order) as $payment) {
            $amount = (float)$payment->amount;
            if ((int)$payment->id_currency !== (int)$order->id_currency) {
                if ((float)$payment->conversion_rate <= 0 || (float)$order->conversion_rate <= 0) {
                    throw new PrestaShopException(Tools::displayError('The historical payment exchange rate is missing.'));
                }
                $amount = $amount / (float)$payment->conversion_rate * (float)$order->conversion_rate;
            }
            $status = (string)$payment->status;
            if ($amount > 0 && $status === OrderPayment::STATUS_DONE) {
                $paid += $amount;
            } elseif ($amount < 0 && $status !== OrderPayment::STATUS_FAILED) {
                if ((int)$payment->id_order_slip > 0) {
                    $id = (int)$payment->id_order_slip;
                    $refundsBySlip[$id] = ($refundsBySlip[$id] ?? 0.0) - $amount;
                } else {
                    $otherRefunds -= $amount;
                }
            }
        }
        foreach ($this->getCreditSlipRows($order) as $slip) {
            $refundsBySlip[(int)$slip->id] = max(
                $refundsBySlip[(int)$slip->id] ?? 0.0, $slip->getRefundTotalTaxIncl()
            );
        }
        $paid = min($paid, max(0.0, (float)$order->total_paid_tax_incl) + $legacyPaid);
        $reserved = $this->getReservedCancellationAmount($order, $excludeCancellation);
        // Never round an available balance upwards beyond money actually received.
        return Tools::roundPrice(max(0.0, $paid - array_sum($refundsBySlip) - $otherRefunds - $reserved));
    }

    protected function getCreditPaymentRows(Order $order): array
    {
        $rows = Db::getInstance()->getArray('SELECT * FROM `'._DB_PREFIX_."order_payment` WHERE order_reference = '".pSQL($order->reference)."' FOR UPDATE");
        return ObjectModel::hydrateCollection('OrderPayment', $rows);
    }

    protected function getCreditSlipRows(Order $order): array
    {
        $rows = Db::getInstance()->getArray('SELECT * FROM `'._DB_PREFIX_.'order_slip` WHERE id_order = '.(int)$order->id.' FOR UPDATE');
        return ObjectModel::hydrateCollection('OrderSlip', $rows);
    }

    protected function getReservedCancellationAmount(Order $order, int $excludeCancellation): float
    {
        $query = (new DbQuery())
                ->select('COALESCE(SUM(c.quoted_refund_total_tax_incl), 0)')
                ->from('order_cancellation', 'c')
                ->where('c.id_order = '.(int)$order->id)
                ->where('c.id_order_cancellation <> '.(int)$excludeCancellation)
                ->where("c.status = '".pSQL(OrderCancellation::STATUS_QUANTITY_CANCELLED)."'")
                ->where('NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'order_slip` s WHERE s.id_order = c.id_order AND s.reason_entity_type = '.RefundPolicy::REASON_CANCELLATION.' AND s.reason_id_entity = c.id_order_cancellation)');
        $rows = Db::getInstance()->getArray((string)$query.' FOR UPDATE');
        return (float)reset($rows[0]);
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

        $remaining = [
            'tax_excl' => $this->policy->roundPriceAmount(max(
                0.0,
                (float)$order->total_shipping_tax_excl - (float)$row['tax_excl']
            )),
            'tax_incl' => $this->policy->roundPriceAmount(max(
                0.0,
                (float)$order->total_shipping_tax_incl - (float)$row['tax_incl']
            )),
        ];

        return $remaining;
    }

    protected function getShippingQuantity(Order $order, array $product, int $orderedQuantity, $orderDetailExtension = null): int
    {
        return $this->cancelEligibility->getShippingQuantity($order, $product, $orderedQuantity, $orderDetailExtension);
    }
}
