<?php

class RefundCalculatorCore
{
    /** @var RefundPolicy */
    protected $policy;

    /** @var RefundEligibilityService */
    protected $eligibility;

    public function __construct(?RefundPolicy $policy = null, ?RefundEligibilityService $eligibility = null)
    {
        $this->policy = $policy ?: new RefundPolicy();
        $this->eligibility = $eligibility ?: new RefundEligibilityService($this->policy);
    }

    public function buildCreditSlipRequest(Order $order, array $input): array
    {
        $errors = [];
        $displayIncludesTax = !empty($input['display_includes_tax']);
        $refundMethod = (string)($input['refund_method'] ?? '');
        $action = isset($input['action']) ? (string)$input['action'] : null;
        if ($refundMethod === '') {
            $errors[] = 'Please select a refund method.';
        } elseif (!in_array($refundMethod, $this->policy->getValidRefundMethods(), true)) {
            $errors[] = 'The selected refund method is invalid.';
        }

        if (
            $refundMethod === RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT
            && !$this->policy->isOriginalPaymentRefundAvailable($order)
        ) {
            $errors[] = 'Refund to the original payment method is not available yet.';
        }

        $rawReasonEntityType = $input['reason_entity_type'] ?? '';
        $reasonEntityType = $this->policy->normalizeReasonEntityType($rawReasonEntityType);
        if ($rawReasonEntityType === '') {
            $errors[] = 'Please select a credit reason.';
        } elseif (!$this->policy->isValidReasonEntityTypeInput($rawReasonEntityType)) {
            $errors[] = 'The selected credit reason is invalid.';
        }

        $reasonIdEntity = (int)($input['reason_id_entity'] ?? 0);
        if ($reasonEntityType === RefundPolicy::REASON_MANUAL) {
            $reasonIdEntity = 0;
        } elseif ($reasonEntityType === RefundPolicy::REASON_ORDER_RETURN) {
            if (!$this->eligibility->isValidOrderReturn($order, $reasonIdEntity)) {
                $errors[] = 'The selected order return is invalid.';
            }
        } elseif ($reasonEntityType === RefundPolicy::REASON_CANCELLATION) {
            if (!$this->eligibility->isValidCancellationReference($order, $reasonIdEntity)) {
                $errors[] = 'The selected cancellation reference is invalid.';
            }
        } elseif ($reasonEntityType === RefundPolicy::REASON_SERVICE_CASE) {
            if (!$this->eligibility->isValidServiceCaseReference($order, $reasonIdEntity)) {
                $errors[] = 'The selected service case is invalid.';
            }
        }

        $refunds = (array)($input['product_amounts'] ?? []);
        $refundQuantities = (array)($input['product_quantities'] ?? []);
        $serviceCaseOrderDetailIds = $reasonEntityType === RefundPolicy::REASON_SERVICE_CASE
            ? $this->eligibility->getServiceCaseOrderDetailIds($reasonIdEntity)
            : [];
        $orderReturnQuantities = $reasonEntityType === RefundPolicy::REASON_ORDER_RETURN
            ? $this->eligibility->getUncreditedOrderReturnQuantities($order, $reasonIdEntity)
            : [];
        $cancellationQuantities = $reasonEntityType === RefundPolicy::REASON_CANCELLATION
            ? $this->eligibility->getUncreditedCancelledQuantities($order, $reasonIdEntity)
            : [];
        if ($reasonEntityType === RefundPolicy::REASON_ORDER_RETURN && !$orderReturnQuantities) {
            $errors[] = 'There are no open return quantities for this order.';
        }
        if ($reasonEntityType === RefundPolicy::REASON_CANCELLATION && !$cancellationQuantities) {
            $errors[] = 'There are no open cancellation quantities for this order.';
        }
        $orderDetailList = [];
        $fullQuantityList = [];
        $productTotalTaxExcl = 0.0;
        $productTotalTaxIncl = 0.0;

        foreach ($refunds as $idOrderDetail => $providedAmount) {
            $idOrderDetail = (int)$idOrderDetail;
            $refundAmount = $this->policy->roundPriceAmount(Tools::parseNumber((string)$providedAmount));
            if ($refundAmount <= 0.0) {
                continue;
            }

            $orderDetail = new OrderDetail($idOrderDetail);
            if (!Validate::isLoadedObject($orderDetail) || (int)$orderDetail->id_order !== (int)$order->id) {
                $errors[] = 'The selected product line is invalid.';
                continue;
            }

            if (
                $reasonEntityType === RefundPolicy::REASON_SERVICE_CASE
                && !in_array($idOrderDetail, $serviceCaseOrderDetailIds, true)
            ) {
                $errors[] = 'The selected product line does not belong to the selected service case.';
                continue;
            }

            $remainingAmounts = $this->eligibility->getOrderDetailRemainingCreditAmounts($orderDetail);
            $amountRefundable = $displayIncludesTax ? $remainingAmounts['tax_incl'] : $remainingAmounts['tax_excl'];
            if ($refundAmount > $amountRefundable + 0.000001) {
                $errors[] = 'Refund amount exceeds the remaining refundable amount.';
                continue;
            }

            $quantity = max(0, (int)($refundQuantities[$idOrderDetail] ?? 0));
            if ($reasonEntityType === RefundPolicy::REASON_ORDER_RETURN) {
                $openReturnQuantity = (int)($orderReturnQuantities[$idOrderDetail] ?? 0);
                if ($quantity <= 0 || $quantity > $openReturnQuantity) {
                    $errors[] = 'The selected return quantity is invalid.';
                    continue;
                }
            } elseif ($reasonEntityType === RefundPolicy::REASON_CANCELLATION) {
                $openCancellationQuantity = (int)($cancellationQuantities[$idOrderDetail] ?? 0);
                if ($quantity <= 0 || $quantity > $openCancellationQuantity) {
                    $errors[] = 'The selected cancellation quantity is invalid.';
                    continue;
                }
            }
            if ($reasonEntityType === RefundPolicy::REASON_SERVICE_CASE) {
                $quantity = 0;
            }

            $taxAmounts = $this->getTaxAmountsForDisplayAmount(
                $refundAmount,
                (float)$orderDetail->tax_rate,
                $displayIncludesTax
            );

            $productTotalTaxExcl += $taxAmounts['tax_excl'];
            $productTotalTaxIncl += $taxAmounts['tax_incl'];
            $fullQuantityList[$idOrderDetail] = $quantity;
            $orderDetailList[$idOrderDetail] = [
                'id_order_detail' => $idOrderDetail,
                'quantity' => $quantity,
                'amount' => $refundAmount,
                'unit_price' => $quantity > 0 ? Tools::roundPrice($refundAmount / $quantity) : $refundAmount,
            ];
        }

        $shippingAdjustmentAmount = Tools::roundPrice(
            Tools::parseNumber((string)($input['shipping_adjustment'] ?? 0))
        );
        $shippingCostAmount = max(0.0, $shippingAdjustmentAmount);
        $shippingChargeAmount = max(0.0, -$shippingAdjustmentAmount);
        $shippingTaxExcl = 0.0;
        $shippingTaxIncl = 0.0;
        if ($reasonEntityType === RefundPolicy::REASON_SERVICE_CASE && abs($shippingAdjustmentAmount) > 0.000001) {
            $errors[] = 'Service case credits only support product amounts.';
        }
        if ($shippingCostAmount > 0.0) {
            $remainingShipping = $this->eligibility->getRemainingShippingCreditAmounts($order);
            $shippingRefundable = $displayIncludesTax ? $remainingShipping['tax_incl'] : $remainingShipping['tax_excl'];
            if ($shippingCostAmount > $shippingRefundable + 0.000001) {
                $errors[] = 'Refund shipping amount exceeds the remaining refundable shipping amount.';
            } else {
                $shippingAmounts = $this->getTaxAmountsForDisplayAmount(
                    $shippingCostAmount,
                    (float)$order->carrier_tax_rate,
                    $displayIncludesTax
                );
                $shippingTaxExcl = $shippingAmounts['tax_excl'];
                $shippingTaxIncl = $shippingAmounts['tax_incl'];
            }
        }

        if ($errors) {
            return ['errors' => $errors, 'request' => null];
        }

        if ($productTotalTaxIncl <= 0.0 && $shippingTaxIncl <= 0.0) {
            return [
                'errors' => ['Please enter an amount to proceed with your refund.'],
                'request' => null,
            ];
        }

        if (
            $reasonEntityType === RefundPolicy::REASON_SERVICE_CASE
            && (
                $this->policy->roundPriceAmount(Tools::parseNumber((string)($input['cart_rule_adjustment'] ?? 0))) > 0.0
                || $this->policy->roundPriceAmount(Tools::parseNumber((string)($input['fee_adjustment'] ?? 0))) > 0.0
            )
        ) {
            return [
                'errors' => ['Service case credits only support product amounts.'],
                'request' => null,
            ];
        }

        $cartRuleAdjustment = $this->resolveCartRuleAdjustment(
            $order,
            $input['cart_rule_adjustment'] ?? null,
            $productTotalTaxExcl,
            $productTotalTaxIncl,
            $displayIncludesTax
        );

        $shippingChargeAdjustment = $this->resolveDisplayAdjustment(
            $shippingChargeAmount,
            $productTotalTaxExcl + $shippingTaxExcl - $cartRuleAdjustment['tax_excl'],
            $productTotalTaxIncl + $shippingTaxIncl - $cartRuleAdjustment['tax_incl'],
            $displayIncludesTax
        );

        $intermediateTaxExcl = max(0.0, $productTotalTaxExcl + $shippingTaxExcl - $cartRuleAdjustment['tax_excl'] - $shippingChargeAdjustment['tax_excl']);
        $intermediateTaxIncl = max(0.0, $productTotalTaxIncl + $shippingTaxIncl - $cartRuleAdjustment['tax_incl'] - $shippingChargeAdjustment['tax_incl']);
        $feeAdjustment = $this->resolveFeeAdjustment(
            $reasonEntityType,
            $refundMethod,
            $input['fee_adjustment'] ?? null,
            $intermediateTaxExcl,
            $intermediateTaxIncl,
            $displayIncludesTax,
            $action
        );

        return [
            'errors' => [],
            'request' => [
                'order_detail_list' => $orderDetailList,
                'full_quantity_list' => $fullQuantityList,
                'shipping_cost_amount' => $shippingCostAmount,
                'add_tax' => !$displayIncludesTax,
                'effective_tax_excl' => $this->policy->roundAmount($intermediateTaxExcl - $feeAdjustment['tax_excl']),
                'effective_tax_incl' => $this->policy->roundAmount($intermediateTaxIncl - $feeAdjustment['tax_incl']),
                'metadata' => [
                    'reason_entity_type' => $reasonEntityType,
                    'reason_id_entity' => $reasonIdEntity,
                    'adjustment_cart_rule_tax_excl' => $cartRuleAdjustment['tax_excl'],
                    'adjustment_cart_rule_tax_incl' => $cartRuleAdjustment['tax_incl'],
                    'adjustment_fee_tax_excl' => $feeAdjustment['tax_excl'],
                    'adjustment_fee_tax_incl' => $feeAdjustment['tax_incl'],
                    'adjustment_shipping_charge_tax_excl' => $shippingChargeAdjustment['tax_excl'],
                    'adjustment_shipping_charge_tax_incl' => $shippingChargeAdjustment['tax_incl'],
                ],
            ],
        ];
    }

    public function buildCreditSuggestions(Order $order, array $products, int $idLang, bool $displayIncludesTax): array
    {
        $orderReturnSuggestions = [];
        foreach ($this->eligibility->getOpenOrderReturnRows($order, $idLang) as $row) {
            $idOrderReturn = (int)$row['id_order_return'];
            $orderReturnSuggestions[$idOrderReturn] = $this->buildOrderReturnCreditSuggestion(
                $order,
                $products,
                $idOrderReturn,
                $displayIncludesTax
            );
        }

        $cancellationSuggestions = [];
        foreach ($this->eligibility->getOpenCancellationRows($order) as $row) {
            $idOrderCancellation = (int)$row['id_order_cancellation'];
            $cancellationSuggestions[$idOrderCancellation] = $this->buildCancellationCreditSuggestion(
                $order,
                $products,
                $displayIncludesTax,
                $idOrderCancellation
            );
        }

        $serviceCaseScopes = [];
        foreach ($this->eligibility->getCreditableServiceCaseRows($order) as $row) {
            $idOrderServiceCase = (int)$row['id_order_service_case'];
            $serviceCaseScopes[$idOrderServiceCase] = [
                'status' => (string)$row['status'],
                'products' => array_fill_keys(
                    $this->eligibility->getServiceCaseOrderDetailIds($idOrderServiceCase),
                    ['quantity' => 0]
                ),
            ];
        }

        return [
            'default_cart_rule_rate' => $this->getOrderPercentCartRuleRate($order),
            'default_fee_rate' => $this->policy->getSuggestedFeeRate(
                RefundPolicy::REASON_ORDER_RETURN,
                RefundPolicy::REFUND_METHOD_NONE
            ),
            'default_fee_rates' => [
                RefundPolicy::REFUND_METHOD_NONE => $this->policy->getSuggestedFeeRate(
                    RefundPolicy::REASON_ORDER_RETURN,
                    RefundPolicy::REFUND_METHOD_NONE
                ),
                RefundPolicy::REFUND_METHOD_STORE_CREDIT => $this->policy->getSuggestedFeeRate(
                    RefundPolicy::REASON_ORDER_RETURN,
                    RefundPolicy::REFUND_METHOD_STORE_CREDIT
                ),
                RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT => $this->policy->getSuggestedFeeRate(
                    RefundPolicy::REASON_ORDER_RETURN,
                    RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT
                ),
            ],
            'round_unit' => RefundPolicy::ROUNDING_UNIT,
            RefundPolicy::REASON_KEY_ORDER_RETURN => $orderReturnSuggestions,
            RefundPolicy::REASON_KEY_CANCELLATION => $cancellationSuggestions,
            RefundPolicy::REASON_KEY_SERVICE_CASE => $serviceCaseScopes,
        ];
    }

    public function buildCancellationCreditSuggestion(
        Order $order,
        array $products,
        bool $displayIncludesTax,
        ?int $idOrderCancellation = null
    ): array {
        $quantities = $this->eligibility->getUncreditedCancelledQuantities($order, $idOrderCancellation);
        $cancellation = new OrderCancellation((int)$idOrderCancellation);
        $adjustmentResult = (new OrderAdjustmentService())->calculate($order, $quantities, true);
        if (!Validate::isLoadedObject($cancellation) || $adjustmentResult['errors'] || !$adjustmentResult['adjustment']) {
            return [
                'products' => [],
                'shipping' => ['tax_excl' => 0.0, 'tax_incl' => 0.0, 'amount' => 0.0],
                'shipping_charge_adjustment' => ['amount' => 0.0],
                'cart_rule_adjustment' => ['amount' => 0.0],
                'fee_adjustment' => ['amount' => 0.0],
            ];
        }
        $adjustment = $adjustmentResult['adjustment'];
        $suggestedProducts = [];

        foreach ($products as $product) {
            $idOrderDetail = (int)$product['id_order_detail'];
            $orderedQuantity = max(0, (int)$product['product_quantity']);

            if ($orderedQuantity <= 0) {
                continue;
            }

            $cancelQuantity = min($orderedQuantity, (int)($quantities[$idOrderDetail] ?? 0));
            if ($cancelQuantity <= 0) {
                continue;
            }

            $line = (array)($adjustment['lines'][$idOrderDetail] ?? []);
            $amountTaxExcl = $this->policy->roundPriceAmount((float)($line['product_amount_tax_excl'] ?? 0.0));
            $amountTaxIncl = $this->policy->roundPriceAmount((float)($line['product_amount_tax_incl'] ?? 0.0));

            $amountTaxExcl = min(
                $amountTaxExcl,
                $this->policy->roundPriceAmount(max(0.0, (float)$product['amount_refundable']))
            );
            $amountTaxIncl = min(
                $amountTaxIncl,
                $this->policy->roundPriceAmount(max(0.0, (float)$product['amount_refundable_tax_incl']))
            );
            $amount = $displayIncludesTax ? $amountTaxIncl : $amountTaxExcl;

            $suggestedProducts[$idOrderDetail] = [
                'quantity' => $cancelQuantity,
                'ordered_quantity' => $orderedQuantity,
                'amount' => $amount,
                'amount_tax_excl' => $amountTaxExcl,
                'amount_tax_incl' => $amountTaxIncl,
            ];
        }

        $shipping = ['tax_excl' => 0.0, 'tax_incl' => 0.0, 'amount' => 0.0];
        $shippingChargeAdjustment = ['amount' => 0.0];
        if ((float)$adjustment['shipping_adjustment_tax_incl'] > 0.0) {
            $shipping = [
                'tax_excl' => max(0.0, (float)$adjustment['shipping_adjustment_tax_excl']),
                'tax_incl' => max(0.0, (float)$adjustment['shipping_adjustment_tax_incl']),
                'amount' => $displayIncludesTax
                    ? max(0.0, (float)$adjustment['shipping_adjustment_tax_incl'])
                    : max(0.0, (float)$adjustment['shipping_adjustment_tax_excl']),
            ];
        } elseif ((float)$adjustment['shipping_adjustment_tax_incl'] < 0.0) {
            $shippingChargeAdjustment['amount'] = $displayIncludesTax
                ? abs((float)$adjustment['shipping_adjustment_tax_incl'])
                : abs((float)$adjustment['shipping_adjustment_tax_excl']);
        }

        $quoteService = new CancellationQuoteService();
        $feeAdjustments = [];
        foreach ([RefundPolicy::REFUND_METHOD_STORE_CREDIT, RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT] as $method) {
            if ($method === RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT && !$this->policy->isOriginalPaymentRefundAvailable($order)) {
                continue;
            }
            $quoteResult = $quoteService->buildAppliedCancellationQuote($cancellation, $method);
            $feeTaxIncl = !$quoteResult['errors'] && $quoteResult['quote']
                ? (float)$quoteResult['quote']['fee_tax_incl']
                : 0.0;
            $productTaxIncl = (float)$adjustment['product_amount_tax_incl'];
            $productTaxExcl = (float)$adjustment['product_amount_tax_excl'];
            $feeAdjustments[$method] = [
                'amount' => $displayIncludesTax || $productTaxIncl <= 0.0
                    ? $feeTaxIncl
                    : $this->policy->roundPriceAmount($productTaxExcl * $feeTaxIncl / $productTaxIncl),
            ];
        }
        $requestedMethod = (string)$cancellation->requested_refund_method;
        $selectedFeeAdjustment = (array)($feeAdjustments[$requestedMethod] ?? ['amount' => 0.0]);

        return [
            'products' => $suggestedProducts,
            'shipping' => $shipping,
            'shipping_charge_adjustment' => $shippingChargeAdjustment,
            // Product values already include the relevant 10% order discount.
            'cart_rule_adjustment' => ['amount' => 0.0],
            'fee_adjustment' => $selectedFeeAdjustment,
            'fee_adjustments' => $feeAdjustments,
            'refund_method' => $requestedMethod,
        ];
    }

    public function buildOrderReturnCreditSuggestion(
        Order $order,
        array $products,
        int $idOrderReturn,
        bool $displayIncludesTax
    ): array {
        $quantities = $this->eligibility->getUncreditedOrderReturnQuantities($order, $idOrderReturn);
        $allProductLinesReturned = true;
        $suggestedProducts = [];
        $productsDisplayTotal = 0.0;

        foreach ($products as $product) {
            $idOrderDetail = (int)$product['id_order_detail'];
            $orderedQuantity = max(0, (int)$product['product_quantity']);

            if ($orderedQuantity <= 0) {
                continue;
            }

            $returnQuantity = min($orderedQuantity, (int)($quantities[$idOrderDetail] ?? 0));
            if ($returnQuantity < $orderedQuantity) {
                $allProductLinesReturned = false;
            }

            if ($returnQuantity <= 0) {
                continue;
            }

            $amountTaxExcl = $this->policy->roundPriceAmount(
                ((float)$product['total_price_tax_excl'] / $orderedQuantity) * $returnQuantity
            );
            $amountTaxIncl = $this->policy->roundPriceAmount(
                ((float)$product['total_price_tax_incl'] / $orderedQuantity) * $returnQuantity
            );

            $amountTaxExcl = min(
                $amountTaxExcl,
                $this->policy->roundPriceAmount(max(0.0, (float)$product['amount_refundable']))
            );
            $amountTaxIncl = min(
                $amountTaxIncl,
                $this->policy->roundPriceAmount(max(0.0, (float)$product['amount_refundable_tax_incl']))
            );
            $amount = $displayIncludesTax ? $amountTaxIncl : $amountTaxExcl;
            $productsDisplayTotal += $amount;

            $suggestedProducts[$idOrderDetail] = [
                'quantity' => $returnQuantity,
                'ordered_quantity' => $orderedQuantity,
                'amount' => $amount,
                'amount_tax_excl' => $amountTaxExcl,
                'amount_tax_incl' => $amountTaxIncl,
            ];
        }

        $shipping = ['tax_excl' => 0.0, 'tax_incl' => 0.0, 'amount' => 0.0];
        if ($allProductLinesReturned) {
            $remainingShipping = $this->eligibility->getRemainingShippingCreditAmounts($order);
            $shipping = [
                'tax_excl' => $remainingShipping['tax_excl'],
                'tax_incl' => $remainingShipping['tax_incl'],
                'amount' => $displayIncludesTax ? $remainingShipping['tax_incl'] : $remainingShipping['tax_excl'],
            ];
        }

        $cartRuleAdjustment = min(
            $productsDisplayTotal,
            $this->policy->roundPriceAmount($productsDisplayTotal * $this->getOrderPercentCartRuleRate($order) / 100)
        );
        $subtotal = max(0.0, $productsDisplayTotal + (float)$shipping['amount'] - $cartRuleAdjustment);
        $feeAdjustment = min(
            $subtotal,
            $this->policy->roundPriceAmount($subtotal * $this->policy->getSuggestedFeeRate(
                RefundPolicy::REASON_ORDER_RETURN,
                RefundPolicy::REFUND_METHOD_NONE
            ) / 100)
        );

        return [
            'products' => $suggestedProducts,
            'shipping' => $shipping,
            'cart_rule_adjustment' => ['amount' => $cartRuleAdjustment],
            'fee_adjustment' => ['amount' => $feeAdjustment],
        ];
    }

    public function getOrderPercentCartRuleRate(Order $order): float
    {
        return (new OrderAdjustmentService())->getOrderPercentCartRuleRate($order);
    }

    public function getTaxAmountsForDisplayAmount(float $amount, float $taxRate, bool $displayIncludesTax): array
    {
        $tax = new Tax();
        $tax->rate = $taxRate;
        $taxCalculator = new TaxCalculator([$tax]);

        if ($displayIncludesTax) {
            return [
                'tax_excl' => $this->policy->roundPriceAmount($taxCalculator->removeTaxes($amount)),
                'tax_incl' => $this->policy->roundPriceAmount($amount),
            ];
        }

        return [
            'tax_excl' => $this->policy->roundPriceAmount($amount),
            'tax_incl' => $this->policy->roundPriceAmount($taxCalculator->addTaxes($amount)),
        ];
    }

    protected function resolveCartRuleAdjustment(
        Order $order,
        $providedAmount,
        float $productTotalTaxExcl,
        float $productTotalTaxIncl,
        bool $displayIncludesTax
    ): array {
        $displayBase = $displayIncludesTax ? $productTotalTaxIncl : $productTotalTaxExcl;
        $displayAmount = $this->isProvidedAmount($providedAmount)
            ? $this->policy->roundPriceAmount(Tools::parseNumber((string)$providedAmount))
            : $this->policy->roundPriceAmount($displayBase * $this->getOrderPercentCartRuleRate($order) / 100);

        return $this->splitDisplayAdjustment(
            $displayAmount,
            $productTotalTaxExcl,
            $productTotalTaxIncl,
            $displayIncludesTax
        );
    }

    protected function resolveFeeAdjustment(
        $reasonEntityType,
        string $refundMethod,
        $providedAmount,
        float $baseTaxExcl,
        float $baseTaxIncl,
        bool $displayIncludesTax,
        ?string $action = null
    ): array {
        if ($refundMethod === RefundPolicy::REFUND_METHOD_NONE) {
            return [
                'tax_excl' => 0.0,
                'tax_incl' => 0.0,
            ];
        }

        $displayBase = $displayIncludesTax ? $baseTaxIncl : $baseTaxExcl;
        $displayAmount = $this->isProvidedAmount($providedAmount)
            ? $this->policy->roundPriceAmount(Tools::parseNumber((string)$providedAmount))
            : $this->policy->roundPriceAmount($displayBase * $this->policy->getSuggestedFeeRate($reasonEntityType, $refundMethod, $action) / 100);

        return $this->splitDisplayAdjustment($displayAmount, $baseTaxExcl, $baseTaxIncl, $displayIncludesTax);
    }

    protected function resolveDisplayAdjustment(
        $providedAmount,
        float $baseTaxExcl,
        float $baseTaxIncl,
        bool $displayIncludesTax
    ): array {
        if (!$this->isProvidedAmount($providedAmount)) {
            return ['tax_excl' => 0.0, 'tax_incl' => 0.0];
        }

        return $this->splitDisplayAdjustment(
            $this->policy->roundPriceAmount(Tools::parseNumber((string)$providedAmount)),
            $baseTaxExcl,
            $baseTaxIncl,
            $displayIncludesTax
        );
    }

    protected function splitDisplayAdjustment(
        float $displayAmount,
        float $baseTaxExcl,
        float $baseTaxIncl,
        bool $displayIncludesTax
    ): array {
        if ($displayIncludesTax) {
            $taxIncl = min($this->policy->roundPriceAmount($displayAmount), $this->policy->roundPriceAmount($baseTaxIncl));
            $taxExcl = $baseTaxIncl > 0.0
                ? $this->policy->roundPriceAmount($baseTaxExcl * $taxIncl / $baseTaxIncl)
                : 0.0;
        } else {
            $taxExcl = min($this->policy->roundPriceAmount($displayAmount), $this->policy->roundPriceAmount($baseTaxExcl));
            $taxIncl = $baseTaxExcl > 0.0
                ? $this->policy->roundPriceAmount($baseTaxIncl * $taxExcl / $baseTaxExcl)
                : 0.0;
        }

        return [
            'tax_excl' => $taxExcl,
            'tax_incl' => $taxIncl,
        ];
    }

    protected function isProvidedAmount($value): bool
    {
        return $value !== null && $value !== '';
    }
}
