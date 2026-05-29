<?php

class RefundPolicyCore
{
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_RETURN = 'return';
    public const ACTION_SERVICE = 'service';

    public const REASON_MANUAL = 'manual';
    public const REASON_ORDER_RETURN = 'order_return';
    public const REASON_CANCELLATION = 'cancellation';
    public const REASON_SERVICE_CASE = 'service_case';

    public const REFUND_METHOD_NONE = 'none';
    public const REFUND_METHOD_STORE_CREDIT = 'store_credit';
    public const REFUND_METHOD_ORIGINAL_PAYMENT = 'original_payment';

    public const ROUNDING_UNIT = 0.05;
    public const ORIGINAL_PAYMENT_CANCELLATION_FEE_RATE = 5.0;

    public function getValidActions(): array
    {
        return [
            static::ACTION_CANCEL,
            static::ACTION_RETURN,
            static::ACTION_SERVICE,
        ];
    }

    public function getValidReasonEntityTypes(): array
    {
        return [
            static::REASON_MANUAL,
            static::REASON_ORDER_RETURN,
            static::REASON_CANCELLATION,
            static::REASON_SERVICE_CASE,
        ];
    }

    public function getValidRefundMethods(): array
    {
        return [
            static::REFUND_METHOD_NONE,
            static::REFUND_METHOD_STORE_CREDIT,
            static::REFUND_METHOD_ORIGINAL_PAYMENT,
        ];
    }

    public function isOriginalPaymentRefundAvailable(?Order $order = null): bool
    {
        return $order instanceof Order
            && strtolower(trim((string)$order->module)) === 'payrexx';
    }

    public function getOpenOrderReturnStates(): array
    {
        return [
            OrderReturn::STATE_WAITING_FOR_CONFIRMATION,
            OrderReturn::STATE_WAITING_FOR_PACKAGE,
            OrderReturn::STATE_PACKAGE_RECEIVED,
        ];
    }

    public function getSuggestedFeeRate(string $reasonEntityType, string $refundMethod, ?string $action = null): float
    {
        if (
            $action === static::ACTION_CANCEL
            && $refundMethod === static::REFUND_METHOD_ORIGINAL_PAYMENT
        ) {
            return static::ORIGINAL_PAYMENT_CANCELLATION_FEE_RATE;
        }

        return 0.0;
    }

    public function roundAmount(float $amount): float
    {
        return Tools::roundPrice(round(max(0.0, $amount) / static::ROUNDING_UNIT) * static::ROUNDING_UNIT);
    }

    public function roundPriceAmount(float $amount): float
    {
        return Tools::roundPrice(max(0.0, $amount));
    }

    public function normalizePercent(float $rate): float
    {
        return Tools::roundPrice(min(100.0, max(0.0, $rate)));
    }
}
