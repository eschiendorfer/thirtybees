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
            return 0;
        }

        $this->triggerOrderSlipHook($order, $request['order_detail_list'], $request['full_quantity_list']);

        return $this->getLatestOrderSlipId((int)$order->id, (int)$order->id_customer);
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

        $idEmployee = (int)$this->context->employee->id;

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
            (string)$idStoreCreditTransaction
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
