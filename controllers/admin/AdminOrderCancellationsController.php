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

        $quoteMissingSql = 'a.`quoted_refund_total_tax_incl` IS NULL OR NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'order_cancellation_detail` ocd_quote WHERE ocd_quote.`id_order_cancellation` = a.`id_order_cancellation`) OR EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'order_cancellation_detail` ocd_missing WHERE ocd_missing.`id_order_cancellation` = a.`id_order_cancellation` AND ocd_missing.`quoted_product_amount_tax_incl` IS NULL)';
        $orderPaidSql = 'EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'order_history` oh_paid INNER JOIN `'._DB_PREFIX_.'order_state` os_paid ON os_paid.`id_order_state` = oh_paid.`id_order_state` AND os_paid.`paid` = 1 WHERE oh_paid.`id_order` = a.`id_order`)';
        $manualUnpaidSql = 'a.`quoted_refund_total_tax_incl` IS NOT NULL AND a.`quoted_shipping_tax_incl` < 0 AND COALESCE(SUM(ocd.`quoted_product_amount_tax_incl`), 0) + a.`quoted_shipping_tax_incl` <= 0';

        $this->_select = 'o.`id_order`, o.`id_shop`, o.`id_currency`, o.`reference`, c.`id_customer`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer`, COALESCE(SUM(ocd.`product_quantity`), 0) AS `cancelled_quantity`';
        $this->_select .= ', CASE'
            .' WHEN a.`status` = \''.pSQL(OrderCancellation::STATUS_DONE).'\' THEN \'completed\''
            .' WHEN a.`status` = \''.pSQL(OrderCancellation::STATUS_OPEN).'\' AND NOT ('.$orderPaidSql.') AND NOT ('.$manualUnpaidSql.') THEN \'adjust_order\''
            .' WHEN a.`status` = \''.pSQL(OrderCancellation::STATUS_QUANTITY_CANCELLED).'\' AND NOT ('.$quoteMissingSql.') AND a.`quoted_refund_total_tax_incl` > 0 THEN \'confirm_refund\''
            .' ELSE \'check_manually\''
            .' END AS `next_action`';
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
            'next_action' => [
                'title' => $this->l('Next action'),
                'type' => 'select',
                'list' => $this->getNextActionList(),
                'filter_key' => 'next_action',
                'filter_type' => 'string',
                'havingFilter' => true,
                'callback' => 'displayNextActionLabel',
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
        $this->addRowAction('view');
    }

    /**
     * @throws PrestaShopException
     */
    public function setMedia()
    {
        parent::setMedia();

        if (Tools::getIntValue('id_order_cancellation') > 0 && Tools::isSubmit('vieworder_cancellation')) {
            $themeBaseUri = __PS_BASE_URI__.$this->admin_webpath.'/themes/'.$this->bo_theme;
            $this->addJS(_PS_MODULE_DIR_.'tb_framework/views/js/components/file_upload.js');
            $this->addCSS($themeBaseUri.'/css/customer_thread_workspace.css');
            $this->addJS($themeBaseUri.'/js/customer_thread_workspace.js');
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
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function initPageHeaderToolbar()
    {
        $idOrderCancellation = Tools::getIntValue('id_order_cancellation');
        if ($idOrderCancellation > 0 && Tools::isSubmit('vieworder_cancellation')) {
            $summary = $this->getCancellationSummary($idOrderCancellation);
            if ($summary) {
                $title = [];
                if (!empty($summary['customer'])) {
                    $title[] = (string)$summary['customer'];
                }
                $title[] = $this->l('Cancellation').' #'.$idOrderCancellation;
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
     * @return void
     *
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        if (Tools::isSubmit('confirmCancellationRefund')) {
            $this->processCancellationRefund();
            return;
        }
        if (Tools::isSubmit('applyCancellationOrderAdjustment')) {
            $this->processCancellationOrderAdjustment();
            return;
        }
        parent::postProcess();
    }

    /**
     * Update one cancellation setting from the detail view.
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    public function ajaxProcessUpdateCancellationSetting()
    {
        if (!$this->hasEditPermission()) {
            $this->respondCancellationSettingJson(false, $this->l('You do not have permission to edit this cancellation.'));
        }

        $cancellation = new OrderCancellation(Tools::getIntValue('id_order_cancellation'));
        if (!Validate::isLoadedObject($cancellation)) {
            $this->respondCancellationSettingJson(false, $this->l('The cancellation could not be found.'));
        }

        $field = (string)Tools::getValue('field');
        $value = (string)Tools::getValue('value');
        if ($field === 'requested_refund_method') {
            if (!in_array($value, [
                '',
                OrderCancellation::REFUND_METHOD_STORE_CREDIT,
                OrderCancellation::REFUND_METHOD_ORIGINAL_PAYMENT,
            ], true)) {
                $this->respondCancellationSettingJson(false, $this->l('The selected refund method is invalid.'));
            }
            $cancellation->requested_refund_method = $value !== '' ? $value : null;
        } else {
            $this->respondCancellationSettingJson(false, $this->l('The selected setting is invalid.'));
        }

        $updated = $cancellation->update(true);
        $this->respondCancellationSettingJson(
            $updated,
            $updated ? $this->l('The cancellation has been updated.') : $this->l('The cancellation could not be updated.')
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

        $summary = $this->getCancellationSummary((int)$this->object->id);
        $details = $this->getCancellationDetails((int)$this->object->id);
        $orderSlips = $this->getCancellationOrderSlips((int)$this->object->id);
        $refundCompleted = $this->hasCompletedRefundPayment((int)$this->object->id);
        $order = $summary ? new Order((int)$summary['id_order']) : null;
        $orderIsPaid = $order && Validate::isLoadedObject($order) && $order->hasBeenPaid();
        $hasCustomerQuote = !empty($details);
        $quotedProductsTotal = 0.0;
        foreach ($details as $detail) {
            if ($detail['quoted_product_amount_tax_incl'] === null) {
                $hasCustomerQuote = false;
            } else {
                $quotedProductsTotal += (float)$detail['quoted_product_amount_tax_incl'];
            }
        }
        $currency = $this->context->currency;
        $isMigrated = (bool)$this->object->migrated;

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
            $summary['adjust_refund_url'] = $this->context->link->getAdminLink('AdminOrders', true, [
                'vieworder' => true,
                'id_order' => (int)$summary['id_order'],
                'prefill_credit_reason' => RefundPolicy::REASON_KEY_CANCELLATION,
                'prefill_credit_entity' => (int)$this->object->id,
                'prefill_refund_method' => (string)$this->object->requested_refund_method,
            ]);
            $summary['customer_url'] = $this->context->link->getAdminLink('AdminCustomers', true, [
                'viewcustomer' => true,
                'id_customer' => (int)$summary['id_customer'],
            ]);
        }

        $conversation = ['thread' => null, 'first_message' => null, 'messages' => [], 'message_form' => []];
        if ($summary) {
            $customer = new Customer((int)$summary['id_customer']);
            $workspaceProvider = new CustomerThreadWorkspaceDataProvider($this->context);
            $conversation = $workspaceProvider->getEntityConversation(
                (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE,
                (int)$this->object->id,
                Validate::isLoadedObject($customer) ? $customer : null,
                $order && Validate::isLoadedObject($order) ? $order : null,
                (int)$summary['id_lang'],
                sprintf(
                    $this->l('Up to %1$d files and %2$d MB in total.'),
                    CustomerMessageAttachment::getMaximumAttachmentCount(),
                    CustomerMessageAttachment::getMaximumEmailTotalSizeMb()
                )
            );
        }
        $templateDirectory = rtrim((string)$this->context->smarty->getTemplateDir(0), '/\\').DIRECTORY_SEPARATOR;

        foreach ($orderSlips as &$orderSlip) {
            $orderSlip['url'] = $this->context->link->getAdminLink(
                'AdminSlip',
                true,
                [],
                ['id_order_slip' => (int)$orderSlip['id_order_slip']]
            );
        }
        unset($orderSlip);

        $hasQuotedRefundTotal = $hasCustomerQuote
            && $this->object->quoted_refund_total_tax_incl !== null
            && $this->object->quoted_refund_total_tax_incl !== '';
        $financialRefundRequired = $hasQuotedRefundTotal
            && (float)$this->object->quoted_refund_total_tax_incl > 0.0;
        $requiresManualOrderReview = !$orderIsPaid
            && $hasQuotedRefundTotal
            && (float)$this->object->quoted_shipping_tax_incl < 0.0
            && Tools::roundPrice($quotedProductsTotal + (float)$this->object->quoted_shipping_tax_incl) <= 0.0;
        $canConfirmRefund = !$isMigrated
            && (string)$this->object->status === OrderCancellation::STATUS_QUANTITY_CANCELLED
            && $orderIsPaid
            && !$refundCompleted
            && $hasCustomerQuote
            && $financialRefundRequired
            && in_array((string)$this->object->requested_refund_method, [
                OrderCancellation::REFUND_METHOD_STORE_CREDIT,
                OrderCancellation::REFUND_METHOD_ORIGINAL_PAYMENT,
            ], true);
        $canApplyOrderAdjustment = !$isMigrated
            && (string)$this->object->status === OrderCancellation::STATUS_OPEN
            && !$orderIsPaid
            && !$requiresManualOrderReview;
        $hasIncompleteRefundSlip = !$refundCompleted && !empty($orderSlips);
        $financialStepCompleted = (string)$this->object->status === OrderCancellation::STATUS_DONE;
        $refundDestinationLabel = $this->getRefundMethodLabel((string)$this->object->requested_refund_method);
        if (
            (string)$this->object->requested_refund_method === OrderCancellation::REFUND_METHOD_ORIGINAL_PAYMENT
            && !empty($summary['payment'])
        ) {
            $refundDestinationLabel = (string)$summary['payment'];
        }

        $nextActionLabels = $this->getNextActionList();
        if ((string)$this->object->status === OrderCancellation::STATUS_DONE) {
            $nextActionLabel = $nextActionLabels['completed'];
        } elseif ($canApplyOrderAdjustment) {
            $nextActionLabel = $nextActionLabels['adjust_order'];
        } elseif ($canConfirmRefund) {
            $nextActionLabel = $nextActionLabels['confirm_refund'];
        } elseif (!$hasCustomerQuote) {
            $nextActionLabel = $nextActionLabels['check_manually'];
        } else {
            $nextActionLabel = $nextActionLabels['check_manually'];
        }

        $this->tpl_view_vars = [
            'order_cancellation' => $this->object,
            'summary' => $summary,
            'details' => $details,
            'order_slips' => $orderSlips,
            'refund_method_label' => $this->getRefundMethodLabel((string)$this->object->requested_refund_method),
            'refund_method_options' => $this->getRefundMethodOptions(),
            'refund_destination_label' => $refundDestinationLabel,
            'currency' => $currency,
            'has_customer_quote' => $hasCustomerQuote,
            'quoted_products_total' => Tools::roundPrice($quotedProductsTotal),
            'has_quoted_refund_total' => $hasQuotedRefundTotal,
            'has_quoted_fee' => $hasCustomerQuote && $this->object->quoted_fee_tax_incl !== null && $this->object->quoted_fee_tax_incl !== '',
            'has_quoted_shipping' => $hasCustomerQuote && $this->object->quoted_shipping_tax_incl !== null && $this->object->quoted_shipping_tax_incl !== '',
            'financial_refund_required' => $financialRefundRequired,
            'financial_step_completed' => $financialStepCompleted,
            'can_confirm_refund' => $canConfirmRefund,
            'can_apply_order_adjustment' => $canApplyOrderAdjustment,
            'requires_manual_order_review' => $requiresManualOrderReview,
            'has_incomplete_refund_slip' => $hasIncompleteRefundSlip,
            'order_is_paid' => $orderIsPaid,
            'refund_completed' => $refundCompleted,
            'is_migrated' => $isMigrated,
            'next_action_label' => $nextActionLabel,
            'refund_confirmation_url' => static::$currentIndex.'&token='.$this->token,
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
     * @param string|null $value
     *
     * @return string
     */
    public function displayRefundMethodLabel($value): string
    {
        $refundMethod = (string)$value;

        return $refundMethod !== '' ? $this->getRefundMethodLabel($refundMethod) : '-';
    }

    public function displayNextActionLabel($value): string
    {
        $labels = $this->getNextActionList();

        return $labels[(string)$value] ?? (string)$value;
    }

    /**
     * @return array<string, string>
     */
    private function getNextActionList(): array
    {
        return [
            'adjust_order' => $this->l('Adjust order'),
            'confirm_refund' => $this->l('Issue refund'),
            'check_manually' => $this->l('Check manually'),
            'completed' => $this->l('Completed'),
        ];
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
                ->select('o.`id_order`, o.`id_shop`, o.`id_lang`, o.`id_currency`, o.`reference`, o.`payment`, c.`id_customer`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer`, CONCAT(e.`firstname`, \' \', e.`lastname`) AS `employee`')
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
        $details = Db::getInstance()->getArray(
            (new DbQuery())
                ->select('ocd.`id_order_detail`, COALESCE(od.`product_reference`, ocd.`product_reference`) AS `product_reference`, COALESCE(od.`product_name`, ocd.`product_name`) AS `product_name`, COALESCE(od.`product_quantity`, ocd.`product_quantity`) AS `ordered_quantity`, ocd.`product_quantity` AS `cancelled_quantity`')
                ->select('ocd.`quoted_product_amount_tax_incl`, ocd.`quoted_fee_tax_incl`, ocd.`quoted_fee_policy`')
                ->from('order_cancellation_detail', 'ocd')
                ->leftJoin('order_detail', 'od', 'od.`id_order_detail` = ocd.`id_order_detail`')
                ->where('ocd.`id_order_cancellation` = '.(int)$idOrderCancellation)
                ->orderBy('ocd.`id_order_cancellation_detail` ASC')
        );
        $labels = [
            CancellationQuoteService::FEE_POLICY_STANDARD => $this->l('5% processing fee'),
            CancellationQuoteService::FEE_POLICY_LATE_KNOWN => $this->l('Fee waived: expected date plus one month exceeded'),
            CancellationQuoteService::FEE_POLICY_LATE_UNKNOWN => $this->l('Fee waived: unknown date and six months exceeded'),
            CancellationQuoteService::FEE_POLICY_STORE_CREDIT => $this->l('No fee: store credit'),
            CancellationQuoteService::FEE_POLICY_UNPAID => $this->l('Unpaid order'),
        ];
        foreach ($details as &$detail) {
            $detail['quoted_fee_policy_label'] = $labels[(string)$detail['quoted_fee_policy']]
                ?? (string)$detail['quoted_fee_policy'];
            $detail['quoted_refund_tax_incl'] = $detail['quoted_product_amount_tax_incl'] !== null
                ? Tools::roundPrice(max(
                    0.0,
                    (float)$detail['quoted_product_amount_tax_incl'] - (float)$detail['quoted_fee_tax_incl']
                ))
                : null;
        }
        unset($detail);

        return $details;
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

    private function processCancellationRefund(): void
    {
        $idOrderCancellation = Tools::getIntValue('id_order_cancellation');
        $cancellation = new OrderCancellation($idOrderCancellation);
        $order = Validate::isLoadedObject($cancellation) ? new Order((int)$cancellation->id_order) : null;
        if (!$order || !Validate::isLoadedObject($order)) {
            $this->errors[] = Tools::displayError('The order cancellation is invalid.');
            return;
        }
        if ((string)$cancellation->status !== OrderCancellation::STATUS_QUANTITY_CANCELLED) {
            $this->errors[] = Tools::displayError('Only a cancellation with cancelled quantities can be refunded.');
            return;
        }
        if (!in_array((string)$cancellation->requested_refund_method, [
            OrderCancellation::REFUND_METHOD_STORE_CREDIT,
            OrderCancellation::REFUND_METHOD_ORIGINAL_PAYMENT,
        ], true)) {
            $this->errors[] = Tools::displayError('The requested refund method is invalid.');
            return;
        }
        $existingSlips = $this->getCancellationOrderSlips($idOrderCancellation);
        if ($this->hasCompletedRefundPayment($idOrderCancellation)) {
            $this->errors[] = Tools::displayError('This cancellation has already been refunded.');
            return;
        }
        if (count($existingSlips) > 1) {
            $this->errors[] = Tools::displayError('This cancellation has multiple credit slips and must be checked manually.');
            return;
        }
        if (
            $cancellation->quoted_refund_total_tax_incl === null
            || (float)$cancellation->quoted_refund_total_tax_incl <= 0.0
        ) {
            $this->errors[] = Tools::displayError('This cancellation does not require a refund.');
            return;
        }

        $expected = Tools::roundPrice((float)$cancellation->quoted_refund_total_tax_incl);
        $creator = new RefundCreator($this->context);
        $idOrderSlip = $existingSlips ? (int)$existingSlips[0]['id_order_slip'] : 0;
        if ($idOrderSlip > 0) {
            $existingSlip = new OrderSlip($idOrderSlip);
            if (
                !Validate::isLoadedObject($existingSlip)
                || abs((float)$existingSlip->getRefundTotalTaxIncl() - $expected) > 0.01
            ) {
                $this->errors[] = Tools::displayError(sprintf(
                    'Die vorhandene Gutschrift #%d stimmt nicht mit dem gespeicherten Rückerstattungsbetrag überein. Prüfe den Fall in der Bestellung und führe keine automatische Auszahlung aus.',
                    $idOrderSlip
                ));
                return;
            }
        }
        if ($idOrderSlip <= 0) {
            $details = $this->getCancellationDetails($idOrderCancellation);
            $amounts = $quantities = [];
            foreach ($details as $detail) {
                if ($detail['quoted_product_amount_tax_incl'] === null) {
                    $this->errors[] = Tools::displayError('The customer quote is incomplete. Please process this case manually in the order.');
                    return;
                }
                $idOrderDetail = (int)$detail['id_order_detail'];
                $amounts[$idOrderDetail] = (float)$detail['quoted_product_amount_tax_incl'];
                $quantities[$idOrderDetail] = (int)$detail['cancelled_quantity'];
            }
            $result = (new RefundCalculator())->buildCreditSlipRequest($order, [
                'display_includes_tax' => true,
                'refund_method' => (string)$cancellation->requested_refund_method,
                'reason_entity_type' => RefundPolicy::REASON_KEY_CANCELLATION,
                'reason_id_entity' => (int)$cancellation->id,
                'product_amounts' => $amounts,
                'product_quantities' => $quantities,
                'shipping_adjustment' => (float)$cancellation->quoted_shipping_tax_incl,
                'cart_rule_adjustment' => 0,
                'fee_adjustment' => (float)$cancellation->quoted_fee_tax_incl,
            ]);
            if ($result['errors'] || !$result['request']) {
                foreach ($result['errors'] as $error) {
                    $this->errors[] = Tools::displayError($error);
                }
                return;
            }
            if (abs((float)$result['request']['effective_tax_incl'] - $expected) > 0.01) {
                $this->errors[] = Tools::displayError('The stored customer quote no longer matches the refund calculation. Please process this case manually.');
                return;
            }
            $idOrderSlip = $creator->createCreditSlipFromRequest($order, $result['request']);
            if ($idOrderSlip <= 0) {
                $this->errors[] = Tools::displayError('The credit slip could not be created.');
                return;
            }
        }

        $refundStatus = OrderPayment::STATUS_DONE;
        $refundPaymentLabel = '';
        if ((string)$cancellation->requested_refund_method === OrderCancellation::REFUND_METHOD_STORE_CREDIT) {
            $idTransaction = $creator->createRefundStoreCredit($order, $idOrderSlip, $expected);
            if ($idTransaction <= 0 || !$creator->addStoreCreditRefundPayment($order, $idOrderSlip, $expected, $idTransaction)) {
                $this->errors[] = Tools::displayError(sprintf(
                    'Gutschrift #%d ist vorhanden, aber die Guthabenbuchung konnte nicht abgeschlossen werden. Kontrolliere den Vorgang und wiederhole ihn mit derselben Gutschrift; lege keine zweite an.',
                    $idOrderSlip
                ));
                return;
            }
        } else {
            $module = Module::getInstanceByName((string)$order->module);
            if (!Validate::isLoadedObject($module) || !method_exists($module, 'refundOrderSlipPayment')) {
                $this->errors[] = Tools::displayError('The payment module does not support refunds.');
                return;
            }
            $refund = $module->refundOrderSlipPayment($order, $idOrderSlip, $expected);
            if (empty($refund['success'])) {
                $message = (string)($refund['message'] ?? 'The original payment refund could not be processed.');
                $this->errors[] = Tools::displayError(sprintf(
                    'Gutschrift #%1$d wurde erstellt, aber die Rückerstattung über Payrexx ist fehlgeschlagen: %2$s Kontrolliere den Vorgang in Payrexx. Wenn der Status unklar ist, führe keine zweite Rückerstattung aus und melde den Fall.',
                    $idOrderSlip,
                    $message
                ));
                return;
            }
            $status = (string)($refund['status'] ?? OrderPayment::STATUS_DONE);
            if (!in_array($status, [OrderPayment::STATUS_DONE, OrderPayment::STATUS_PENDING, OrderPayment::STATUS_FAILED, OrderPayment::STATUS_MANUAL], true)) {
                $status = OrderPayment::STATUS_DONE;
            }
            if (!OrderPayment::addRefundForOrderSlip(
                $order,
                $idOrderSlip,
                $expected,
                (string)($refund['payment_method'] ?? $order->payment),
                (string)($refund['payment_module'] ?? $order->module),
                $status,
                !empty($refund['transaction_id']) ? (string)$refund['transaction_id'] : null
            )) {
                $this->errors[] = Tools::displayError(sprintf(
                    'Payrexx hat die Rückerstattung für Gutschrift #%d angenommen, aber sie konnte lokal nicht gespeichert werden. Führe keine zweite Rückerstattung aus. Kontrolliere Payrexx und melde den Fall.',
                    $idOrderSlip
                ));
                return;
            }
            $refundStatus = $status;
            $refundPaymentLabel = trim((string)($refund['payment_method'] ?? $order->payment));
        }

        if ($refundStatus === OrderPayment::STATUS_FAILED) {
            $this->errors[] = Tools::displayError('The refund has not been completed. Check the payment before trying again.');
            return;
        }

        $cancellation->status = OrderCancellation::STATUS_DONE;
        if (!$cancellation->update(true)) {
            $this->errors[] = Tools::displayError('The refund was recorded, but the cancellation could not be completed. Do not issue a second refund; check the case and report the error.');
            return;
        }

        if ($refundStatus === OrderPayment::STATUS_DONE) {
            $this->notifyCancellationRefundCompleted($cancellation, $order, $expected, $refundPaymentLabel);
        }

        Tools::redirectAdmin(static::$currentIndex.'&view'.$this->table.'&'.$this->identifier.'='.(int)$cancellation->id.'&conf=4&token='.$this->token);
    }

    private function processCancellationOrderAdjustment(): void
    {
        $cancellation = new OrderCancellation(Tools::getIntValue('id_order_cancellation'));
        $order = Validate::isLoadedObject($cancellation) ? new Order((int)$cancellation->id_order) : null;
        if (!$order || !Validate::isLoadedObject($order) || $order->hasBeenPaid()) {
            $this->errors[] = Tools::displayError('Only an unpaid cancellation can adjust the order directly.');
            return;
        }

        $db = Db::getInstance();
        $db->execute('START TRANSACTION');
        try {
            $result = (new OrderAdjustmentService())->applyUnpaidCancellation($cancellation);
            if (empty($result['success'])) {
                throw new PrestaShopException((string)($result['error'] ?? 'The order could not be adjusted.'));
            }
            if (!$db->execute('COMMIT')) {
                throw new PrestaShopException('The order adjustment could not be committed.');
            }
        } catch (Throwable $exception) {
            $db->execute('ROLLBACK');
            $this->errors[] = Tools::displayError($exception->getMessage());
            return;
        }

        $order = new Order((int)$order->id);
        $isFullCancellation = empty($result['adjustment']['remaining']['has_products']);
        if ($isFullCancellation) {
            $message = sprintf(
                $this->l('Your order %s has been fully cancelled.'),
                (string)$order->reference
            );
            $eventKey = 'cancellation_order_cancelled';
        } else {
            $currency = new Currency((int)$order->id_currency);
            $message = sprintf(
                $this->l('Your order %1$s has been adjusted. The new order total is %2$s.'),
                (string)$order->reference,
                Tools::displayPrice((float)$order->total_paid_tax_incl, Validate::isLoadedObject($currency) ? $currency : null)
            );
            $eventKey = 'cancellation_order_adjusted';
        }
        $this->notifyCancellationCompleted($cancellation, $order, $message, $eventKey);

        Tools::redirectAdmin(static::$currentIndex.'&view'.$this->table.'&'.$this->identifier.'='.(int)$cancellation->id.'&conf=4&token='.$this->token);
    }

    private function notifyCancellationCompleted(OrderCancellation $cancellation, Order $order, string $message, string $eventKey): void
    {
        (new CustomerServiceNotificationService())->notify(
            (int)$order->id_customer,
            $eventKey,
            'order_cancellation:'.(int)$cancellation->id.':'.$eventKey,
            $message,
            (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE,
            (int)$cancellation->id,
            $this->context->link->getModuleLink('genzo_crm', 'customer_service', [
                'view_case' => 1,
                'case_entity_type' => (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE,
                'case_id' => (int)$cancellation->id,
            ])
        );
    }

    private function notifyCancellationRefundCompleted(
        OrderCancellation $cancellation,
        Order $order,
        float $amount,
        string $paymentLabel
    ): void {
        $currency = new Currency((int)$order->id_currency);
        $formattedAmount = Tools::displayPrice($amount, Validate::isLoadedObject($currency) ? $currency : null);
        if ((string)$cancellation->requested_refund_method === OrderCancellation::REFUND_METHOD_STORE_CREDIT) {
            $message = sprintf($this->l('Your store credit of %s has been created.'), $formattedAmount);
            $eventKey = 'cancellation_store_credit_created';
        } else {
            if ($paymentLabel === '') {
                try {
                    $paymentLabel = trim((string)$order->getDisplayPaymentMethodsText(' + ', false, false));
                } catch (Throwable $exception) {
                    $paymentLabel = trim((string)$order->payment);
                }
            }
            $message = sprintf(
                $this->l('The refund of %1$s to %2$s has been completed.'),
                $formattedAmount,
                $paymentLabel
            );
            $eventKey = 'cancellation_refund_completed';
        }

        (new CustomerServiceNotificationService())->notify(
            (int)$order->id_customer,
            $eventKey,
            'order_cancellation:'.(int)$cancellation->id.':refund_completed',
            $message,
            (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE,
            (int)$cancellation->id,
            $this->context->link->getModuleLink('genzo_crm', 'customer_service', [
                'view_case' => 1,
                'case_entity_type' => (int)\CoreExtension\EntityTypeEnum::ORDER_CANCELLATION_VALUE,
                'case_id' => (int)$cancellation->id,
            ])
        );
    }

    private function hasCompletedRefundPayment(int $idOrderCancellation): bool
    {
        return (bool)Db::readOnly()->getValue(
            (new DbQuery())
                ->select('1')
                ->from('order_slip', 'os')
                ->innerJoin('order_payment', 'op', 'op.`id_order_slip` = os.`id_order_slip`')
                ->where('os.`reason_entity_type` = '.self::REASON_ENTITY_TYPE_ORDER_CANCELLATION)
                ->where('os.`reason_id_entity` = '.(int)$idOrderCancellation)
                ->where("op.`status` IN ('done', 'pending', 'manual')")
        );
    }

    /**
     * @return void
     */
    protected function respondCancellationSettingJson(bool $success, string $message)
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->ajaxDie(json_encode([
            'success' => $success,
            'text' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

}
