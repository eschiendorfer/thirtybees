<?php

class RefundCreatorCore
{
    /** @var Context */
    protected $context;

    public function __construct(?Context $context = null)
    {
        $this->context = $context ?: Context::getContext();
    }

    public function createCreditSlipFromRequest(Order $order, array $request): int
    {
        $db = Db::getInstance();
        $db->execute('START TRANSACTION');
        try {
            $db->getArray('SELECT id_order FROM `'._DB_PREFIX_.'orders` WHERE id_order = '.(int)$order->id.' FOR UPDATE');
            $freshOrder = new Order((int)$order->id);
            $metadata = $request['metadata'];
            $excludeCancellation = (int)$metadata['reason_entity_type'] === RefundPolicy::REASON_CANCELLATION
                ? (int)$metadata['reason_id_entity'] : 0;
            $eligibility = new RefundEligibilityService();
            if ((float)$request['effective_tax_incl'] > $eligibility->getRemainingOrderCreditAmount($freshOrder, $excludeCancellation) + 0.000001) {
                throw new PrestaShopException(Tools::displayError('The remaining refundable amount changed. Reload the order and review the credit.'));
            }
            foreach ($request['order_detail_list'] as $product) {
                $detail = new OrderDetail((int)$product['id_order_detail']);
                $remaining = $eligibility->getOrderDetailRemainingCreditAmounts($detail);
                $resume = OrderSlip::getProductSlipResume((int)$detail->id);
                if ((int)$detail->id_order !== (int)$order->id
                    || (float)$product['amount'] > $remaining[$request['add_tax'] ? 'tax_excl' : 'tax_incl'] + 0.000001
                    || (int)$product['quantity'] + (int)$resume['product_quantity'] > (int)$detail->product_quantity) {
                    throw new PrestaShopException(Tools::displayError('The remaining refundable product amount or quantity changed. Reload the order.'));
                }
            }
            if (!OrderSlip::create(
                $order,
                $request['order_detail_list'],
                $request['shipping_cost_amount'],
                0,
                false,
                (bool)$request['add_tax'],
                [],
                $request['metadata']
            )) {
                throw new PrestaShopException(Tools::displayError('The credit slip could not be created.'));
            }

            $idOrderSlip = $this->getLatestOrderSlipId((int)$order->id, (int)$order->id_customer);
            $slip = new OrderSlip($idOrderSlip);
            if (!$idOrderSlip || abs($slip->getRefundTotalTaxIncl() - (float)$request['effective_tax_incl']) > 0.000001) {
                throw new PrestaShopException(Tools::displayError('The credit slip differs from the confirmed amount. No credit was created.'));
            }
            $db->execute('COMMIT');
        } catch (Throwable $exception) {
            $db->execute('ROLLBACK');
            throw $exception;
        }

        $this->triggerOrderSlipHook($order, $request['order_detail_list'], $request['full_quantity_list']);

        return $idOrderSlip;
    }

    public function getLatestOrderSlipId(int $idOrder, int $idCustomer): int
    {
        return (int)Db::readOnly()->getValue(
            (new DbQuery())
                ->select('`id_order_slip`')
                ->from('order_slip')
                ->where('`id_order` = '.(int)$idOrder)
                ->where('`id_customer` = '.(int)$idCustomer)
                ->orderBy('`id_order_slip` DESC')
        );
    }

    public function createRefundStoreCredit(Order $order, int $idOrderSlip, float $amountTaxIncl): int
    {
        if ($idOrderSlip <= 0 || $amountTaxIncl <= 0.0) {
            return 0;
        }

        $existingTransaction = StoreCreditTransaction::getRefundCreditTransactionIdForOrderSlip($idOrderSlip);
        if ($existingTransaction > 0) {
            return $existingTransaction;
        }

        $idEmployee = 0;
        if (isset($this->context->employee) && Validate::isLoadedObject($this->context->employee)) {
            $idEmployee = (int)$this->context->employee->id;
        }

        if (!StoreCredit::addRefundCreditForOrderSlip(
            (int)$order->id_shop,
            (int)$order->id_customer,
            (int)$order->id,
            (int)$idOrderSlip,
            (float)$amountTaxIncl,
            $idEmployee
        )) {
            return 0;
        }

        return StoreCreditTransaction::getRefundCreditTransactionIdForOrderSlip((int)$idOrderSlip);
    }

    public function addStoreCreditRefundPayment(Order $order, int $idOrderSlip, float $amountTaxIncl, int $idStoreCreditTransaction): bool
    {
        return OrderPayment::addRefundForOrderSlip(
            $order,
            $idOrderSlip,
            $amountTaxIncl,
            'Store Credit',
            'store_credit',
            OrderPayment::STATUS_DONE,
            null,
            0,
            $idStoreCreditTransaction
        );
    }

    protected function triggerOrderSlipHook(Order $order, array $productList, array $qtyList): void
    {
        $params = [
            'order' => $order,
            'productList' => $productList,
            'qtyList' => $qtyList,
        ];

        if (method_exists('Hook', 'triggerEvent')) {
            Hook::triggerEvent('actionOrderSlipAdd', $params, $order->id_shop);
        } else {
            Hook::exec('actionOrderSlipAdd', $params, null, false, true, false, $order->id_shop);
        }
    }
}
