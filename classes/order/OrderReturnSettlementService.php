<?php

/** Coordinates the existing credit and payment services for a received return. */
class OrderReturnSettlementServiceCore
{
    public function preview(OrderReturn $return): array
    {
        $order = new Order((int)$return->id_order);
        if (!Validate::isLoadedObject($return) || !Validate::isLoadedObject($order)) {
            throw new PrestaShopException('Die Rücksendung wurde nicht gefunden.');
        }
        $eligibility = new RefundEligibilityService();
        $products = $order->getProducts();
        foreach ($products as &$product) {
            $remaining = $eligibility->getOrderDetailRemainingCreditAmounts(new OrderDetail((int)$product['id_order_detail']));
            $product['amount_refundable'] = $remaining['tax_excl'];
            $product['amount_refundable_tax_incl'] = $remaining['tax_incl'];
        }
        unset($product);
        $calculator = new RefundCalculator();
        $suggestion = $calculator->buildOrderReturnCreditSuggestion($order, $products, (int)$return->id, true);
        $slips = Db::getInstance()->getArray('SELECT id_order_slip FROM `'._DB_PREFIX_.'order_slip` WHERE id_order = '.(int)$order->id
            .' AND reason_entity_type = '.RefundPolicy::REASON_ORDER_RETURN.' AND reason_id_entity = '.(int)$return->id.' ORDER BY id_order_slip DESC');
        $request = null;
        $errors = [];
        if ($suggestion['products']) {
            $amounts = $quantities = [];
            foreach ($suggestion['products'] as $id => $line) {
                $amounts[$id] = $line['amount'];
                $quantities[$id] = $line['quantity'];
            }
            $result = $calculator->buildCreditSlipRequest($order, [
                'display_includes_tax' => true,
                'initial_return_credit' => true,
                'refund_method' => RefundPolicy::REFUND_METHOD_NONE,
                'reason_entity_type' => RefundPolicy::REASON_KEY_ORDER_RETURN,
                'reason_id_entity' => (int)$return->id,
                'product_amounts' => $amounts,
                'product_quantities' => $quantities,
                'shipping_adjustment' => $suggestion['shipping']['amount'],
                'cart_rule_adjustment' => $suggestion['cart_rule_adjustment']['amount'],
                'fee_adjustment' => $suggestion['fee_adjustment']['amount'],
            ]);
            $request = $result['request'];
            $errors = $result['errors'];
        }
        $balance = (new OrderBalanceService())->calculate($order);
        $credit = $request ? (float)$request['effective_tax_incl'] : 0.0;
        $latestSlip = $slips ? new OrderSlip((int)$slips[0]['id_order_slip']) : null;
        $settlement = $this->getAmounts($order, (int)$return->id, $credit);
        $credited = $settlement['credited'];
        $payout = $settlement['payout'];
        $payments = $settlement['payments'];
        $hasUnresolvedPayment = $settlement['unresolved'];
        if ($payout > $settlement['available_slips'] + $credit + 0.000001) {
            $errors[] = 'Eine bereits teilweise ausgezahlte Gutschrift benötigt eine weitere Rückzahlung. Bitte die Zahlungen in der Bestellung prüfen.';
        }
        $outstandingQuantity = false;
        foreach ($return->getDetailRows() as $row) {
            $outstandingQuantity = $outstandingQuantity || (int)$row['product_quantity'] > (int)$row['received_quantity'];
        }
        if (count($order->getInvoicesCollection()) > 1) {
            $errors[] = 'Diese Bestellung hat mehrere Rechnungen. Bitte die Zuordnung in der Bestellung prüfen.';
        }
        $policy = new RefundPolicy();
        $paymentLabel = trim((string)$order->payment);
        $methods = [RefundPolicy::REFUND_METHOD_NONE => 'Keine Auszahlung – nur Gutschrift'];
        if ($payout > 0) {
            if ($policy->isOriginalPaymentRefundAvailable($order)) {
                $methods[RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT] = 'Ursprüngliche Zahlung'.($paymentLabel !== '' ? ' ('.$paymentLabel.')' : '');
            }
            $methods[RefundPolicy::REFUND_METHOD_STORE_CREDIT] = 'Kundenguthaben';
        }
        $default = $payout > 0 && isset($methods[RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT])
            ? RefundPolicy::REFUND_METHOD_ORIGINAL_PAYMENT : RefundPolicy::REFUND_METHOD_NONE;
        $data = [
            'request' => $request, 'errors' => $errors, 'credit' => $credit, 'credited' => $credited, 'payout' => $payout,
            'balance' => $balance, 'remaining' => max(0.0, Tools::roundPrice($balance['outstanding'] - $credit)),
            'slips' => $slips, 'methods' => $methods, 'default_method' => $default,
            'original_payment_label' => $paymentLabel,
            'waiting_package' => $outstandingQuantity, 'payment_unresolved' => $hasUnresolvedPayment,
            'can_settle' => !(bool)$return->migrated && !$errors && !$hasUnresolvedPayment && ($request || $latestSlip)
                && !in_array((int)$return->state, [OrderReturn::STATE_RETURN_COMPLETED, OrderReturn::STATE_RETURN_DENIED, OrderReturn::STATE_RETURN_EXPIRED], true),
        ];
        $data['fingerprint'] = hash('sha256', json_encode([$request, $balance, $slips, $payments, $return->getDetailRows()]));
        return $data;
    }

    /** Display the actual request, or the persisted credits after completion. */
    public function getCreditBreakdown(array $view): array
    {
        $result = ['products' => [], 'previous_products' => [], 'articles' => 0.0, 'shipping' => 0.0,
            'discount' => 0.0, 'deductions' => 0.0, 'rounding' => 0.0,
            'total' => $view['request'] ? $view['credit'] : $view['credited'], 'is_new' => (bool)$view['request']];
        foreach ($view['slips'] as $row) {
            $slip = new OrderSlip((int)$row['id_order_slip']);
            foreach (OrderSlip::getOrdersSlipDetail((int)$slip->id) as $line) {
                $id = (int)$line['id_order_detail'];
                $result['previous_products'][$id] = ($result['previous_products'][$id] ?? 0.0) + (float)$line['amount_tax_incl'];
            }
            if (!$view['request']) {
                $result['articles'] += (float)$slip->total_products_tax_incl;
                $result['shipping'] += (float)$slip->total_shipping_tax_incl;
                $result['discount'] += (float)$slip->adjustment_cart_rule_tax_incl;
                $result['deductions'] += (float)$slip->adjustment_fee_tax_incl + (float)$slip->adjustment_shipping_charge_tax_incl;
            }
        }
        if ($view['request']) {
            $request = $view['request'];
            foreach ($request['order_detail_list'] as $line) {
                $result['products'][(int)$line['id_order_detail']] = (float)$line['amount'];
            }
            $result['articles'] = array_sum($result['products']);
            $result['shipping'] = (float)$request['shipping_cost_amount'];
            $result['discount'] = (float)$request['metadata']['adjustment_cart_rule_tax_incl'];
            $result['deductions'] = (float)$request['metadata']['adjustment_fee_tax_incl'] + (float)$request['metadata']['adjustment_shipping_charge_tax_incl'];
        } else {
            $result['products'] = $result['previous_products'];
        }
        $result['rounding'] = Tools::roundPrice($result['total'] - $result['articles'] - $result['shipping'] + $result['discount'] + $result['deductions']);
        return $result;
    }

    public function settle(OrderReturn $return, string $method, string $fingerprint): void
    {
        $db = Db::getInstance();
        $lock = 'return_settlement_'.(int)$return->id_order;
        if (!(int)$db->getValue("SELECT GET_LOCK('".pSQL($lock)."', 0)")) {
            throw new PrestaShopException('Diese Bestellung wird gerade bearbeitet. Bitte neu laden.');
        }
        try {
            $view = $this->preview($return);
            if (!$view['can_settle'] || !hash_equals($view['fingerprint'], $fingerprint) || !isset($view['methods'][$method])) {
                throw new PrestaShopException('Der Stand hat sich geändert oder die Abwicklung ist gesperrt. Bitte neu laden und prüfen.');
            }
            $order = new Order((int)$return->id_order);
            $creator = new RefundCreator(Context::getContext());
            $idSlip = $view['request'] ? $creator->createCreditSlipFromRequest($order, $view['request']) : (int)$view['slips'][0]['id_order_slip'];
            if ($idSlip <= 0) {
                throw new PrestaShopException('Die Gutschrift konnte nicht erstellt werden.');
            }
            if ($method !== RefundPolicy::REFUND_METHOD_NONE && $view['payout'] > 0) {
                $this->payReturn($order, (int)$return->id, $method, (float)$view['payout']);
            }
            $this->updateStatus($return);
        } finally {
            $db->getValue("SELECT RELEASE_LOCK('".pSQL($lock)."')");
        }
    }

    /** Include every credit and payment belonging to this case, including unresolved attempts. */
    public function getAmounts(Order $order, int $idReturn, float $newCredit = 0.0): array
    {
        $slips = Db::getInstance()->getArray('SELECT id_order_slip FROM `'._DB_PREFIX_.'order_slip` WHERE id_order = '.(int)$order->id
            .' AND reason_entity_type = '.RefundPolicy::REASON_ORDER_RETURN.' AND reason_id_entity = '.$idReturn.' ORDER BY id_order_slip');
        $credited = $paid = $reserved = $available = 0.0;
        $payments = $payable = [];
        $unresolved = false;
        foreach ($slips as $row) {
            $id = (int)$row['id_order_slip'];
            $amount = (float)(new OrderSlip($id))->getRefundTotalTaxIncl();
            $credited += $amount;
            $rows = Db::getInstance()->getArray('SELECT id_order_payment, id_order_slip, status, amount, id_currency, conversion_rate FROM `'._DB_PREFIX_.'order_payment` WHERE id_order_slip = '.$id.' AND amount < 0');
            foreach ($rows as $payment) {
                $payments[] = $payment;
                $paymentAmount = abs((float)$payment['amount']);
                if ((int)$payment['id_currency'] !== (int)$order->id_currency) {
                    if ((float)$payment['conversion_rate'] <= 0 || (float)$order->conversion_rate <= 0) {
                        throw new PrestaShopException('Der historische Wechselkurs fehlt.');
                    }
                    $paymentAmount = $paymentAmount / (float)$payment['conversion_rate'] * (float)$order->conversion_rate;
                }
                if ($payment['status'] === OrderPayment::STATUS_DONE) {
                    $paid += $paymentAmount;
                } else {
                    $reserved += $paymentAmount;
                    $unresolved = true;
                }
            }
            if (!$rows) {
                $payable[$id] = $amount;
                $available += $amount;
            }
        }
        $balance = (new OrderBalanceService())->calculate($order);
        $payout = Tools::roundPrice(max(0.0, min($credited + $newCredit - $paid - $reserved,
            $balance['paid'] - $balance['refunded'] - $balance['reserved'] - $balance['claim'] + $newCredit)));
        return ['credited' => $credited, 'payout' => $payout, 'payments' => $payments,
            'unresolved' => $unresolved, 'available_slips' => $available, 'payable' => $payable];
    }

    public function payReturn(Order $order, int $idReturn, string $method, float $amount): void
    {
        $state = $this->getAmounts($order, $idReturn);
        if ($state['unresolved'] || $amount > $state['available_slips'] + 0.000001) {
            throw new PrestaShopException('Die vorhandenen Rückzahlungen müssen zuerst geprüft werden. Keine weitere Auszahlung ausgelöst.');
        }
        foreach ($state['payable'] as $idSlip => $credit) {
            $part = min($amount, $credit);
            if ($part <= 0) {
                continue;
            }
            $payment = (new RefundPaymentService())->pay($order, $idSlip, $method, $part);
            $amount = Tools::roundPrice($amount - $part);
            if ($payment->status !== OrderPayment::STATUS_DONE) {
                break;
            }
        }
    }

    public function updateStatus(OrderReturn $return): void
    {
        $view = $this->preview($return);
        $complete = !$view['waiting_package'] && !$view['request'] && !$view['errors'] && !$view['payment_unresolved']
            && $view['slips'] && $view['payout'] <= 0;
        if (!CustomerServiceStatus::save($return, (string)($complete ? OrderReturn::STATE_RETURN_COMPLETED : OrderReturn::STATE_INQUIRY))) {
            throw new PrestaShopException('Die Gutschrift ist vorhanden, aber der Fallstatus konnte nicht gespeichert werden.');
        }
    }
}
