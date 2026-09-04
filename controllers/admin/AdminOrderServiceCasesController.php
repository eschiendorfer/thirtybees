<?php

/**
 * Class AdminOrderServiceCasesControllerCore
 *
 * @property OrderServiceCase|null $object
 */
class AdminOrderServiceCasesControllerCore extends AdminController
{
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

        $this->_select = 'o.`id_order`, o.`id_shop`, o.`id_currency`, o.`reference`, c.`id_customer`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer`, COALESCE(SUM(oscd.`product_quantity`), 0) AS `service_case_quantity`';
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
            'case_type' => [
                'title' => $this->l('Case type'),
                'type' => 'select',
                'list' => $this->getCaseTypeList(),
                'filter_key' => 'a!case_type',
                'filter_type' => 'int',
                'callback' => 'displayCaseTypeLabel',
            ],
            'requested_solution' => [
                'title' => $this->l('Requested solution'),
                'type' => 'select',
                'list' => $this->getRequestedSolutionList(),
                'filter_key' => 'a!requested_solution',
                'filter_type' => 'int',
                'callback' => 'displayRequestedSolutionLabel',
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
        $this->addRowAction('view');
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    public function setMedia()
    {
        parent::setMedia();

        if (Tools::getIntValue('id_order_service_case') > 0 && Tools::isSubmit('vieworder_service_case')) {
            $themeBaseUri = __PS_BASE_URI__.$this->admin_webpath.'/themes/'.$this->bo_theme;
            $this->addJS(_PS_MODULE_DIR_.'tb_framework/views/js/components/file_upload.js');
            $this->addCSS($themeBaseUri.'/css/customer_thread_workspace.css');
            $this->addJS($themeBaseUri.'/js/customer_thread_workspace.js');
            $this->addJS($themeBaseUri.'/js/customer_service_work_items.js');
        }
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function initPageHeaderToolbar()
    {
        $idOrderServiceCase = Tools::getIntValue('id_order_service_case');
        if ($idOrderServiceCase > 0 && Tools::isSubmit('vieworder_service_case')) {
            $summary = $this->getServiceCaseSummary($idOrderServiceCase);
            if ($summary) {
                $title = [];
                if (!empty($summary['customer'])) {
                    $title[] = (string)$summary['customer'];
                }
                $title[] = $this->l('Service case').' #'.$idOrderServiceCase;
                if (!empty($summary['reference'])) {
                    $title[] = $this->l('Order').' '.(string)$summary['reference'];
                }
                $this->page_header_toolbar_title = implode(' – ', $title);
            }

            $this->page_header_toolbar_btn['back_to_customer_service'] = [
                'href' => $this->context->link->getAdminLink('AdminCustomerThreads'),
                'desc' => $this->l('Back to customer service'),
                'icon' => 'process-icon-back',
            ];
        }

        parent::initPageHeaderToolbar();
        $this->context->smarty->clearAssign('help_link');
    }

    /**
     * Update the service-case status from its workspace.
     */
    public function ajaxProcessUpdateServiceCaseSetting()
    {
        if (!$this->hasEditPermission()) {
            $this->respondServiceCaseSettingJson(false, $this->l('You do not have permission to edit this service case.'));
        }

        $serviceCase = new OrderServiceCase(Tools::getIntValue('id_order_service_case'));
        if (!Validate::isLoadedObject($serviceCase)) {
            $this->respondServiceCaseSettingJson(false, $this->l('The service case could not be found.'));
        }

        if ((string)Tools::getValue('field') !== 'status') {
            $this->respondServiceCaseSettingJson(false, $this->l('The selected setting is invalid.'));
        }

        $status = (string)Tools::getValue('value');
        if (!array_key_exists($status, $this->getStatusList())) {
            $this->respondServiceCaseSettingJson(false, $this->l('The selected status is invalid.'));
        }

        $serviceCase->status = $status;
        $updated = $serviceCase->update(true);
        $this->respondServiceCaseSettingJson(
            $updated,
            $updated ? $this->l('The service case has been updated.') : $this->l('The service case could not be updated.')
        );
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
        if (!$summary) {
            return '';
        }

        $details = $this->getServiceCaseDetails((int)$this->object->id);
        foreach ($details as &$detail) {
            $idProduct = (int)$detail['id_product'];
            $detail['admin_product_url'] = $idProduct > 0
                ? $this->context->link->getAdminLink('AdminProducts', true, [
                    'updateproduct' => true,
                    'id_product' => $idProduct,
                ])
                : '';
            $detail['product_url'] = $idProduct > 0 && !empty($detail['product_active'])
                ? $this->context->link->getProductLink($idProduct)
                : '';
        }
        unset($detail);
        $orderSlips = $this->getServiceCaseOrderSlips((int)$this->object->id);

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
        $conversation = $this->getConversationWorkspace($summary);
        $entityType = (int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE;
        $templateDirectory = rtrim((string)$this->context->smarty->getTemplateDir(0), '/\\').DIRECTORY_SEPARATOR;

        $this->tpl_view_vars = [
            'order_service_case' => $this->object,
            'summary' => $summary,
            'details' => $details,
            'order_slips' => $orderSlips,
            'status_options' => $this->getStatusList(),
            'case_type_label' => $this->getCaseTypeLabel((int)$this->object->case_type),
            'requested_solution_label' => $this->getRequestedSolutionLabel($requestedSolution > 0 ? $requestedSolution : null),
            'entity_type' => $entityType,
            'assigned_employee_id' => EntityEmployeeAssignment::getEmployeeId($entityType, (int)$this->object->id),
            'assignable_employees' => $this->getAssignableEmployeeOptions(),
            'assignment_update_url' => $this->context->link->getAdminLink('AdminCustomerThreads'),
            'service_case_setting_url' => $this->context->link->getAdminLink('AdminOrderServiceCases'),
            'thread_setting_url' => $this->context->link->getAdminLink('AdminCustomerThreads'),
            'thread' => $conversation['thread'],
            'messages' => $conversation['messages'],
            'first_message' => $conversation['first_message'],
            'message_form' => $conversation['message_form'],
            'conversation_template' => $templateDirectory.'controllers'.DIRECTORY_SEPARATOR.'customer_threads'.DIRECTORY_SEPARATOR.'helpers'.DIRECTORY_SEPARATOR.'view'.DIRECTORY_SEPARATOR.'conversation_panel.tpl',
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
     * @param string $value
     *
     * @return string
     */
    public function displayCaseTypeLabel($value): string
    {
        return $this->getCaseTypeLabel((int)$value);
    }

    /**
     * @param string|null $value
     *
     * @return string
     */
    public function displayRequestedSolutionLabel($value): string
    {
        $requestedSolution = (int)$value;

        return $requestedSolution > 0 ? $this->getRequestedSolutionLabel($requestedSolution) : '-';
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

    /** @return array<int, string> */
    private function getAssignableEmployeeOptions(): array
    {
        $options = [0 => $this->l('Unassigned')];
        foreach (Employee::getEmployees(true) as $employee) {
            $options[(int)$employee['id_employee']] = trim($employee['firstname'].' '.$employee['lastname']);
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $summary
     *
     * @return array<string, mixed>
     *
     * @throws PrestaShopException
     */
    private function getConversationWorkspace(array $summary): array
    {
        $entityType = (int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE;
        $idServiceCase = (int)$this->object->id;
        $customer = new Customer((int)$summary['id_customer']);
        $order = new Order((int)$summary['id_order']);
        $provider = new CustomerThreadWorkspaceDataProvider($this->context);
        $maximumCount = CustomerMessageAttachment::getMaximumAttachmentCount();
        $maximumEmailSizeMb = CustomerMessageAttachment::getMaximumEmailTotalSizeMb();

        return $provider->getEntityConversation(
            $entityType,
            $idServiceCase,
            Validate::isLoadedObject($customer) ? $customer : null,
            Validate::isLoadedObject($order) ? $order : null,
            (int)$summary['id_lang'],
            sprintf($this->l('Up to %1$d files and %2$d MB in total.'), $maximumCount, $maximumEmailSizeMb)
        );
    }

    protected function respondServiceCaseSettingJson(bool $success, string $message)
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->ajaxDie(json_encode([
            'success' => $success,
            'text' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
                ->select('o.`id_order`, o.`id_lang`, o.`reference`, c.`id_customer`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer`, osc.`id_replacement_order`, ro.`reference` AS `replacement_reference`')
                ->from('order_service_case', 'osc')
                ->leftJoin('orders', 'o', 'o.`id_order` = osc.`id_order`')
                ->leftJoin('customer', 'c', 'c.`id_customer` = o.`id_customer`')
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
                ->select('od.`product_id` AS `id_product`, od.`product_reference`, od.`product_name`, od.`product_quantity` AS `ordered_quantity`, oscd.`product_quantity` AS `service_case_quantity`, IFNULL(ps.`active`, 0) AS `product_active`')
                ->from('order_service_case_detail', 'oscd')
                ->leftJoin('order_detail', 'od', 'od.`id_order_detail` = oscd.`id_order_detail`')
                ->leftJoin('product_shop', 'ps', 'ps.`id_product` = od.`product_id` AND ps.`id_shop` = '.(int)$this->context->shop->id)
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
                ->select('os.`id_order_slip`')
                ->from('order_slip', 'os')
                ->where('os.`reason_entity_type` = '.(int)\CoreExtension\EntityTypeEnum::ORDER_SERVICE_CASE_VALUE)
                ->where('os.`reason_id_entity` = '.(int)$idOrderServiceCase)
                ->orderBy('os.`id_order_slip` ASC')
        );
    }
}
