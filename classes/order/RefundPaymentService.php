<?php

/** One durable payment attempt per credit slip, shared by all BO refund actions. */
class RefundPaymentServiceCore
{
    public function pay(Order $order, int $idSlip, string $method, float $amount): OrderPayment
    {
        $db = Db::getInstance();
        $lock = 'refund_payment_'.(int)$order->id;
        if (!(int)$db->getValue("SELECT GET_LOCK('".pSQL($lock)."', 0)")) {
            throw new PrestaShopException('Eine Rückzahlung wird gerade bearbeitet. Bitte neu laden.');
        }
        try {
            $slip = new OrderSlip($idSlip);
            if (!Validate::isLoadedObject($order) || !Validate::isLoadedObject($slip)
                || (int)$slip->id_order !== (int)$order->id || !is_finite($amount) || $amount <= 0
                || $amount > $slip->getRefundTotalTaxIncl() + 0.000001
                || !in_array($method, [RefundPolicy::REFUND_METHOD_STORE_CREDIT, RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT], true)) {
                throw new PrestaShopException('Die Rückzahlung ist ungültig.');
            }
            if ($method === RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT
                && !(new RefundPolicy())->isOriginalPaymentRefundAvailable($order)) {
                throw new PrestaShopException('Die ursprüngliche Zahlung kann nicht automatisch erstattet werden.');
            }
            if ($db->getValue('SELECT id_order_payment FROM `'._DB_PREFIX_.'order_payment` WHERE id_order_slip = '.$idSlip.' AND amount < 0')) {
                throw new PrestaShopException('Für diese Gutschrift besteht bereits eine Rückzahlung oder ein ungeklärter Versuch. Bitte die vorhandene Zahlung prüfen.');
            }
            if (StoreCreditTransaction::getRefundCreditTransactionIdForOrderSlip($idSlip) > 0) {
                throw new PrestaShopException('Für diese Gutschrift besteht bereits eine Guthabenbuchung. Bitte zuerst die Zahlungszuordnung prüfen.');
            }
            $balance = (new OrderBalanceService())->calculate(new Order((int)$order->id));
            // An employee may deliberately leave a receivable, but may never refund money twice.
            $available = max(0.0, $balance['paid'] - $balance['refunded'] - $balance['reserved']);
            if ($amount > $available + 0.000001) {
                throw new PrestaShopException('Der verfügbare Zahlungsbetrag hat sich geändert. Die Gutschrift bleibt erhalten; bitte neu laden.');
            }
            // Commit the intent before the provider call. A timeout or failed result stays reserved.
            // The temporary module avoids the provider mistaking its own reservation for a completed refund.
            if (!OrderPayment::addRefundForOrderSlip($order, $idSlip, $amount,
                $method === RefundPolicy::REFUND_METHOD_STORE_CREDIT ? 'Store Credit' : $order->payment,
                'refund_pending', OrderPayment::STATUS_PENDING)) {
                throw new PrestaShopException('Die Rückzahlung konnte nicht vorbereitet werden.');
            }
            $idPayment = (int)$db->getValue('SELECT id_order_payment FROM `'._DB_PREFIX_.'order_payment` WHERE id_order_slip = '.$idSlip
                ." AND payment_module = 'refund_pending' AND amount < 0 ORDER BY id_order_payment DESC");
            $payment = new OrderPayment($idPayment);
            if (!Validate::isLoadedObject($payment)) {
                throw new PrestaShopException('Die vorbereitete Rückzahlung konnte nicht geladen werden. Bitte prüfen.');
            }
            if ($method === RefundPolicy::REFUND_METHOD_STORE_CREDIT) {
                $idTransaction = (new RefundCreator(Context::getContext()))->createRefundStoreCredit($order, $idSlip, $amount);
                if (!$idTransaction) {
                    throw new PrestaShopException('Die Guthabenbuchung muss geprüft werden. Keine zweite Auszahlung auslösen.');
                }
                $payment->id_store_credit_transaction = $idTransaction;
                $payment->payment_module = 'store_credit';
                $payment->status = OrderPayment::STATUS_DONE;
            } else {
                $result = $this->refundOriginalPayment($order, $idSlip, $amount);
                if (empty($result['success'])) {
                    throw new PrestaShopException('Die Rückzahlung muss in Payrexx geprüft werden. Keine zweite Auszahlung auslösen. '.($result['message'] ?? ''));
                }
                $payment->payment_module = $result['payment_module'] ?? $order->module;
                $payment->payment_method = $result['payment_method'] ?? $order->payment;
                $payment->transaction_id = $result['transaction_id'] ?? null;
                $payment->status = in_array($result['status'] ?? '', [OrderPayment::STATUS_DONE, OrderPayment::STATUS_PENDING, OrderPayment::STATUS_MANUAL], true)
                    ? $result['status'] : OrderPayment::STATUS_PENDING;
            }
            if (!$payment->update()) {
                throw new PrestaShopException('Die Rückzahlung wurde ausgelöst, ihr Ergebnis konnte aber nicht gespeichert werden. Nicht erneut auszahlen.');
            }
            return $payment;
        } finally {
            $db->getValue("SELECT RELEASE_LOCK('".pSQL($lock)."')");
        }
    }

    protected function refundOriginalPayment(Order $order, int $idSlip, float $amount): array
    {
        $module = Module::getInstanceByName($order->module);
        if (!$module || !method_exists($module, 'refundOrderSlipPayment')) {
            throw new PrestaShopException('Der Zahlungsanbieter unterstützt keine automatische Rückzahlung.');
        }
        return $module->refundOrderSlipPayment($order, $idSlip, $amount);
    }
}
