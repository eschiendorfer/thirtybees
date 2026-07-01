<?php

class RefundPolicyCore
{
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_RETURN = 'return';
    public const ACTION_SERVICE = 'service';

    public const REASON_KEY_MANUAL = 'manual';
    public const REASON_KEY_ORDER_RETURN = 'order_return';
    public const REASON_KEY_CANCELLATION = 'cancellation';
    public const REASON_KEY_SERVICE_CASE = 'service_case';

    public const REASON_MANUAL = 0;
    public const REASON_ORDER_RETURN = 72;
    public const REASON_CANCELLATION = 71;
    public const REASON_SERVICE_CASE = 73;

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

    public function normalizeReasonEntityType($reasonEntityType): int
    {
        if (is_numeric($reasonEntityType)) {
            $reasonEntityType = (int)$reasonEntityType;
            return in_array($reasonEntityType, $this->getValidReasonEntityTypes(), true)
                ? $reasonEntityType
                : static::REASON_MANUAL;
        }

        switch (trim((string)$reasonEntityType)) {
            case static::REASON_KEY_ORDER_RETURN:
                return static::REASON_ORDER_RETURN;
            case static::REASON_KEY_CANCELLATION:
                return static::REASON_CANCELLATION;
            case static::REASON_KEY_SERVICE_CASE:
                return static::REASON_SERVICE_CASE;
            case static::REASON_KEY_MANUAL:
            default:
                return static::REASON_MANUAL;
        }
    }

    public function isValidReasonEntityTypeInput($reasonEntityType): bool
    {
        if (is_numeric($reasonEntityType)) {
            return in_array((int)$reasonEntityType, $this->getValidReasonEntityTypes(), true);
        }

        return in_array(trim((string)$reasonEntityType), [
            static::REASON_KEY_MANUAL,
            static::REASON_KEY_ORDER_RETURN,
            static::REASON_KEY_CANCELLATION,
            static::REASON_KEY_SERVICE_CASE,
        ], true);
    }

    public function getReasonKey(int $reasonEntityType): string
    {
        switch ($reasonEntityType) {
            case static::REASON_ORDER_RETURN:
                return static::REASON_KEY_ORDER_RETURN;
            case static::REASON_CANCELLATION:
                return static::REASON_KEY_CANCELLATION;
            case static::REASON_SERVICE_CASE:
                return static::REASON_KEY_SERVICE_CASE;
            case static::REASON_MANUAL:
            default:
                return static::REASON_KEY_MANUAL;
        }
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
            && strtolower(trim((string)$order->module)) === 'payrexx'
            && !$this->isStoreCreditUsedForOrder($order);
    }

    private function isStoreCreditUsedForOrder(?Order $order): bool
    {
        if (!$order instanceof Order || (int)$order->id <= 0) {
            return false;
        }

        if (class_exists('StoreCreditTransaction')) {
            try {
                if ((float)StoreCreditTransaction::getOrderConsumptionAmount((int)$order->id) > 0.0) {
                    return true;
                }
            } catch (Exception $exception) {
            }
        }

        try {
            $payments = $order->getOrderPaymentCollection();
        } catch (Exception $exception) {
            return false;
        }

        foreach ($payments as $payment) {
            if (
                $payment instanceof OrderPayment
                && (float)$payment->amount > 0.0
                && ((string)$payment->payment_module === 'store_credit' || (string)$payment->payment_method === 'Store Credit')
            ) {
                return true;
            }
        }

        return false;
    }

    public function getOpenOrderReturnStates(): array
    {
        return [
            OrderReturn::STATE_WAITING_FOR_CONFIRMATION,
            OrderReturn::STATE_WAITING_FOR_PACKAGE,
            OrderReturn::STATE_PACKAGE_RECEIVED,
        ];
    }

    public function getSuggestedFeeRate($reasonEntityType, string $refundMethod, ?string $action = null): float
    {
        $reasonEntityType = $this->normalizeReasonEntityType($reasonEntityType);
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
