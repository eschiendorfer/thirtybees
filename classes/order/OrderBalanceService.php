<?php

/** Financial balance; a credit slip reduces the claim without inventing a payment. */
class OrderBalanceServiceCore
{
    public function calculate(Order $order): array
    {
        $invoiceTotal = Db::getInstance()->getValue('SELECT SUM(total_paid_tax_incl) FROM `'._DB_PREFIX_.'order_invoice` WHERE id_order = '.(int)$order->id);
        $total = $invoiceTotal === null || $invoiceTotal === false ? (float)$order->total_paid_tax_incl : (float)$invoiceTotal;
        $credits = 0.0;
        foreach ($order->getOrderSlipsCollection() as $slip) {
            $credits += (float)$slip->getRefundTotalTaxIncl();
        }
        $paid = $refunded = $reserved = 0.0;
        foreach ($order->getOrderPaymentCollection() as $payment) {
            $amount = (float)$payment->amount;
            if ((int)$payment->id_currency !== (int)$order->id_currency) {
                if ((float)$payment->conversion_rate <= 0 || (float)$order->conversion_rate <= 0) {
                    throw new PrestaShopException('Der historische Wechselkurs fehlt.');
                }
                $amount = $amount / (float)$payment->conversion_rate * (float)$order->conversion_rate;
            }
            if ($amount > 0 && $payment->status === OrderPayment::STATUS_DONE) {
                $paid += $amount;
            } elseif ($amount < 0 && $payment->status === OrderPayment::STATUS_DONE) {
                $refunded -= $amount;
            } elseif ($amount < 0 && in_array($payment->status, [OrderPayment::STATUS_PENDING, OrderPayment::STATUS_MANUAL], true)) {
                $reserved -= $amount;
            }
        }
        $claim = Tools::roundPrice(max(0.0, $total - $credits));
        $balance = Tools::roundPrice($claim - $paid + $refunded);
        return [
            'invoice_total' => Tools::roundPrice($total),
            'credits' => Tools::roundPrice($credits),
            'claim' => $claim,
            'paid' => Tools::roundPrice($paid),
            'refunded' => Tools::roundPrice($refunded),
            'reserved' => Tools::roundPrice($reserved),
            'outstanding' => max(0.0, $balance),
            'refundable' => Tools::roundPrice(max(0.0, -$balance - $reserved)),
        ];
    }
}
