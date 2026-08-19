<?php

/**
 * Class AdminOrderServiceCasesControllerCore
 *
 * @property OrderServiceCase|null $object
 */
class AdminOrderServiceCasesControllerCore extends AdminController
{
    private const REASON_ENTITY_TYPE_ORDER_SERVICE_CASE = 73;

    /**
     * AdminOrderServiceCasesControllerCore constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->table = 'order_service_case';
        $this->className = 'OrderServiceCase';
        $this->identifier = 'id_order_service_case';

        $this->_select = 'o.`id_order`, o.`id_shop`, o.`id_currency`, o.`reference`, c.`id_customer`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer`, COALESCE(SUM(oscd.`product_quantity`), 0) AS `service_case_quantity`, CASE a.`case_type` WHEN 1 THEN \'Delivery damage\' WHEN 2 THEN \'Items missing\' WHEN 3 THEN \'Items broken\' WHEN 4 THEN \'Other\' ELSE \'Unknown\' END AS `case_type_label`, CASE a.`requested_solution` WHEN 1 THEN \'Replacement\' WHEN 2 THEN \'Alternate product\' WHEN 3 THEN \'Voucher\' ELSE \'-\' END AS `requested_solution_label`';
        $this->_join = ' LEFT JOIN `'._DB_PREFIX_.'orders` o ON (o.`id_order` = a.`id_order`)';
        $this->_join .= ' LEFT JOIN `'._DB_PREFIX_.'customer` c ON (c.`id_customer` = o.`id_customer`)';
        $this->_join .= ' LEFT JOIN `'._DB_PREFIX_.'order_service_case_detail` oscd ON (oscd.`id_order_service_case` = a.`id_order_service_case`)';
        $this->_group = ' GROUP BY a.`id_order_service_case`';

        $this->fields_list = [
            'id_order_service_case' => [
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
            ],
            'reference' => [
                'title' => $this->l('Order Reference'),
                'filter_key' => 'o!reference',
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
            ],
            'customer' => [
                'title' => $this->l('Customer'),
                'havingFilter' => true,
            ],
            'status' => [
                'title' => $this->l('Status'),
                'filter_key' => 'a!status',
            ],
            'case_type_label' => [
                'title' => $this->l('Case type'),
                'havingFilter' => true,
            ],
            'requested_solution_label' => [
                'title' => $this->l('Requested solution'),
                'havingFilter' => true,
            ],
            'service_case_quantity' => [
                'title' => $this->l('Quantity'),
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

        $this->_orderBy = 'id_order_service_case';
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
                'title' => $this->l('Order service case'),
                'icon' => 'icon-wrench',
            ],
            'input' => [
                [
                    'type' => 'hidden',
                    'name' => 'id_order_service_case',
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
                    'label' => $this->l('Case type'),
                    'name' => 'case_type',
                    'required' => true,
                    'options' => [
                        'query' => $this->getCaseTypeOptions(),
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Requested solution'),
                    'name' => 'requested_solution',
                    'required' => false,
                    'options' => [
                        'query' => $this->getRequestedSolutionOptions(),
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Replacement order ID'),
                    'name' => 'id_replacement_order',
                    'required' => false,
                    'class' => 'fixed-width-sm',
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
            $this->processServiceCaseUpdate();
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

        $summary = $this->getServiceCaseSummary((int)$this->object->id);
        $details = $this->getServiceCaseDetails((int)$this->object->id);
        $orderSlips = $this->getServiceCaseOrderSlips((int)$this->object->id);
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

            if ((int)$summary['id_replacement_order'] > 0) {
                $summary['replacement_order_url'] = $this->context->link->getAdminLink('AdminOrders', true, [
                    'vieworder' => true,
                    'id_order' => (int)$summary['id_replacement_order'],
                ]);
            }
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

        $requestedSolution = (int)$this->object->requested_solution;

        $this->tpl_view_vars = [
            'order_service_case' => $this->object,
            'summary' => $summary,
            'details' => $details,
            'order_slips' => $orderSlips,
            'status_label' => $this->getStatusLabel((string)$this->object->status),
            'case_type_label' => $this->getCaseTypeLabel((int)$this->object->case_type),
            'requested_solution_label' => $this->getRequestedSolutionLabel($requestedSolution > 0 ? $requestedSolution : null),
            'currency' => $currency,
        ];

        return parent::renderView();
    }

    /**
     * @return array
     */
    private function getStatusList(): array
    {
        return [
            OrderServiceCase::STATUS_OPEN => $this->l('Open'),
            OrderServiceCase::STATUS_WAITING => $this->l('Waiting'),
            OrderServiceCase::STATUS_RESOLVED => $this->l('Resolved'),
            OrderServiceCase::STATUS_REJECTED => $this->l('Rejected'),
            OrderServiceCase::STATUS_EXPIRED => $this->l('Expired'),
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
     * @return array
     */
    private function getCaseTypeList(): array
    {
        $labels = [];
        foreach (OrderServiceCase::getValidCaseTypes() as $caseType) {
            $labels[$caseType] = $this->l(OrderServiceCase::getCaseTypeLabelSource($caseType));
        }

        return $labels;
    }

    /**
     * @param int $caseType
     *
     * @return string
     */
    private function getCaseTypeLabel(int $caseType): string
    {
        return $this->getCaseTypeList()[$caseType] ?? $this->l('Unknown');
    }

    /**
     * @return array
     */
    private function getCaseTypeOptions(): array
    {
        $options = [];
        foreach ($this->getCaseTypeList() as $id => $name) {
            $options[] = [
                'id' => $id,
                'name' => $name,
            ];
        }

        return $options;
    }

    /**
     * @return array
     */
    private function getRequestedSolutionList(): array
    {
        $labels = [];
        foreach (OrderServiceCase::getValidRequestedSolutions() as $requestedSolution) {
            $labels[$requestedSolution] = $this->l(OrderServiceCase::getRequestedSolutionLabelSource($requestedSolution));
        }

        return $labels;
    }

    /**
     * @param int|null $requestedSolution
     *
     * @return string
     */
    private function getRequestedSolutionLabel(?int $requestedSolution): string
    {
        if ($requestedSolution === null) {
            return '';
        }

        return $this->getRequestedSolutionList()[$requestedSolution] ?? $this->l('Unknown');
    }

    /**
     * @return array
     */
    private function getRequestedSolutionOptions(): array
    {
        $options = [
            [
                'id' => '',
                'name' => '-',
            ],
        ];

        foreach ($this->getRequestedSolutionList() as $id => $name) {
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
    private function processServiceCaseUpdate(): void
    {
        if (!$this->hasEditPermission()) {
            $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            return;
        }

        $idOrderServiceCase = Tools::getIntValue('id_order_service_case');
        $serviceCase = new OrderServiceCase($idOrderServiceCase);
        if (!Validate::isLoadedObject($serviceCase)) {
            $this->errors[] = Tools::displayError('The order service case is invalid.');
            return;
        }

        $status = (string)Tools::getValue('status');
        if (!array_key_exists($status, $this->getStatusList())) {
            $this->errors[] = Tools::displayError('The selected status is invalid.');
            return;
        }

        $caseType = Tools::getIntValue('case_type');
        if (!array_key_exists($caseType, $this->getCaseTypeList())) {
            $this->errors[] = Tools::displayError('The selected case type is invalid.');
            return;
        }

        $requestedSolution = (string)Tools::getValue('requested_solution', '');
        $requestedSolutionId = $requestedSolution !== '' ? (int)$requestedSolution : null;
        if ($requestedSolutionId !== null && !array_key_exists($requestedSolutionId, $this->getRequestedSolutionList())) {
            $this->errors[] = Tools::displayError('The selected requested solution is invalid.');
            return;
        }

        $idReplacementOrder = Tools::getIntValue('id_replacement_order');
        if ($idReplacementOrder > 0) {
            $replacementOrder = new Order($idReplacementOrder);
            if (!Validate::isLoadedObject($replacementOrder)) {
                $this->errors[] = Tools::displayError('The replacement order is invalid.');
                return;
            }
        }

        $serviceCase->status = $status;
        $serviceCase->case_type = $caseType;
        $serviceCase->requested_solution = $requestedSolutionId;
        $serviceCase->id_replacement_order = $idReplacementOrder > 0 ? $idReplacementOrder : null;

        if (!$serviceCase->update(true)) {
            $this->errors[] = Tools::displayError('The order service case could not be updated.');
            return;
        }

        if (Tools::isSubmit('submitAdd'.$this->table.'AndStay')) {
            Tools::redirectAdmin(static::$currentIndex.'&conf=4&token='.$this->token.'&update'.$this->table.'&'.$this->identifier.'='.(int)$serviceCase->id);
        }

        Tools::redirectAdmin(static::$currentIndex.'&conf=4&token='.$this->token);
    }

    /**
     * @param int $idOrderServiceCase
     *
     * @return array|null
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getServiceCaseSummary(int $idOrderServiceCase): ?array
    {
        $row = Db::getInstance()->getRow(
            (new DbQuery())
                ->select('o.`id_order`, o.`id_currency`, o.`reference`, c.`id_customer`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer`, CONCAT(e.`firstname`, \' \', e.`lastname`) AS `employee`, osc.`id_replacement_order`, ro.`reference` AS `replacement_reference`')
                ->from('order_service_case', 'osc')
                ->leftJoin('orders', 'o', 'o.`id_order` = osc.`id_order`')
                ->leftJoin('customer', 'c', 'c.`id_customer` = o.`id_customer`')
                ->leftJoin('employee', 'e', 'e.`id_employee` = osc.`id_employee`')
                ->leftJoin('orders', 'ro', 'ro.`id_order` = osc.`id_replacement_order`')
                ->where('osc.`id_order_service_case` = '.(int)$idOrderServiceCase)
        );

        return $row ?: null;
    }

    /**
     * @param int $idOrderServiceCase
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getServiceCaseDetails(int $idOrderServiceCase): array
    {
        return Db::getInstance()->getArray(
            (new DbQuery())
                ->select('oscd.`id_order_detail`, od.`product_reference`, od.`product_name`, od.`product_quantity` AS `ordered_quantity`, oscd.`product_quantity` AS `service_case_quantity`')
                ->from('order_service_case_detail', 'oscd')
                ->leftJoin('order_detail', 'od', 'od.`id_order_detail` = oscd.`id_order_detail`')
                ->where('oscd.`id_order_service_case` = '.(int)$idOrderServiceCase)
                ->orderBy('oscd.`id_order_service_case_detail` ASC')
        );
    }

    /**
     * @param int $idOrderServiceCase
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getServiceCaseOrderSlips(int $idOrderServiceCase): array
    {
        return Db::getInstance()->getArray(
            (new DbQuery())
                ->select('os.`id_order_slip`, os.`date_add`, os.`total_products_tax_incl`, os.`adjustment_fee_tax_incl`')
                ->from('order_slip', 'os')
                ->where('os.`reason_entity_type` = '.self::REASON_ENTITY_TYPE_ORDER_SERVICE_CASE)
                ->where('os.`reason_id_entity` = '.(int)$idOrderServiceCase)
                ->orderBy('os.`id_order_slip` ASC')
        );
    }
}
