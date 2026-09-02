<?php

class CancellationQuoteServiceCore
{
    public const FEE_POLICY_STANDARD = 'standard_5_percent';
    public const FEE_POLICY_LATE_KNOWN = 'late_known_date';
    public const FEE_POLICY_LATE_UNKNOWN = 'late_unknown_date';
    public const FEE_POLICY_STORE_CREDIT = 'store_credit';
    public const FEE_POLICY_UNPAID = 'unpaid';

    /** @var RefundPolicy */
    private $policy;
    /** @var CancelEligibilityService */
    private $cancelEligibility;
    /** @var OrderAdjustmentService */
    private $adjustmentService;

    public function __construct()
    {
        $this->policy = new RefundPolicy();
        $this->cancelEligibility = new CancelEligibilityService();
        $this->adjustmentService = new OrderAdjustmentService();
    }

    public function getAvailableRefundMethods(Order $order): array
    {
        if (!$order->hasBeenPaid()) {
            return [];
        }

        $methods = [RefundPolicy::REFUND_METHOD_STORE_CREDIT];
        if ($this->policy->isOriginalPaymentRefundAvailable($order)) {
            $methods[] = RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT;
        }

        return $methods;
    }

    /**
     * Builds the authoritative values shown to the customer. Product discounts
     * are allocated proportionally, then the cancellation fee is calculated
     * per order line and only then summed.
     *
     * @return array{errors: array, quote: ?array}
     */
    public function buildQuote(Order $order, array $quantitiesByOrderDetail, ?string $refundMethod): array
    {
        return $this->buildQuoteInternal($order, $quantitiesByOrderDetail, $refundMethod, false, null);
    }

    public function buildAppliedCancellationQuote(OrderCancellation $cancellation, ?string $refundMethod): array
    {
        $order = new Order((int)$cancellation->id_order);
        if (!Validate::isLoadedObject($order)) {
            return ['errors' => ['The order could not be loaded.'], 'quote' => null];
        }

        return $this->buildQuoteInternal(
            $order,
            OrderCancellationDetail::getQuantitiesForCancellation((int)$cancellation->id),
            $refundMethod,
            true,
            (string)$cancellation->date_add
        );
    }

    private function buildQuoteInternal(
        Order $order,
        array $quantitiesByOrderDetail,
        ?string $refundMethod,
        bool $selectedAlreadyCancelled,
        ?string $calculationDate
    ): array
    {
        $errors = [];
        $isPaid = $order->hasBeenPaid();
        $availableMethods = $this->getAvailableRefundMethods($order);
        if ($isPaid && !in_array((string)$refundMethod, $availableMethods, true)) {
            $errors[] = 'The selected refund method is not available for this order.';
        }
        if (!$isPaid) {
            $refundMethod = null;
        }

        $products = [];
        foreach ($order->getProducts() as $product) {
            $products[(int)$product['id_order_detail']] = $product;
        }

        $adjustmentResult = $this->adjustmentService->calculate($order, $quantitiesByOrderDetail, $selectedAlreadyCancelled);
        if ($adjustmentResult['errors'] || !$adjustmentResult['adjustment']) {
            return ['errors' => $adjustmentResult['errors'], 'quote' => null];
        }
        $adjustment = $adjustmentResult['adjustment'];

        $details = [];
        $productTotal = 0.0;
        $feeTotal = 0.0;

        foreach ($products as $idOrderDetail => $product) {
            $orderDetail = new OrderDetail($idOrderDetail);
            $quantity = max(0, (int)($quantitiesByOrderDetail[$idOrderDetail] ?? 0));
            $cancelable = $selectedAlreadyCancelled
                ? (int)$quantity
                : (Validate::isLoadedObject($orderDetail)
                ? $this->cancelEligibility->getOrderDetailCancelableQuantity(
                    $order,
                    $orderDetail
                )
                : 0);
            if ($quantity > $cancelable) {
                $errors[] = 'A selected cancellation quantity is no longer available.';
                continue;
            }
            if ($quantity <= 0) {
                continue;
            }

            $productAmount = (float)($adjustment['lines'][$idOrderDetail]['product_amount_tax_incl'] ?? 0.0);
            $feePolicyData = $this->getFeePolicyData($order, $idOrderDetail, $refundMethod, $isPaid, $calculationDate);
            $feePolicy = $feePolicyData['policy'];
            $fee = $feePolicy === self::FEE_POLICY_STANDARD
                ? $this->policy->roundAmount($productAmount * RefundPolicy::ORIGINAL_PAYMENT_CANCELLATION_FEE_RATE / 100.0)
                : 0.0;

            $details[$idOrderDetail] = [
                'product_amount_tax_incl' => $productAmount,
                'fee_tax_incl' => $fee,
                'fee_policy' => $feePolicy,
                'fee_expected_date' => $feePolicyData['expected_date'],
                'fee_free_from_date' => $feePolicyData['fee_free_from_date'],
            ];
            $productTotal += $productAmount;
            $feeTotal += $fee;
        }

        if (!$details) {
            $errors[] = 'Please select at least one product.';
        }
        if ($errors) {
            return ['errors' => array_values(array_unique($errors)), 'quote' => null];
        }

        // Positive means previously paid shipping is returned. Negative means
        // the remaining order newly requires shipping and therefore reduces
        // the credit. The customer is never charged an additional amount.
        $shipping = (float)$adjustment['shipping_adjustment_tax_incl'];
        $refundTotal = $this->policy->roundAmount(max(0.0, $productTotal + $shipping - $feeTotal));

        return [
            'errors' => [],
            'quote' => [
                'refund_method' => $refundMethod,
                'product_total_tax_incl' => $this->policy->roundPriceAmount($productTotal),
                'shipping_tax_incl' => $shipping,
                'shipping_before_tax_incl' => (float)$adjustment['shipping_before_tax_incl'],
                'shipping_after_tax_incl' => (float)$adjustment['shipping_after_tax_incl'],
                'remaining_total_tax_incl' => (float)$adjustment['remaining']['total_tax_incl'],
                'is_full_cancellation' => empty($adjustment['remaining']['has_products']),
                'fee_tax_incl' => $this->policy->roundAmount($feeTotal),
                'refund_total_tax_incl' => $refundTotal,
                'requires_manual_review' => !empty($adjustment['requires_manual_review']),
                'negative_financial_effect' => !empty($adjustment['negative_financial_effect']),
                'details' => $details,
            ],
        ];
    }

    /**
     * Returns both the applied policy and the exact dates used by that policy,
     * so the customer explanation cannot diverge from the fee calculation.
     */
    private function getFeePolicyData(
        Order $order,
        int $idOrderDetail,
        ?string $refundMethod,
        bool $isPaid,
        ?string $calculationDate = null
    ): array
    {
        if (!$isPaid) {
            return [
                'policy' => self::FEE_POLICY_UNPAID,
                'expected_date' => null,
                'fee_free_from_date' => null,
            ];
        }
        if ($refundMethod !== RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT) {
            return [
                'policy' => self::FEE_POLICY_STORE_CREDIT,
                'expected_date' => null,
                'fee_free_from_date' => null,
            ];
        }

        $expectedDate = '';
        if (class_exists('\\CrmModule\\OrderDetailExtension')) {
            $extension = new \CrmModule\OrderDetailExtension($idOrderDetail);
            $expectedDate = trim((string)$extension->date_availability_expected);
        }
        $known = !in_array($expectedDate, ['', '0000-00-00', '0000-00-00 00:00:00'], true);
        $result = [
            'policy' => self::FEE_POLICY_STANDARD,
            'expected_date' => null,
            'fee_free_from_date' => null,
        ];
        try {
            $referenceDate = new DateTime($known ? $expectedDate : (string)$order->date_add);
            $deadline = clone $referenceDate;
            $deadline->add(new DateInterval($known ? 'P1M' : 'P6M'))->setTime(23, 59, 59);
            $feeFreeFrom = clone $deadline;
            $feeFreeFrom->modify('+1 second');
            $result['expected_date'] = $known ? $referenceDate->format('Y-m-d') : null;
            $result['fee_free_from_date'] = $feeFreeFrom->format('Y-m-d');
            $evaluatedAt = $calculationDate ? new DateTime($calculationDate) : new DateTime();
            if ($evaluatedAt > $deadline) {
                $result['policy'] = $known ? self::FEE_POLICY_LATE_KNOWN : self::FEE_POLICY_LATE_UNKNOWN;
            }
        } catch (Throwable $exception) {
            // An invalid historical date must not waive a fee automatically.
        }

        return $result;
    }
}
