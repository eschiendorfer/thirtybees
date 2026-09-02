<?php

/**
 * Calculates and applies changes to an existing order while keeping the
 * original product prices. Cancellation policy (for example the 5% fee) is
 * deliberately handled by CancellationQuoteService, not here.
 */
class OrderAdjustmentServiceCore
{
    /** @var RefundPolicy */
    private $refundPolicy;

    /** @var RefundEligibilityService */
    private $refundEligibility;

    public function __construct()
    {
        $this->refundPolicy = new RefundPolicy();
        $this->refundEligibility = new RefundEligibilityService($this->refundPolicy);
    }

    /**
     * @return array{errors: array, adjustment: ?array}
     */
    public function calculate(Order $order, array $quantitiesByOrderDetail, bool $selectedAlreadyCancelled = false): array
    {
        if (!Validate::isLoadedObject($order)) {
            return ['errors' => ['The order could not be loaded.'], 'adjustment' => null];
        }

        $errors = [];
        $lines = [];
        $remainingProducts = [];
        $cancelledGrossTaxIncl = 0.0;
        $cancelledGrossTaxExcl = 0.0;
        $remainingGrossTaxIncl = 0.0;
        $remainingGrossTaxExcl = 0.0;
        $remainingPhysicalGrossTaxIncl = 0.0;
        $hasRemainingProducts = false;

        foreach ($order->getProducts() as $product) {
            $idOrderDetail = (int)$product['id_order_detail'];
            $orderedQuantity = max(0, (int)$product['product_quantity']);
            if ($orderedQuantity <= 0) {
                continue;
            }

            $selectedQuantity = max(0, (int)($quantitiesByOrderDetail[$idOrderDetail] ?? 0));
            $recordedCancelledQuantity = max(0, (int)($product['product_quantity_refunded'] ?? 0));
            $previouslyCancelledQuantity = max(
                0,
                $recordedCancelledQuantity - ($selectedAlreadyCancelled ? $selectedQuantity : 0)
            );
            $quantityBefore = max(0, $orderedQuantity - $previouslyCancelledQuantity);
            if ($selectedQuantity > $quantityBefore) {
                $errors[] = 'A selected cancellation quantity is no longer available.';
                continue;
            }

            $remainingQuantity = $quantityBefore - $selectedQuantity;
            $unitTaxIncl = (float)$product['total_price_tax_incl'] / $orderedQuantity;
            $unitTaxExcl = (float)$product['total_price_tax_excl'] / $orderedQuantity;
            $cancelledLineTaxIncl = $unitTaxIncl * $selectedQuantity;
            $cancelledLineTaxExcl = $unitTaxExcl * $selectedQuantity;
            $remainingLineTaxIncl = $unitTaxIncl * $remainingQuantity;
            $remainingLineTaxExcl = $unitTaxExcl * $remainingQuantity;

            if ($selectedQuantity > 0) {
                $lines[$idOrderDetail] = [
                    'id_order_detail' => $idOrderDetail,
                    'id_product' => (int)$product['product_id'],
                    'id_product_attribute' => (int)$product['product_attribute_id'],
                    'product_name' => (string)$product['product_name'],
                    'product_reference' => (string)$product['product_reference'],
                    'ordered_quantity' => $orderedQuantity,
                    'quantity_before' => $quantityBefore,
                    'cancel_quantity' => $selectedQuantity,
                    'remaining_quantity' => $remainingQuantity,
                    'gross_tax_incl' => $this->refundPolicy->roundPriceAmount($cancelledLineTaxIncl),
                    'gross_tax_excl' => $this->refundPolicy->roundPriceAmount($cancelledLineTaxExcl),
                    'product_amount_tax_incl' => 0.0,
                    'product_amount_tax_excl' => 0.0,
                ];
                $cancelledGrossTaxIncl += $cancelledLineTaxIncl;
                $cancelledGrossTaxExcl += $cancelledLineTaxExcl;
            }

            if ($remainingQuantity > 0) {
                $hasRemainingProducts = true;
                if (empty($product['is_virtual'])) {
                    $remainingProduct = $this->buildShippingProduct($order, $product, $remainingQuantity);
                    $remainingProducts[] = $remainingProduct;
                    $remainingPhysicalGrossTaxIncl += $remainingLineTaxIncl;
                }
                $remainingGrossTaxIncl += $remainingLineTaxIncl;
                $remainingGrossTaxExcl += $remainingLineTaxExcl;
            }
        }

        if (!$lines) {
            $errors[] = 'Please select at least one product.';
        }
        if ($errors) {
            return ['errors' => array_values(array_unique($errors)), 'adjustment' => null];
        }

        $discountRate = $this->getRelevantDiscountRate($order);
        $cancelledProductAmountTaxIncl = $this->refundPolicy->roundAmount(
            $cancelledGrossTaxIncl * (1 - ($discountRate / 100))
        );
        $cancelledProductAmountTaxExcl = $this->refundPolicy->roundPriceAmount(
            $cancelledGrossTaxExcl * (1 - ($discountRate / 100))
        );
        $allocatedTaxIncl = $this->allocateRoundedAmount($lines, $cancelledProductAmountTaxIncl, 'gross_tax_incl');
        $allocatedTaxExcl = $this->allocatePriceAmount($lines, $cancelledProductAmountTaxExcl, 'gross_tax_excl');
        foreach ($lines as $idOrderDetail => &$line) {
            $line['product_amount_tax_incl'] = (float)($allocatedTaxIncl[$idOrderDetail] ?? 0.0);
            $line['product_amount_tax_excl'] = (float)($allocatedTaxExcl[$idOrderDetail] ?? 0.0);
        }
        unset($line);

        $remainingDiscountTaxIncl = $this->refundPolicy->roundAmount($remainingGrossTaxIncl * $discountRate / 100);
        $remainingDiscountTaxExcl = $remainingGrossTaxIncl > 0
            ? $this->refundPolicy->roundPriceAmount($remainingGrossTaxExcl * $remainingDiscountTaxIncl / $remainingGrossTaxIncl)
            : 0.0;

        $shipping = $this->calculateCurrentShipping($order, $remainingProducts, $remainingPhysicalGrossTaxIncl);
        if ($shipping['error'] !== '') {
            return ['errors' => [$shipping['error']], 'adjustment' => null];
        }

        $shippingBefore = $order->hasBeenPaid()
            ? $this->refundEligibility->getRemainingShippingCreditAmounts($order)
            : [
                'tax_incl' => $this->refundPolicy->roundPriceAmount((float)$order->total_shipping_tax_incl),
                'tax_excl' => $this->refundPolicy->roundPriceAmount((float)$order->total_shipping_tax_excl),
            ];
        $shippingAdjustmentTaxIncl = $this->roundSignedAmount(
            (float)$shippingBefore['tax_incl'] - (float)$shipping['tax_incl']
        );
        $shippingAdjustmentTaxExcl = Tools::roundPrice(
            (float)$shippingBefore['tax_excl'] - (float)$shipping['tax_excl']
        );

        $rawAdjustmentTaxIncl = $this->roundSignedAmount(
            $cancelledProductAmountTaxIncl + $shippingAdjustmentTaxIncl
        );
        $rawAdjustmentTaxExcl = Tools::roundPrice(
            $cancelledProductAmountTaxExcl + $shippingAdjustmentTaxExcl
        );
        $negativeFinancialEffect = $rawAdjustmentTaxIncl < 0.0;

        $wrappingTaxIncl = $hasRemainingProducts ? (float)$order->total_wrapping_tax_incl : 0.0;
        $wrappingTaxExcl = $hasRemainingProducts ? (float)$order->total_wrapping_tax_excl : 0.0;
        $calculatedTotalTaxIncl = $this->refundPolicy->roundAmount(max(
            0.0,
            $remainingGrossTaxIncl - $remainingDiscountTaxIncl + (float)$shipping['tax_incl'] + $wrappingTaxIncl
        ));
        $calculatedTotalTaxExcl = $this->refundPolicy->roundPriceAmount(max(
            0.0,
            $remainingGrossTaxExcl - $remainingDiscountTaxExcl + (float)$shipping['tax_excl'] + $wrappingTaxExcl
        ));

        // A cancellation never creates a new receivable. If the recalculated
        // shipping is higher than the cancelled product value, the difference
        // is waived and the employee receives a manual-review case.
        $chargedTotalTaxIncl = min($this->refundPolicy->roundAmount((float)$order->total_paid_tax_incl), $calculatedTotalTaxIncl);
        $chargedTotalTaxExcl = min($this->refundPolicy->roundPriceAmount((float)$order->total_paid_tax_excl), $calculatedTotalTaxExcl);
        $chargedShippingTaxIncl = max(
            0.0,
            (float)$shipping['tax_incl'] - max(0.0, $calculatedTotalTaxIncl - $chargedTotalTaxIncl)
        );
        $chargedShippingTaxExcl = max(
            0.0,
            (float)$shipping['tax_excl'] - max(0.0, $calculatedTotalTaxExcl - $chargedTotalTaxExcl)
        );

        return [
            'errors' => [],
            'adjustment' => [
                'lines' => $lines,
                'discount_rate' => $discountRate,
                'product_amount_tax_incl' => $cancelledProductAmountTaxIncl,
                'product_amount_tax_excl' => $cancelledProductAmountTaxExcl,
                'shipping_before_tax_incl' => (float)$shippingBefore['tax_incl'],
                'shipping_before_tax_excl' => (float)$shippingBefore['tax_excl'],
                'shipping_after_tax_incl' => (float)$shipping['tax_incl'],
                'shipping_after_tax_excl' => (float)$shipping['tax_excl'],
                // Positive means shipping is refunded, negative means newly charged.
                // If the order is fully closed, current shipping is zero and only
                // the still-unrefunded shipping originally paid for this order is returned.
                'shipping_adjustment_tax_incl' => $shippingAdjustmentTaxIncl,
                'shipping_adjustment_tax_excl' => $shippingAdjustmentTaxExcl,
                'adjustment_tax_incl' => max(0.0, $rawAdjustmentTaxIncl),
                'adjustment_tax_excl' => max(0.0, $rawAdjustmentTaxExcl),
                'negative_financial_effect' => $negativeFinancialEffect,
                'requires_manual_review' => $negativeFinancialEffect,
                'remaining' => [
                    'product_total_tax_incl' => $this->refundPolicy->roundPriceAmount($remainingGrossTaxIncl),
                    'product_total_tax_excl' => $this->refundPolicy->roundPriceAmount($remainingGrossTaxExcl),
                    'discount_tax_incl' => $remainingDiscountTaxIncl,
                    'discount_tax_excl' => $remainingDiscountTaxExcl,
                    'shipping_tax_incl' => $this->refundPolicy->roundPriceAmount($chargedShippingTaxIncl),
                    'shipping_tax_excl' => $this->refundPolicy->roundPriceAmount($chargedShippingTaxExcl),
                    'wrapping_tax_incl' => $this->refundPolicy->roundPriceAmount($wrappingTaxIncl),
                    'wrapping_tax_excl' => $this->refundPolicy->roundPriceAmount($wrappingTaxExcl),
                    'total_tax_incl' => $chargedTotalTaxIncl,
                    'total_tax_excl' => $chargedTotalTaxExcl,
                    'has_products' => $hasRemainingProducts,
                ],
            ],
        ];
    }

    /**
     * Applies a previously submitted cancellation to an unpaid order.
     * The caller owns the surrounding transaction.
     */
    public function applyUnpaidCancellation(OrderCancellation $cancellation): array
    {
        $order = new Order((int)$cancellation->id_order);
        if (!Validate::isLoadedObject($order)) {
            return ['success' => false, 'error' => 'The order could not be loaded.'];
        }
        if ($order->hasBeenPaid()) {
            return ['success' => false, 'error' => 'Paid orders require a credit slip.'];
        }
        if ((string)$cancellation->status === OrderCancellation::STATUS_DONE) {
            return ['success' => true, 'adjustment' => null];
        }
        if ((string)$cancellation->status !== OrderCancellation::STATUS_OPEN) {
            return ['success' => false, 'error' => 'Only an open cancellation can adjust an unpaid order.'];
        }

        $quantities = OrderCancellationDetail::getQuantitiesForCancellation((int)$cancellation->id);
        $result = $this->calculate($order, $quantities, true);
        if ($result['errors'] || !$result['adjustment']) {
            return ['success' => false, 'error' => implode(' ', $result['errors'])];
        }
        $adjustment = $result['adjustment'];
        if (!empty($adjustment['requires_manual_review'])) {
            return ['success' => false, 'error' => 'This order adjustment must be checked manually.'];
        }

        $invoices = $order->getInvoicesCollection()->getResults();
        if (count($invoices) > 1) {
            return ['success' => false, 'error' => 'Orders with multiple invoices must be adjusted manually.'];
        }

        $remaining = $adjustment['remaining'];
        if (empty($remaining['has_products'])) {
            // A full cancellation keeps the original order lines and amounts as
            // an audit trail. The cancelled order state performs the stock
            // reversal; line deletion is reserved for partial cancellations.
            if (!$this->setOrderCancelled($order)) {
                return ['success' => false, 'error' => 'The order could not be marked as cancelled.'];
            }
            $cancellation->status = OrderCancellation::STATUS_DONE;
            if (!$cancellation->update(true)) {
                return ['success' => false, 'error' => 'The cancellation could not be completed.'];
            }

            return ['success' => true, 'adjustment' => $adjustment];
        }

        foreach ($adjustment['lines'] as $line) {
            $orderDetail = new OrderDetail((int)$line['id_order_detail']);
            if (!Validate::isLoadedObject($orderDetail) || (int)$orderDetail->id_order !== (int)$order->id) {
                continue;
            }
            $idProduct = (int)$orderDetail->product_id;
            $idProductAttribute = (int)$orderDetail->product_attribute_id;
            $idShop = (int)$orderDetail->id_shop;
            $cancelledQuantity = (int)$line['cancel_quantity'];
            $remainingQuantity = (int)$line['remaining_quantity'];
            if ($remainingQuantity <= 0) {
                if (!$orderDetail->delete()) {
                    return ['success' => false, 'error' => 'A cancelled product line could not be removed.'];
                }
            } else {
                $orderDetail->product_quantity = $remainingQuantity;
                $orderDetail->product_quantity_refunded = 0;
                $orderDetail->product_quantity_reinjected = 0;
                $orderDetail->total_price_tax_incl = Tools::roundPrice((float)$orderDetail->unit_price_tax_incl * $remainingQuantity);
                $orderDetail->total_price_tax_excl = Tools::roundPrice((float)$orderDetail->unit_price_tax_excl * $remainingQuantity);
                if (!$orderDetail->update()) {
                    return ['success' => false, 'error' => 'A cancelled product line could not be updated.'];
                }
            }

            // Cancellation only releases stock reserved by an unshipped order.
            // Physical warehouse movements belong to shipping and returns.
            if (!$this->restoreAvailableQuantity($idProduct, $idProductAttribute, $idShop, $cancelledQuantity)) {
                return ['success' => false, 'error' => 'The available product quantity could not be updated.'];
            }
        }

        if (!$this->updateOrderCartRules($order, (float)$remaining['discount_tax_incl'], (float)$remaining['discount_tax_excl'])) {
            return ['success' => false, 'error' => 'The order discount could not be updated.'];
        }

        $order->total_products = (float)$remaining['product_total_tax_excl'];
        $order->total_products_wt = (float)$remaining['product_total_tax_incl'];
        $order->total_discounts = (float)$remaining['discount_tax_incl'];
        $order->total_discounts_tax_incl = (float)$remaining['discount_tax_incl'];
        $order->total_discounts_tax_excl = (float)$remaining['discount_tax_excl'];
        $order->total_shipping = (float)$remaining['shipping_tax_incl'];
        $order->total_shipping_tax_incl = (float)$remaining['shipping_tax_incl'];
        $order->total_shipping_tax_excl = (float)$remaining['shipping_tax_excl'];
        $order->total_wrapping = (float)$remaining['wrapping_tax_incl'];
        $order->total_wrapping_tax_incl = (float)$remaining['wrapping_tax_incl'];
        $order->total_wrapping_tax_excl = (float)$remaining['wrapping_tax_excl'];
        $order->total_paid = (float)$remaining['total_tax_incl'];
        $order->total_paid_tax_incl = (float)$remaining['total_tax_incl'];
        $order->total_paid_tax_excl = (float)$remaining['total_tax_excl'];
        if (!$order->update()) {
            return ['success' => false, 'error' => 'The order totals could not be updated.'];
        }

        if ($invoices) {
            /** @var OrderInvoice $invoice */
            $invoice = reset($invoices);
            $invoice->total_products = (float)$remaining['product_total_tax_excl'];
            $invoice->total_products_wt = (float)$remaining['product_total_tax_incl'];
            $invoice->total_discount_tax_incl = (float)$remaining['discount_tax_incl'];
            $invoice->total_discount_tax_excl = (float)$remaining['discount_tax_excl'];
            $invoice->total_shipping_tax_incl = (float)$remaining['shipping_tax_incl'];
            $invoice->total_shipping_tax_excl = (float)$remaining['shipping_tax_excl'];
            $invoice->total_wrapping_tax_incl = (float)$remaining['wrapping_tax_incl'];
            $invoice->total_wrapping_tax_excl = (float)$remaining['wrapping_tax_excl'];
            $invoice->total_paid_tax_incl = (float)$remaining['total_tax_incl'];
            $invoice->total_paid_tax_excl = (float)$remaining['total_tax_excl'];
            if (!$invoice->update()) {
                return ['success' => false, 'error' => 'The invoice totals could not be updated.'];
            }
        }

        $cancellation->status = OrderCancellation::STATUS_DONE;
        if (!$cancellation->update(true)) {
            return ['success' => false, 'error' => 'The cancellation could not be completed.'];
        }

        return ['success' => true, 'adjustment' => $adjustment];
    }

    private function restoreAvailableQuantity(
        int $idProduct,
        int $idProductAttribute,
        int $idShop,
        int $quantity
    ): bool
    {
        if ($quantity <= 0) {
            return true;
        }

        if (StockAvailable::dependsOnStock($idProduct, $idShop)) {
            return StockAvailable::synchronize($idProduct, $idShop);
        }

        return StockAvailable::updateQuantity($idProduct, $idProductAttribute, $quantity, $idShop);
    }

    private function getRelevantDiscountRate(Order $order): float
    {
        foreach ($order->getCartRules() as $orderCartRule) {
            $cartRule = new CartRule((int)$orderCartRule['id_cart_rule']);
            if (Validate::isLoadedObject($cartRule) && abs((float)$cartRule->reduction_percent - 10.0) < 0.0001) {
                return 10.0;
            }
        }

        $historicalRate = $this->getOrderPercentCartRuleRate($order);
        return abs($historicalRate - 10.0) <= 0.1 ? 10.0 : 0.0;
    }

    public function getOrderPercentCartRuleRate(Order $order): float
    {
        $productTotal = (float)$order->total_products_wt;
        if ($productTotal <= 0.0) {
            return 0.0;
        }

        $productDiscount = 0.0;
        $shippingDiscountLeft = max(0.0, (float)$order->total_shipping_tax_incl);

        foreach ($order->getCartRules() as $orderCartRule) {
            $discount = max(0.0, (float)$orderCartRule['value']);

            if (!empty($orderCartRule['free_shipping']) && $shippingDiscountLeft > 0.0) {
                $shippingDiscount = min($discount, $shippingDiscountLeft);
                $discount -= $shippingDiscount;
                $shippingDiscountLeft -= $shippingDiscount;
            }

            $productDiscount += max(0.0, $discount);
        }

        return $this->refundPolicy->normalizePercent(($productDiscount / $productTotal) * 100);
    }

    private function calculateCurrentShipping(Order $order, array $products, float $productTotalTaxIncl): array
    {
        if (!$products) {
            return ['tax_incl' => 0.0, 'tax_excl' => 0.0, 'error' => ''];
        }

        $originalCarrier = new CarrierCore((int)$order->id_carrier, (int)$order->id_lang);
        if (!Validate::isLoadedObject($originalCarrier)) {
            return ['tax_incl' => 0.0, 'tax_excl' => 0.0, 'error' => 'The original carrier could not be loaded.'];
        }
        $currentCarrierId = (int)Db::readOnly()->getValue(
            (new DbQuery())
                ->select('c.`id_carrier`')
                ->from('carrier', 'c')
                ->innerJoin('carrier_shop', 'cs', 'cs.`id_carrier` = c.`id_carrier` AND cs.`id_shop` = '.(int)$order->id_shop)
                ->where('c.`id_reference` = '.(int)$originalCarrier->id_reference)
                ->where('c.`deleted` = 0')
                ->where('c.`active` = 1')
                ->orderBy('c.`id_carrier` DESC')
        );
        $carrier = $currentCarrierId > 0
            ? new CarrierCore($currentCarrierId, (int)$order->id_lang)
            : $originalCarrier;
        if (!Validate::isLoadedObject($carrier)) {
            $carrier = $originalCarrier;
        }
        if ((int)$carrier->is_free === 1 || $carrier->getShippingMethod() === Carrier::SHIPPING_METHOD_FREE) {
            return ['tax_incl' => 0.0, 'tax_excl' => 0.0, 'error' => ''];
        }
        if ((int)$carrier->shipping_external === 1) {
            return ['tax_incl' => 0.0, 'tax_excl' => 0.0, 'error' => 'Shipping for this carrier must be checked manually.'];
        }

        $idZone = (int)Address::getZoneById((int)$order->id_address_delivery);
        if ($idZone <= 0) {
            return ['tax_incl' => 0.0, 'tax_excl' => 0.0, 'error' => 'The delivery zone could not be determined.'];
        }

        $shippingMethod = $carrier->getShippingMethod();
        if ($shippingMethod === Carrier::SHIPPING_METHOD_WEIGHT) {
            $weight = 0.0;
            foreach ($products as $product) {
                $weight += (float)$product['weight'] * (int)$product['cart_quantity'];
            }
            $shippingTaxExcl = (float)$carrier->getDeliveryPriceByWeight($weight, $idZone);
        } else {
            $shippingTaxExcl = (float)$carrier->getDeliveryPriceByPrice(
                $productTotalTaxIncl,
                $idZone,
                (int)$order->id_currency
            );
        }

        if ($carrier->shipping_handling) {
            $shippingTaxExcl += (float)Configuration::get('PS_SHIPPING_HANDLING');
        }
        foreach ($products as $product) {
            $shippingTaxExcl += (float)$product['additional_shipping_cost'] * (int)$product['cart_quantity'];
        }
        $shippingTaxExcl = Tools::convertPrice(
            $shippingTaxExcl,
            Currency::getCurrencyInstance((int)$order->id_currency)
        );

        $address = Address::initialize((int)$order->id_address_delivery);
        $taxRate = Validate::isLoadedObject($address) ? (float)$carrier->getTaxesRate($address) : (float)$order->carrier_tax_rate;
        if ((int)$carrier->prices_with_tax === 1) {
            $shippingTaxIncl = $shippingTaxExcl;
            $shippingTaxExcl = $taxRate > 0 ? $shippingTaxIncl / (1 + ($taxRate / 100)) : $shippingTaxIncl;
        } else {
            $shippingTaxIncl = $shippingTaxExcl * (1 + ($taxRate / 100));
        }

        return [
            'tax_incl' => $this->refundPolicy->roundAmount($shippingTaxIncl),
            'tax_excl' => $this->refundPolicy->roundPriceAmount($shippingTaxExcl),
            'error' => '',
        ];
    }

    private function buildShippingProduct(Order $order, array $product, int $quantity): array
    {
        $idProduct = (int)$product['product_id'];
        $productObject = new Product($idProduct, false, (int)$order->id_lang, (int)$order->id_shop);

        return [
            'id_product' => $idProduct,
            'id_product_attribute' => (int)$product['product_attribute_id'],
            'id_shop' => (int)$order->id_shop,
            'id_address_delivery' => (int)$order->id_address_delivery,
            'cart_quantity' => $quantity,
            'quantity' => $quantity,
            'is_virtual' => (int)($product['is_virtual'] ?? 0),
            'weight' => (float)($product['product_weight'] ?? 0.0),
            'additional_shipping_cost' => Validate::isLoadedObject($productObject)
                ? (float)$productObject->additional_shipping_cost
                : 0.0,
        ];
    }

    private function allocateRoundedAmount(array $lines, float $target, string $weightKey): array
    {
        $unit = RefundPolicy::ROUNDING_UNIT;
        $targetUnits = (int)round($target / $unit);
        $totalWeight = array_sum(array_column($lines, $weightKey));
        $result = [];
        $allocatedUnits = 0;
        $ids = array_keys($lines);

        foreach ($ids as $index => $idOrderDetail) {
            if ($index === count($ids) - 1) {
                $units = max(0, $targetUnits - $allocatedUnits);
            } else {
                $units = $totalWeight > 0
                    ? (int)round($targetUnits * (float)$lines[$idOrderDetail][$weightKey] / $totalWeight)
                    : 0;
                $units = min($units, max(0, $targetUnits - $allocatedUnits));
            }
            $allocatedUnits += $units;
            $result[$idOrderDetail] = Tools::roundPrice($units * $unit);
        }

        return $result;
    }

    private function roundSignedAmount(float $amount): float
    {
        return Tools::roundPrice(round($amount / RefundPolicy::ROUNDING_UNIT) * RefundPolicy::ROUNDING_UNIT);
    }

    private function allocatePriceAmount(array $lines, float $target, string $weightKey): array
    {
        $totalWeight = array_sum(array_column($lines, $weightKey));
        $result = [];
        $allocated = 0.0;
        $ids = array_keys($lines);
        foreach ($ids as $index => $idOrderDetail) {
            $amount = $index === count($ids) - 1
                ? max(0.0, $target - $allocated)
                : ($totalWeight > 0 ? $target * (float)$lines[$idOrderDetail][$weightKey] / $totalWeight : 0.0);
            $amount = $this->refundPolicy->roundPriceAmount($amount);
            $allocated += $amount;
            $result[$idOrderDetail] = $amount;
        }

        return $result;
    }

    private function updateOrderCartRules(Order $order, float $discountTaxIncl, float $discountTaxExcl): bool
    {
        $percentageRules = [];
        foreach ($order->getCartRules() as $row) {
            $cartRule = new CartRule((int)$row['id_cart_rule']);
            if (Validate::isLoadedObject($cartRule) && abs((float)$cartRule->reduction_percent - 10.0) < 0.0001) {
                $percentageRules[] = new OrderCartRule((int)$row['id_order_cart_rule']);
            }
        }
        if (!$percentageRules) {
            return $discountTaxIncl <= 0.0;
        }

        foreach ($percentageRules as $index => $orderCartRule) {
            $orderCartRule->value = $index === 0 ? $discountTaxIncl : 0.0;
            $orderCartRule->value_tax_excl = $index === 0 ? $discountTaxExcl : 0.0;
            if (!$orderCartRule->update()) {
                return false;
            }
        }

        return true;
    }

    private function setOrderCancelled(Order $order): bool
    {
        if ((int)$order->current_state === (int)Configuration::get('PS_OS_CANCELED')) {
            return true;
        }
        $history = new OrderHistory();
        $history->id_order = (int)$order->id;
        $history->id_employee = Context::getContext()->employee instanceof Employee
            ? (int)Context::getContext()->employee->id
            : 0;
        $history->changeIdOrderState((int)Configuration::get('PS_OS_CANCELED'), $order);

        return $history->add(false);
    }
}
