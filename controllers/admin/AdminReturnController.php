<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 * @author    thirty bees <contact@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2024 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

/**
 * Class AdminReturnControllerCore
 *
 * @property OrderReturn|null $object
 */
class AdminReturnControllerCore extends AdminController
{
    /**
     * AdminReturnControllerCore constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->table = 'order_return';
        $this->className = 'OrderReturn';
        $this->_select = 'ors.color, orsl.`name`, o.`id_shop`, o.`reference`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer`';
        $this->_join = 'LEFT JOIN '._DB_PREFIX_.'order_return_state ors ON (ors.`id_order_return_state` = a.`state`)';
        $this->_join .= 'LEFT JOIN '._DB_PREFIX_.'order_return_state_lang orsl ON (orsl.`id_order_return_state` = a.`state` AND orsl.`id_lang` = '.(int) $this->context->language->id.')';
        $this->_join .= ' LEFT JOIN '._DB_PREFIX_.'orders o ON (o.`id_order` = a.`id_order`)';
        $this->_join .= ' LEFT JOIN '._DB_PREFIX_.'customer c ON (o.`id_customer` = c.`id_customer`)';

        $this->fields_list = [
            'id_order_return' => [
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
            ],
            'reference' => [
                'title' => $this->l('Order Reference'),
                'filter_key' => 'o!reference',
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
                'callback' => 'getOrderLink'
            ],
            'customer' => [
                'title' => $this->l('Customer'),
                'havingFilter' => true,
                'callback' => 'getCustomerLink',
            ],
            'name' => [
                'title' => $this->l('Status'),
                'color' => 'color',
                'width' => 'auto',
                'align' => 'left'
            ],
            'date_add' => [
                'title' => $this->l('Date issued'),
                'width' => 150,
                'type' => 'date',
                'align' => 'right',
                'filter_key' => 'a!date_add'
            ],
        ];

        $this->fields_options = [
            'general' => [
                'title'  => $this->l('Merchandise return (RMA) options'),
                'fields' => [
                    'PS_ORDER_RETURN'         => [
                        'title' => $this->l('Enable returns'),
                        'desc'  => $this->l('Would you like to allow merchandise returns in your shop?'),
                        'cast'  => 'intval', 'type' => 'bool',
                    ],
                    'PS_ORDER_RETURN_NB_DAYS' => [
                        'title' => $this->l('Time limit of validity'),
                        'desc'  => $this->l('How many days after the delivery date does the customer have to return a product?'),
                        'cast'  => 'intval',
                        'type'  => 'text',
                        'size'  => '2',
                    ],
                    'PS_RETURN_PREFIX'        => [
                        'title' => $this->l('Returns prefix'),
                        'desc'  => $this->l('Prefix used for return name (e.g. RE00001).'),
                        'size'  => 6,
                        'type'  => 'textLang',
                    ],
                    OrderReturn::CONFIG_RETURN_ADDRESS => [
                        'title' => $this->l('Return address'),
                        'desc'  => $this->l('This address is shown to customers in the return instructions and on the return slip.'),
                        'type'  => 'textarea',
                        'rows'  => 4,
                        'cols'  => 50,
                        'defaultValue' => OrderReturn::DEFAULT_RETURN_ADDRESS,
                    ],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        parent::__construct();

        $this->_where = Shop::addSqlRestriction(false, 'o');
        $this->_use_found_rows = false;
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();

        if (Tools::isSubmit('showReturnSettings')) {
            $this->page_header_toolbar_btn['back_to_returns'] = [
                'href' => $this->context->link->getAdminLink('AdminReturn'),
                'desc' => $this->l('Back to returns'),
                'icon' => 'process-icon-back',
            ];
        } else {
            if (Tools::getIntValue('id_order_return') > 0 && Tools::isSubmit('updateorder_return')) {
                $this->page_header_toolbar_btn['back_to_customer_service'] = [
                    'href' => $this->context->link->getAdminLink('AdminCustomerThreads'),
                    'desc' => $this->l('Back to customer service'),
                    'icon' => 'process-icon-back',
                ];
            }
            $this->page_header_toolbar_btn['settings'] = [
                'href' => $this->context->link->getAdminLink('AdminReturn', true, ['showReturnSettings' => 1]),
                'desc' => $this->l('Settings'),
                'icon' => 'process-icon-cogs',
            ];
        }
    }

    public function renderList()
    {
        return Tools::isSubmit('showReturnSettings') ? '' : parent::renderList();
    }

    public function renderOptions()
    {
        return Tools::isSubmit('showReturnSettings') ? parent::renderOptions() : '';
    }

    /**
     * @throws PrestaShopException
     */
    public function setMedia()
    {
        parent::setMedia();

        if (Tools::getIntValue('id_order_return') > 0 && Tools::isSubmit('updateorder_return')) {
            $themeBaseUri = __PS_BASE_URI__.$this->admin_webpath.'/themes/'.$this->bo_theme;
            $this->addJS(_PS_MODULE_DIR_.'tb_framework/views/js/components/file_upload.js');
            $this->addCSS($themeBaseUri.'/css/customer_thread_workspace.css');
            $this->addJS($themeBaseUri.'/js/customer_thread_workspace.js');
        }
    }

    /**
     * Render form
     *
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Return Merchandise Authorization (RMA)'),
                'icon' => 'icon-clipboard',
            ],
            'input'  => [
                [
                    'type' => 'hidden',
                    'name' => 'id_order',
                ],
                [
                    'type' => 'hidden',
                    'name' => 'id_customer',
                ],
                [
                    'type'     => 'text_customer',
                    'label'    => $this->l('Customer'),
                    'name'     => '',
                    'size'     => '',
                    'required' => false,
                ],
                [
                    'type'     => 'text_order',
                    'label'    => $this->l('Order'),
                    'name'     => '',
                    'size'     => '',
                    'required' => false,
                ],
                [
                    'type'     => 'free',
                    'label'    => $this->l('Customer explanation'),
                    'name'     => 'question',
                    'size'     => '',
                    'required' => false,
                ],
                [
                    'type'     => 'select',
                    'label'    => $this->l('Status'),
                    'name'     => 'state',
                    'required' => false,
                    'options'  => [
                        'query' => OrderReturnState::getOrderReturnStates($this->context->language->id),
                        'id'    => 'id_order_return_state',
                        'name'  => 'name',
                    ],
                    'desc'     => $this->l('Merchandise return (RMA) status.'),
                ],
                [
                    'type'     => 'list_products',
                    'label'    => $this->l('Products'),
                    'name'     => '',
                    'size'     => '',
                    'required' => false,
                    'desc'     => $this->l('List of products in return package.'),
                ],
                [
                    'type'     => 'pdf_order_return',
                    'label'    => $this->l('Return slip'),
                    'name'     => '',
                    'size'     => '',
                    'required' => false,
                    'desc'     => $this->l('The link is only available after validation and before the parcel gets delivered.'),
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
            'buttons' => [
                'save-and-stay' => [
                    'title' => $this->l('Save and stay'),
                    'name'  => 'submitAdd'.$this->table.'AndStay',
                    'type'  => 'submit',
                    'class' => 'btn btn-default pull-right',
                    'icon'  => 'process-icon-save',
                ],
            ],
        ];

        $order = new Order($this->object->id_order);
        $quantityDisplayed = [];
        // Customized products */
        $returnedCustomizations = OrderReturn::getReturnedCustomizedProducts((int) ($this->object->id_order));
        foreach ($returnedCustomizations as $returnedCustomization) {
            $quantityDisplayed[(int) $returnedCustomization['id_order_detail']] = isset($quantityDisplayed[(int) $returnedCustomization['id_order_detail']]) ? $quantityDisplayed[(int) $returnedCustomization['id_order_detail']] + (int) $returnedCustomization['product_quantity'] : (int) $returnedCustomization['product_quantity'];
        }

        // Classic products
        $products = OrderReturn::getOrdersReturnProducts($this->object->id, $order);
        foreach ($products as &$product) {
            $orderDetail = new OrderDetail((int)$product['id_order_detail']);
            $product['return_registered_quantity'] = (int)$product['product_quantity'];
            $product['return_quantity_max'] = Validate::isLoadedObject($orderDetail) ? (int)$orderDetail->product_quantity : (int)$product['product_quantity'];
            $product['return_received_quantity'] = Validate::isLoadedObject($orderDetail) ? (int)$orderDetail->product_quantity_return : 0;
            $product['return_reinjected_quantity'] = Validate::isLoadedObject($orderDetail) ? (int)$orderDetail->product_quantity_reinjected : 0;
        }
        unset($product);

        // Prepare customer explanation for display
        $this->object->question = '<span class="normal-text">'.nl2br($this->object->question).'</span>';

        $customer = new Customer((int)$this->object->id_customer);
        $workspaceProvider = new CustomerThreadWorkspaceDataProvider($this->context);
        $conversation = $workspaceProvider->getEntityConversation(
            (int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE,
            (int)$this->object->id,
            Validate::isLoadedObject($customer) ? $customer : null,
            Validate::isLoadedObject($order) ? $order : null,
            Validate::isLoadedObject($customer) && (int)$customer->id_lang > 0
                ? (int)$customer->id_lang
                : (int)$this->context->language->id,
            sprintf(
                $this->l('Up to %1$d files and %2$d MB in total.'),
                CustomerMessageAttachment::getMaximumAttachmentCount(),
                CustomerMessageAttachment::getMaximumEmailTotalSizeMb()
            )
        );
        $templateDirectory = rtrim((string)$this->context->smarty->getTemplateDir(0), '/\\').DIRECTORY_SEPARATOR;

        $this->tpl_form_vars = [
            'customer'               => $customer,
            'url_customer'           => $this->context->link->getAdminLink('AdminCustomers', true, [
                'id_customer' => (int)$this->object->id_customer,
                'viewcustomer' => 1,
            ]),
            'text_order'             => sprintf($this->l('Order #%1$d from %2$s'), $order->id, Tools::displayDate($order->date_upd)),
            'url_order'              => $this->context->link->getAdminLink('AdminOrders', true, [
                'id_order' => (int)$order->id,
                'vieworder' => 1,
            ]),
            'picture_folder'         => _THEME_PROD_PIC_DIR_,
            'returnedCustomizations' => $returnedCustomizations,
            'customizedDatas'        => Product::getAllCustomizedDatas((int) ($order->id_cart)),
            'products'               => $products,
            'quantityDisplayed'      => $quantityDisplayed,
            'id_order_return'        => $this->object->id,
            'state_order_return'     => $this->object->state,
            'thread'                 => $conversation['thread'],
            'messages'               => $conversation['messages'],
            'first_message'          => $conversation['first_message'],
            'message_form'           => $conversation['message_form'],
            'conversation_template'  => $templateDirectory.'controllers'.DIRECTORY_SEPARATOR.'customer_threads'.DIRECTORY_SEPARATOR.'helpers'.DIRECTORY_SEPARATOR.'view'.DIRECTORY_SEPARATOR.'conversation_panel.tpl',
        ];

        return parent::renderForm();
    }

    /**
     * Initialize toolbar
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    public function initToolbar()
    {
        // If display list, we don't want the "add" button
        if (!$this->display || $this->display == 'list') {
            return;
        } elseif ($this->display != 'options') {
            $this->toolbar_btn['save-and-stay'] = [
                'short'      => 'SaveAndStay',
                'href'       => '#',
                'desc'       => $this->l('Save and stay'),
                'force_desc' => true,
            ];
        }

        parent::initToolbar();
    }

    /**
     * Post processing
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        if (Tools::isSubmit('deleteorder_return_detail')) {
            if ($this->hasDeletePermission()) {
                if (($idOrderDetail = Tools::getIntValue('id_order_detail')) && Validate::isUnsignedId($idOrderDetail)) {
                    if (($idOrderReturn = Tools::getIntValue('id_order_return')) && Validate::isUnsignedId($idOrderReturn)) {
                        $orderReturn = new OrderReturn($idOrderReturn);
                        if (!Validate::isLoadedObject($orderReturn)) {
                            $this->errors[] = Tools::displayError('Order return not found');
                        } else {
                            if ((int)($orderReturn->countProduct()) > 1) {
                                if (OrderReturn::deleteOrderReturnDetail($idOrderReturn, $idOrderDetail, Tools::getIntValue('id_customization', 0))) {
                                    Tools::redirectAdmin(static::$currentIndex . '&conf=4token=' . $this->token);
                                } else {
                                    $this->errors[] = Tools::displayError('An error occurred while deleting the details of your order return.');
                                }
                            } else {
                                $this->errors[] = Tools::displayError('You need at least one product.');
                            }
                        }
                    } else {
                        $this->errors[] = Tools::displayError('The order return is invalid.');
                    }
                } else {
                    $this->errors[] = Tools::displayError('The order return content is invalid.');
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        } elseif (Tools::isSubmit('submitAddorder_return') || Tools::isSubmit('submitAddorder_returnAndStay')) {
            if ($this->hasEditPermission()) {
                if (($idOrderReturn = Tools::getIntValue('id_order_return')) && Validate::isUnsignedId($idOrderReturn)) {
                    $orderReturn = new OrderReturn($idOrderReturn);
                    $order = new Order($orderReturn->id_order);
                    $newState = Tools::getIntValue('state');
                    $oldState = (int)$orderReturn->state;
                    $quantityTargets = $this->collectReturnQuantityTargets($orderReturn);
                    if (count($this->errors)) {
                        return;
                    }

                    $orderReturn->state = $newState;
                    if ($this->applyReturnQuantityTargets($orderReturn, $quantityTargets) && $orderReturn->save()) {
                        if ($oldState !== $newState && !(bool)$orderReturn->migrated) {
                            $this->notifyReturnStatusChange($orderReturn, $order, $newState);
                        }

                        if (Tools::isSubmit('submitAddorder_returnAndStay')) {
                            Tools::redirectAdmin(static::$currentIndex.'&conf=4&token='.$this->token.'&updateorder_return&id_order_return='.(int) $idOrderReturn);
                        } else {
                            Tools::redirectAdmin(static::$currentIndex.'&conf=4&token='.$this->token);
                        }
                    }
                } else {
                    $this->errors[] = Tools::displayError('No order return ID has been specified.');
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            }
        }
        parent::postProcess();
    }

    private function notifyReturnStatusChange(OrderReturn $orderReturn, Order $order, int $newState): void
    {
        if ($newState === OrderReturn::STATE_PACKAGE_RECEIVED) {
            $eventKey = 'order_return_package_received';
            $message = sprintf(
                $this->l('Your return for order %s has arrived at our warehouse.'),
                (string)$order->reference
            );
        } elseif ($newState === OrderReturn::STATE_RETURN_COMPLETED) {
            $eventKey = 'order_return_completed';
            $message = $this->getReturnCompletedNotificationMessage($orderReturn, $order);
        } else {
            return;
        }

        (new CustomerServiceNotificationService())->notify(
            (int)$order->id_customer,
            $eventKey,
            'order_return:'.(int)$orderReturn->id.':'.$eventKey,
            $message,
            (int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE,
            (int)$orderReturn->id,
            $this->context->link->getModuleLink('genzo_crm', 'customer_service', [
                'view_case' => 1,
                'case_entity_type' => (int)\CoreExtension\EntityTypeEnum::ORDER_RETURN_VALUE,
                'case_id' => (int)$orderReturn->id,
            ])
        );
    }

    private function getReturnCompletedNotificationMessage(OrderReturn $orderReturn, Order $order): string
    {
        $slipRows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_order_slip`')
                ->from('order_slip')
                ->where('`reason_entity_type` = '.(int)RefundPolicy::REASON_ORDER_RETURN)
                ->where('`reason_id_entity` = '.(int)$orderReturn->id)
                ->orderBy('`id_order_slip` ASC')
        );
        if (!$slipRows) {
            return sprintf(
                $this->l('Your return for order %s has been completed.'),
                (string)$order->reference
            );
        }

        $idsOrderSlip = array_map('intval', array_column($slipRows, 'id_order_slip'));
        $creditTotal = 0.0;
        foreach ($idsOrderSlip as $idOrderSlip) {
            $orderSlip = new OrderSlip($idOrderSlip);
            if (Validate::isLoadedObject($orderSlip)) {
                $creditTotal += $orderSlip->getRefundTotalTaxIncl();
            }
        }

        $paymentRows = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`payment_method`, `amount`')
                ->from('order_payment')
                ->where('`id_order_slip` IN ('.implode(',', $idsOrderSlip).')')
                ->where('`status` = \''.pSQL(OrderPayment::STATUS_DONE).'\'')
                ->orderBy('`id_order_payment` ASC')
        );
        $refundTotal = 0.0;
        $paymentMethods = [];
        foreach ($paymentRows ?: [] as $paymentRow) {
            $refundTotal += abs((float)$paymentRow['amount']);
            $paymentMethod = trim((string)$paymentRow['payment_method']);
            if ($paymentMethod !== '') {
                $paymentMethods[$paymentMethod] = true;
            }
        }

        if ($refundTotal > 0.0) {
            return sprintf(
                $this->l('Your return for order %1$s has been completed. We refunded %2$s via %3$s.'),
                (string)$order->reference,
                Tools::displayPrice(Tools::roundPrice($refundTotal), (int)$order->id_currency),
                implode(' + ', array_keys($paymentMethods)) ?: (string)$order->payment
            );
        }

        if ($creditTotal > 0.0) {
            return sprintf(
                $this->l('Your return for order %1$s has been completed. A credit slip of %2$s has been created.'),
                (string)$order->reference,
                Tools::displayPrice(Tools::roundPrice($creditTotal), (int)$order->id_currency)
            );
        }

        return sprintf(
            $this->l('Your return for order %s has been completed.'),
            (string)$order->reference
        );
    }

    protected function collectReturnQuantityTargets(OrderReturn $orderReturn): array
    {
        $registeredQuantities = Tools::getValue('return_registered_quantity', []);
        $receivedQuantities = Tools::getValue('return_received_quantity', []);
        $restockedQuantities = Tools::getValue('return_restocked_quantity', []);

        if (!is_array($registeredQuantities)) {
            return [];
        }

        $targets = [];
        $hasRegisteredQuantity = false;
        foreach ($registeredQuantities as $idOrderDetail => $rawRegisteredQuantity) {
            $idOrderDetail = (int)$idOrderDetail;
            $orderDetail = new OrderDetail($idOrderDetail);
            if (!Validate::isLoadedObject($orderDetail) || (int)$orderDetail->id_order !== (int)$orderReturn->id_order) {
                $this->errors[] = Tools::displayError('The order return content is invalid.');
                return [];
            }

            $registeredQuantity = (int)$rawRegisteredQuantity;
            $receivedQuantity = is_array($receivedQuantities) && array_key_exists($idOrderDetail, $receivedQuantities)
                ? (int)$receivedQuantities[$idOrderDetail]
                : (int)$orderDetail->product_quantity_return;
            $restockedQuantity = is_array($restockedQuantities) && array_key_exists($idOrderDetail, $restockedQuantities)
                ? (int)$restockedQuantities[$idOrderDetail]
                : (int)$orderDetail->product_quantity_reinjected;
            $orderedQuantity = (int)$orderDetail->product_quantity;

            if ($registeredQuantity < 0 || $receivedQuantity < 0 || $restockedQuantity < 0) {
                $this->errors[] = Tools::displayError('Returned quantities cannot be negative.');
                return [];
            }

            if ($registeredQuantity > $orderedQuantity || $receivedQuantity > $orderedQuantity) {
                $this->errors[] = Tools::displayError('Returned quantities cannot be greater than the ordered quantity.');
                return [];
            }

            if ($restockedQuantity > $receivedQuantity) {
                $this->errors[] = Tools::displayError('Restocked quantity cannot be greater than received quantity.');
                return [];
            }

            if ($registeredQuantity > 0) {
                $hasRegisteredQuantity = true;
            }

            $targets[$idOrderDetail] = [
                'registered' => $registeredQuantity,
                'received'   => $receivedQuantity,
                'restocked'  => $restockedQuantity,
            ];
        }

        if ($targets && !$hasRegisteredQuantity) {
            $this->errors[] = Tools::displayError('You need at least one product.');
            return [];
        }

        return $targets;
    }

    protected function applyReturnQuantityTargets(OrderReturn $orderReturn, array $quantityTargets): bool
    {
        foreach ($quantityTargets as $idOrderDetail => $target) {
            if (!OrderReturn::upsertReturnDetail((int)$orderReturn->id, (int)$idOrderDetail, (int)$target['registered'])) {
                $this->errors[] = Tools::displayError('An error occurred while saving the order return details.');
                return false;
            }

            $orderDetail = new OrderDetail((int)$idOrderDetail);
            if (!Validate::isLoadedObject($orderDetail)) {
                $this->errors[] = Tools::displayError('The order return content is invalid.');
                return false;
            }

            if ((int)$orderDetail->product_quantity_return !== (int)$target['received']) {
                $orderDetail->product_quantity_return = (int)$target['received'];
                if (!$orderDetail->update()) {
                    $this->errors[] = Tools::displayError('Returned quantities could not be booked.');
                    return false;
                }
            }

            if (!$this->setRestockedQuantity($orderDetail, (int)$target['restocked'])) {
                return false;
            }
        }

        return true;
    }

    protected function setRestockedQuantity(OrderDetail $orderDetail, int $targetQuantity): bool
    {
        $currentQuantity = (int)$orderDetail->product_quantity_reinjected;
        if ($targetQuantity === $currentQuantity) {
            return true;
        }

        $idMovementReason = $this->getCustomerReturnStockMovementReasonId();
        if ($targetQuantity > $currentQuantity) {
            $this->reinjectQuantity($orderDetail, $targetQuantity - $currentQuantity, false, $idMovementReason);
            return !count($this->errors);
        }

        return $this->removeRestockedQuantity($orderDetail, $currentQuantity - $targetQuantity, $idMovementReason);
    }

    protected function removeRestockedQuantity(OrderDetail $orderDetail, int $quantity, int $idMovementReason): bool
    {
        $quantityToRemove = min($quantity, (int)$orderDetail->product_quantity_reinjected);
        if ($quantityToRemove <= 0) {
            return true;
        }

        $product = new Product($orderDetail->product_id, false, (int)$this->context->language->id, (int)$orderDetail->id_shop);

        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $product->advanced_stock_management && (int)$orderDetail->id_warehouse !== 0) {
            $manager = StockManagerFactory::getManager();
            $warehouse = new Warehouse((int)$orderDetail->id_warehouse);
            if (!Validate::isLoadedObject($warehouse)) {
                $this->errors[] = Tools::displayError('This product cannot be re-stocked.');
                return false;
            }

            if (
                class_exists('\\ErpModule\\ProductExtensionErp')
                && class_exists('\\ErpModule\\ProductBundleErp')
                && \ErpModule\ProductExtensionErp::checkIfBundle($orderDetail->product_id)
            ) {
                $items = \ErpModule\ProductBundleErp::getItems($orderDetail->product_id, $orderDetail->product_attribute_id);
                foreach ($items as $item) {
                    if (!$this->removeAdvancedStockProduct(
                        $manager,
                        (int)$item['item_id_product'],
                        0,
                        $warehouse,
                        $quantityToRemove * (int)$item['quantity'],
                        $idMovementReason,
                        (int)$orderDetail->id_order
                    )) {
                        return false;
                    }
                }
            } elseif (Pack::isPack((int)$product->id)) {
                if ($product->shouldAdjustPackItemsQuantities()) {
                    $productsPack = Pack::getItems((int)$product->id, (int)Configuration::get('PS_LANG_DEFAULT'));
                    foreach ($productsPack as $productPack) {
                        if ((int)$productPack->advanced_stock_management === 1 && !$this->removeAdvancedStockProduct(
                            $manager,
                            (int)$productPack->id,
                            (int)$productPack->id_pack_product_attribute,
                            $warehouse,
                            (int)$productPack->pack_quantity * $quantityToRemove,
                            $idMovementReason,
                            (int)$orderDetail->id_order
                        )) {
                            return false;
                        }
                    }
                }
                if ($product->shouldAdjustPackQuantity() && !$this->removeAdvancedStockProduct(
                    $manager,
                    (int)$orderDetail->product_id,
                    (int)$orderDetail->product_attribute_id,
                    $warehouse,
                    $quantityToRemove,
                    $idMovementReason,
                    (int)$orderDetail->id_order,
                    1
                )) {
                    return false;
                }
            } elseif (!$this->removeAdvancedStockProduct(
                $manager,
                (int)$orderDetail->product_id,
                (int)$orderDetail->product_attribute_id,
                $warehouse,
                $quantityToRemove,
                $idMovementReason,
                (int)$orderDetail->id_order
            )) {
                return false;
            }

            $orderDetail->product_quantity_reinjected -= $quantityToRemove;
            if (!$orderDetail->update()) {
                $this->errors[] = Tools::displayError('Restocked quantities could not be corrected.');
                return false;
            }
            StockAvailable::synchronize((int)$orderDetail->product_id);

            return true;
        }

        if ((int)$orderDetail->id_warehouse === 0) {
            if (!StockAvailable::updateQuantity(
                (int)$orderDetail->product_id,
                (int)$orderDetail->product_attribute_id,
                -$quantityToRemove,
                (int)$orderDetail->id_shop
            )) {
                $this->errors[] = Tools::displayError('Restocked quantities could not be corrected.');
                return false;
            }

            $orderDetail->product_quantity_reinjected -= $quantityToRemove;
            if (!$orderDetail->update()) {
                $this->errors[] = Tools::displayError('Restocked quantities could not be corrected.');
                return false;
            }

            return true;
        }

        $this->errors[] = Tools::displayError('This product cannot be re-stocked.');
        return false;
    }

    protected function removeAdvancedStockProduct(
        $manager,
        int $idProduct,
        int $idProductAttribute,
        Warehouse $warehouse,
        int $quantity,
        int $idMovementReason,
        int $idOrder,
        int $ignorePack = 0
    ): bool {
        if ($quantity <= 0) {
            return true;
        }

        $removedProducts = $manager->removeProduct(
            $idProduct,
            $idProductAttribute,
            $warehouse,
            $quantity,
            $idMovementReason,
            true,
            $idOrder,
            $ignorePack,
            $this->context->employee
        );

        if (!is_array($removedProducts) || !$this->hasRemovedStock($removedProducts)) {
            $this->errors[] = Tools::displayError('Restocked quantities could not be corrected.');
            return false;
        }

        return true;
    }

    protected function hasRemovedStock(array $removedProducts): bool
    {
        foreach ($removedProducts as $removedProduct) {
            if (!is_array($removedProduct)) {
                continue;
            }
            if (isset($removedProduct['quantity']) && (int)$removedProduct['quantity'] > 0) {
                return true;
            }
            if ($this->hasRemovedStock($removedProduct)) {
                return true;
            }
        }

        return false;
    }

    protected function getCustomerReturnStockMovementReasonId(): int
    {
        $idMovementReason = (int)Configuration::get('PS_STOCK_CUSTOMER_RETURN_REASON');
        if ($idMovementReason > 0 && StockMvtReason::exists($idMovementReason)) {
            return $idMovementReason;
        }

        return (int)Configuration::get('PS_STOCK_MVT_INC_REASON_DEFAULT');
    }

    protected function reinjectQuantity($orderDetail, $qtyCancelProduct, $delete = false, $idMvtReason = null)
    {
        $reinjectableQuantity = (int) $orderDetail->product_quantity - (int) $orderDetail->product_quantity_reinjected;
        $quantityToReinject = $qtyCancelProduct > $reinjectableQuantity ? $reinjectableQuantity : $qtyCancelProduct;

        $product = new Product($orderDetail->product_id, false, (int) $this->context->language->id, (int) $orderDetail->id_shop);

        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $product->advanced_stock_management && $orderDetail->id_warehouse != 0) {
            $manager = StockManagerFactory::getManager();
            $movements = StockMvt::getNegativeStockMvts(
                $orderDetail->id_order,
                $orderDetail->product_id,
                $orderDetail->product_attribute_id,
                $quantityToReinject
            );
            $leftToReinject = $quantityToReinject;
            foreach ($movements as $movement) {
                if ($leftToReinject > $movement['physical_quantity']) {
                    $quantityToReinject = $movement['physical_quantity'];
                }

                $leftToReinject -= $quantityToReinject;
                if (
                    class_exists('\\ErpModule\\ProductExtensionErp')
                    && class_exists('\\ErpModule\\ProductBundleErp')
                    && \ErpModule\ProductExtensionErp::checkIfBundle($orderDetail->product_id)
                ) {
                    $items = \ErpModule\ProductBundleErp::getItems($orderDetail->product_id, $orderDetail->product_attribute_id);
                    foreach ($items as $item) {
                        $manager->addProduct(
                            $item['item_id_product'],
                            0,
                            new Warehouse($movement['id_warehouse']),
                            $quantityToReinject * $item['quantity'],
                            $idMvtReason,
                            $movement['price_te'],
                            true
                        );
                    }
                } elseif (Pack::isPack((int) $product->id)) {
                    if ($product->shouldAdjustPackItemsQuantities()) {
                        $productsPack = Pack::getItems((int) $product->id, (int) Configuration::get('PS_LANG_DEFAULT'));
                        foreach ($productsPack as $productPack) {
                            if ($productPack->advanced_stock_management == 1) {
                                $manager->addProduct(
                                    $productPack->id,
                                    $productPack->id_pack_product_attribute,
                                    new Warehouse($movement['id_warehouse']),
                                    $productPack->pack_quantity * $quantityToReinject,
                                    $idMvtReason,
                                    $movement['price_te'],
                                    true
                                );
                            }
                        }
                    }
                    if ($product->shouldAdjustPackQuantity()) {
                        $manager->addProduct(
                            $orderDetail->product_id,
                            $orderDetail->product_attribute_id,
                            new Warehouse($movement['id_warehouse']),
                            $quantityToReinject,
                            $idMvtReason,
                            $movement['price_te'],
                            true
                        );
                    }
                } else {
                    $manager->addProduct(
                        $orderDetail->product_id,
                        $orderDetail->product_attribute_id,
                        new Warehouse($movement['id_warehouse']),
                        $quantityToReinject,
                        $idMvtReason,
                        $movement['price_te'],
                        true
                    );
                }
                $orderDetail->product_quantity_reinjected += $quantityToReinject;
            }

            $idProduct = $orderDetail->product_id;
            $delete ? $orderDetail->delete() : $orderDetail->update();
            StockAvailable::synchronize($idProduct);
        } elseif ($orderDetail->id_warehouse == 0) {
            StockAvailable::updateQuantity(
                $orderDetail->product_id,
                $orderDetail->product_attribute_id,
                $quantityToReinject,
                $orderDetail->id_shop
            );

            if ($delete) {
                $orderDetail->delete();
            } else {
                $orderDetail->product_quantity_reinjected += $quantityToReinject;
                $orderDetail->update();
            }
        } else {
            $this->errors[] = Tools::displayError('This product cannot be re-stocked.');
        }
    }

    /**
     * @param int $reference
     * @param array $row
     * @return string
     * @throws PrestaShopException
     */
    public static function getOrderLink($reference, $row)
    {
        $params = [
            'vieworder'=> true,
            'id_order' => (int)$row['id_order']
        ];
        $link = Context::getContext()->link->getAdminLink('AdminOrders', true, $params);

        return '<a href="'.Tools::safeOutput($link).'">'.Tools::safeOutput($reference).'</a>';
    }

    public static function getCustomerLink($customer, $row)
    {
        $params = [
            'viewcustomer' => true,
            'id_customer'  => (int)$row['id_customer'],
        ];
        $link = Context::getContext()->link->getAdminLink('AdminCustomers', true, $params);

        return '<a href="'.Tools::safeOutput($link).'">'.Tools::safeOutput($customer).'</a>';
    }

}
