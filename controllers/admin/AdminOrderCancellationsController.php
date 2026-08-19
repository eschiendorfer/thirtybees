<?php

/**
 * Class AdminOrderCancellationsControllerCore
 *
 * @property OrderCancellation|null $object
 */
class AdminOrderCancellationsControllerCore extends AdminController
{
    private const REASON_ENTITY_TYPE_ORDER_CANCELLATION = 71;

    /**
     * AdminOrderCancellationsControllerCore constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->table = 'order_cancellation';
        $this->className = 'OrderCancellation';
        $this->identifier = 'id_order_cancellation';

        $this->_select = 'o.`id_order`, o.`id_shop`, o.`id_currency`, o.`reference`, c.`id_customer`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer`, COALESCE(SUM(ocd.`product_quantity`), 0) AS `cancelled_quantity`';
        $this->_join = ' LEFT JOIN `'._DB_PREFIX_.'orders` o ON (o.`id_order` = a.`id_order`)';
        $this->_join .= ' LEFT JOIN `'._DB_PREFIX_.'customer` c ON (c.`id_customer` = o.`id_customer`)';
        $this->_join .= ' LEFT JOIN `'._DB_PREFIX_.'order_cancellation_detail` ocd ON (ocd.`id_order_cancellation` = a.`id_order_cancellation`)';
        $this->_group = ' GROUP BY a.`id_order_cancellation`';

        $this->fields_list = [
            'id_order_cancellation' => [
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
            ],
            'reference' => [
                'title' => $this->l('Order Reference'),
                'filter_key' => 'o!reference',
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
                'callback' => 'displayOrderDetailLink',
            ],
            'customer' => [
                'title' => $this->l('Customer'),
                'havingFilter' => true,
                'callback' => 'displayCustomerDetailLink',
            ],
            'status' => [
                'title' => $this->l('Status'),
                'type' => 'select',
                'list' => $this->getStatusList(),
                'filter_key' => 'a!status',
                'filter_type' => 'string',
                'callback' => 'displayStatusLabel',
            ],
            'requested_refund_method' => [
                'title' => $this->l('Requested refund method'),
                'type' => 'select',
                'list' => $this->getRefundMethodList(),
                'filter_key' => 'a!requested_refund_method',
                'filter_type' => 'string',
                'callback' => 'displayRefundMethodLabel',
            ],
            'cancelled_quantity' => [
                'title' => $this->l('Cancelled quantity'),
                'align' => 'text-center',
                'havingFilter' => true,
                'class' => 'fixed-width-sm',
            ],
            'date_add' => [
                'title' => $this->l('Date issued'),
                'type' => 'datetime',
                'align' => 'right',
                'filter_key' => 'a!date_add',
            ],
        ];

        $this->_orderBy = 'id_order_cancellation';
        $this->_orderWay = 'DESC';
        $this->_use_found_rows = false;

        parent::__construct();

        $this->_where = Shop::addSqlRestriction(false, 'o');
        $this->addRowAction('edit');
        $this->addRowAction('view');
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    public function initToolbar()
    {
        parent::initToolbar();

        unset($this->toolbar_btn['new']);
    }

    /**
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderForm()
    {
        if (!Validate::isLoadedObject($this->object)) {
            return '';
        }

        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Order cancellation'),
                'icon' => 'icon-ban',
            ],
            'input' => [
                [
                    'type' => 'hidden',
                    'name' => 'id_order_cancellation',
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Status'),
                    'name' => 'status',
                    'required' => true,
                    'options' => [
                        'query' => $this->getStatusOptions(),
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Requested refund method'),
                    'name' => 'requested_refund_method',
                    'required' => false,
                    'options' => [
                        'query' => $this->getRefundMethodOptions(),
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
            'buttons' => [
                'save-and-stay' => [
                    'title' => $this->l('Save and stay'),
                    'name' => 'submitAdd'.$this->table.'AndStay',
                    'type' => 'submit',
                    'class' => 'btn btn-default pull-right',
                    'icon' => 'process-icon-save',
                ],
            ],
        ];

        return parent::renderForm();
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitAdd'.$this->table) || Tools::isSubmit('submitAdd'.$this->table.'AndStay')) {
            $this->processCancellationUpdate();
            return;
        }

        parent::postProcess();
    }

    /**
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderView()
    {
        if (!Validate::isLoadedObject($this->object)) {
            return '';
        }

        $summary = $this->getCancellationSummary((int)$this->object->id);
        $details = $this->getCancellationDetails((int)$this->object->id);
        $orderSlips = $this->getCancellationOrderSlips((int)$this->object->id);
        $currency = $this->context->currency;

        if ($summary) {
            if ((int)$summary['id_currency'] > 0) {
                $orderCurrency = new Currency((int)$summary['id_currency']);
                if (Validate::isLoadedObject($orderCurrency)) {
                    $currency = $orderCurrency;
                }
            }

            $summary['order_url'] = $this->context->link->getAdminLink('AdminOrders', true, [
                'vieworder' => true,
                'id_order' => (int)$summary['id_order'],
            ]);
            $summary['customer_url'] = $this->context->link->getAdminLink('AdminCustomers', true, [
                'viewcustomer' => true,
                'id_customer' => (int)$summary['id_customer'],
            ]);
        }

        foreach ($orderSlips as &$orderSlip) {
            $orderSlip['url'] = $this->context->link->getAdminLink(
                'AdminSlip',
                true,
                [],
                ['id_order_slip' => (int)$orderSlip['id_order_slip']]
            );
        }
        unset($orderSlip);

        $this->tpl_view_vars = [
            'order_cancellation' => $this->object,
            'summary' => $summary,
            'details' => $details,
            'order_slips' => $orderSlips,
            'status_label' => $this->getStatusLabel((string)$this->object->status),
            'refund_method_label' => $this->getRefundMethodLabel((string)$this->object->requested_refund_method),
            'currency' => $currency,
            'has_quoted_refund_total' => $this->object->quoted_refund_total_tax_incl !== null && $this->object->quoted_refund_total_tax_incl !== '',
            'has_quoted_fee' => $this->object->quoted_fee_tax_incl !== null && $this->object->quoted_fee_tax_incl !== '',
        ];

        return parent::renderView();
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function displayStatusLabel($value): string
    {
        return $this->getStatusLabel((string)$value);
    }

    /**
     * @param string|null $value
     *
     * @return string
     */
    public function displayRefundMethodLabel($value): string
    {
        $refundMethod = (string)$value;

        return $refundMethod !== '' ? $this->getRefundMethodLabel($refundMethod) : '-';
    }

    /**
     * @return array
     */
    private function getStatusList(): array
    {
        return [
            OrderCancellation::STATUS_REQUESTED => $this->l('Requested'),
            OrderCancellation::STATUS_APPLIED => $this->l('Applied'),
            OrderCancellation::STATUS_REJECTED => $this->l('Rejected'),
            OrderCancellation::STATUS_EXPIRED => $this->l('Expired'),
        ];
    }

    /**
     * @param string $status
     *
     * @return string
     */
    private function getStatusLabel(string $status): string
    {
        return $this->getStatusList()[$status] ?? $status;
    }

    /**
     * @return array
     */
    private function getStatusOptions(): array
    {
        $options = [];
        foreach ($this->getStatusList() as $id => $name) {
            $options[] = [
                'id' => $id,
                'name' => $name,
            ];
        }

        return $options;
    }

    /**
     * @param string $refundMethod
     *
     * @return string
     */
    private function getRefundMethodLabel(string $refundMethod): string
    {
        if ($refundMethod === '') {
            return '';
        }

        return $this->getRefundMethodList()[$refundMethod] ?? str_replace('_', ' ', $refundMethod);
    }

    /**
     * @return array
     */
    private function getRefundMethodList(): array
    {
        return [
            OrderCancellation::REFUND_METHOD_STORE_CREDIT => $this->l('Store credit'),
            OrderCancellation::REFUND_METHOD_ORIGINAL_PAYMENT => $this->l('Original payment'),
        ];
    }

    /**
     * @return array
     */
    private function getRefundMethodOptions(): array
    {
        $options = [
            [
                'id' => '',
                'name' => '-',
            ],
        ];

        foreach ($this->getRefundMethodList() as $id => $name) {
            $options[] = [
                'id' => $id,
                'name' => $name,
            ];
        }

        return $options;
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    private function processCancellationUpdate(): void
    {
        if (!$this->hasEditPermission()) {
            $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            return;
        }

        $idOrderCancellation = Tools::getIntValue('id_order_cancellation');
        $orderCancellation = new OrderCancellation($idOrderCancellation);
        if (!Validate::isLoadedObject($orderCancellation)) {
            $this->errors[] = Tools::displayError('The order cancellation is invalid.');
            return;
        }

        $status = (string)Tools::getValue('status');
        if (!array_key_exists($status, $this->getStatusList())) {
            $this->errors[] = Tools::displayError('The selected status is invalid.');
            return;
        }

        $requestedRefundMethod = (string)Tools::getValue('requested_refund_method', '');
        if (!in_array($requestedRefundMethod, [
            '',
            OrderCancellation::REFUND_METHOD_STORE_CREDIT,
            OrderCancellation::REFUND_METHOD_ORIGINAL_PAYMENT,
        ], true)) {
            $this->errors[] = Tools::displayError('The selected refund method is invalid.');
            return;
        }

        $orderCancellation->status = $status;
        $orderCancellation->requested_refund_method = $requestedRefundMethod !== '' ? $requestedRefundMethod : null;

        if (!$orderCancellation->update(true)) {
            $this->errors[] = Tools::displayError('The order cancellation could not be updated.');
            return;
        }

        if (Tools::isSubmit('submitAdd'.$this->table.'AndStay')) {
            Tools::redirectAdmin(static::$currentIndex.'&conf=4&token='.$this->token.'&update'.$this->table.'&'.$this->identifier.'='.(int)$orderCancellation->id);
        }

        Tools::redirectAdmin(static::$currentIndex.'&conf=4&token='.$this->token);
    }

    /**
     * @param int $idOrderCancellation
     *
     * @return array|null
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getCancellationSummary(int $idOrderCancellation): ?array
    {
        $row = Db::getInstance()->getRow(
            (new DbQuery())
                ->select('o.`id_order`, o.`id_currency`, o.`reference`, c.`id_customer`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer`, CONCAT(e.`firstname`, \' \', e.`lastname`) AS `employee`')
                ->from('order_cancellation', 'oc')
                ->leftJoin('orders', 'o', 'o.`id_order` = oc.`id_order`')
                ->leftJoin('customer', 'c', 'c.`id_customer` = o.`id_customer`')
                ->leftJoin('employee', 'e', 'e.`id_employee` = oc.`id_employee`')
                ->where('oc.`id_order_cancellation` = '.(int)$idOrderCancellation)
        );

        return $row ?: null;
    }

    /**
     * @param int $idOrderCancellation
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getCancellationDetails(int $idOrderCancellation): array
    {
        return Db::getInstance()->getArray(
            (new DbQuery())
                ->select('ocd.`id_order_detail`, od.`product_reference`, od.`product_name`, od.`product_quantity` AS `ordered_quantity`, ocd.`product_quantity` AS `cancelled_quantity`')
                ->from('order_cancellation_detail', 'ocd')
                ->leftJoin('order_detail', 'od', 'od.`id_order_detail` = ocd.`id_order_detail`')
                ->where('ocd.`id_order_cancellation` = '.(int)$idOrderCancellation)
                ->orderBy('ocd.`id_order_cancellation_detail` ASC')
        );
    }

    /**
     * @param int $idOrderCancellation
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getCancellationOrderSlips(int $idOrderCancellation): array
    {
        return Db::getInstance()->getArray(
            (new DbQuery())
                ->select('os.`id_order_slip`, os.`date_add`, os.`total_products_tax_incl`, os.`adjustment_fee_tax_incl`')
                ->from('order_slip', 'os')
                ->where('os.`reason_entity_type` = '.self::REASON_ENTITY_TYPE_ORDER_CANCELLATION)
                ->where('os.`reason_id_entity` = '.(int)$idOrderCancellation)
                ->orderBy('os.`id_order_slip` ASC')
        );
    }

}
