<?php
/**
 * Copyright (C) 2025-2026 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <contact@thirtybees.com>
 * @copyright 2025-2026 thirty bees
 * @license   Open Software License (OSL 3.0)
 */

/**
 * Class AdminStoreCreditTransactionsController
 *
 * @property StoreCreditTransaction|null $object
 */
class AdminStoreCreditTransactionsControllerCore extends AdminController
{
    /**
     * @var bool
     */
    protected $addStoreCreditTransactionMode = false;

    /**
     * AdminStoreCreditTransactionsController constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'store_credit_transaction';
        $this->className = 'StoreCreditTransaction';
        $this->lang = false;
        $this->list_no_link = true;
        $this->_orderBy = 'date_add';
        $this->_orderWay = 'DESC';
        $this->bulk_actions = [];

        $idCustomer = Tools::getIntValue('id_customer');
        $idStoreCredit = Tools::getIntValue('id_store_credit');

        $this->_join = implode('', [
            ' LEFT JOIN `' . _DB_PREFIX_ . 'employee` `e` ON (`e`.`id_employee` = `a`.`id_employee`)',
            ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` `o` ON (`a`.`entity_type` = ' . (int)StoreCreditTransaction::ENTITY_ORDER . ' AND `o`.`id_order` = `a`.`id_entity`)',
        ]);
        $this->_select = implode(', ', [
            'CONCAT(`e`.`firstname`, " ", `e`.`lastname`) AS `employee_name`',
            '`o`.`reference` AS `order_reference`',
        ]);

        if ($idCustomer) {
            $this->_where .= ' AND a.id_customer = ' . (int)$idCustomer;
        }
        if ($idStoreCredit) {
            $this->_where .= ' AND a.id_store_credit = ' . (int)$idStoreCredit;
        }

        $this->fields_list = [
            'id_store_credit_transaction' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'date_add' => [
                'title' => $this->l('Date'),
                'type' => 'datetime',
                'class' => 'fixed-width-lg',
            ],
            'transaction_type' => [
                'title' => $this->l('Transaction Type'),
                'callback_object' => $this,
                'callback' => 'displayTransactionType',
                'align' => 'center',
            ],
            'economic_type' => [
                'title' => $this->l('Economic Type'),
                'callback_object' => $this,
                'callback' => 'displayEconomicType',
                'align' => 'center',
            ],
            'entity_type' => [
                'title' => $this->l('Entity Type'),
                'callback_object' => $this,
                'callback' => 'displayEntityType',
                'align' => 'center',
            ],
            'id_entity' => [
                'title' => $this->l('Entity'),
                'callback_object' => $this,
                'callback' => 'displayEntity',
                'align' => 'center',
                'class' => 'fixed-width-md',
            ],
            'amount_tax_incl' => [
                'title' => $this->l('Amount (Tax Incl.)'),
                'type' => 'price',
                'currency' => true,
                'align' => 'text-right',
            ],
            'employee_name' => [
                'title' => $this->l('Employee'),
                'havingFilter' => true,
            ],
            'note' => [
                'title' => $this->l('Note'),
            ],
        ];

        parent::__construct();
    }

    /**
     * @throws PrestaShopException
     */
    public function init()
    {
        parent::init();

        if ($this->isAddStoreCreditTransactionMode()) {
            $this->addStoreCreditTransactionMode = true;
            $this->display = 'add';
        }
    }

    /**
     * @throws PrestaShopException
     */
    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
    }

    /**
     * @throws PrestaShopException
     */
    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();

        $title = $this->buildPageTitle();
        $this->page_header_toolbar_title = $title;
        $this->toolbar_title = $title;

        $idCustomer = $this->resolveCustomerId();
        $idStoreCredit = Tools::getIntValue('id_store_credit');

        if ($this->addStoreCreditTransactionMode) {
            $this->page_header_toolbar_btn['back_to_list'] = [
                'href' => $this->getTransactionsListUrl($idCustomer, $idStoreCredit),
                'desc' => $this->l('Back to list'),
                'icon' => 'process-icon-back',
            ];
            unset($this->page_header_toolbar_btn['new']);

            return;
        }

        if (empty($this->display) && $idCustomer > 0) {
            $this->page_header_toolbar_btn['add_store_credit_transaction'] = [
                'href' => $this->getAddStoreCreditTransactionUrl($idCustomer, $idStoreCredit),
                'desc' => $this->l('Add store credit'),
                'icon' => 'process-icon-new',
            ];
        }
    }

    /**
     * @param Helper $helper
     *
     * @throws PrestaShopException
     */
    public function setHelperDisplay(Helper $helper)
    {
        parent::setHelperDisplay($helper);
        if ($helper instanceof HelperList) {
            $idCustomer = $this->resolveCustomerId();
            $helper->title = $this->buildPageTitle();

            if ($idCustomer > 0) {
                $helper->toolbar_btn['back_to_credits'] = [
                    'href' => $this->getCustomerCreditsUrl($idCustomer),
                    'desc' => $this->l('Back to credits'),
                    'icon' => 'process-icon-back',
                ];
            }
        }
    }

    /**
     * @return false|mixed
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitAddStoreCreditTransaction')) {
            $this->processAddStoreCreditTransaction();
            if (empty($this->errors)) {
                $idCustomer = Tools::getIntValue('id_customer');
                $idStoreCredit = Tools::getIntValue('id_store_credit');
                if ($idStoreCredit <= 0 && $idCustomer > 0) {
                    $idStoreCredit = StoreCredit::getStoreCreditIdByCustomer($idCustomer);
                }
                Tools::redirectAdmin($this->getTransactionsListUrl($idCustomer, $idStoreCredit));
            }

            return false;
        }

        return parent::postProcess();
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function renderForm()
    {
        if (!$this->addStoreCreditTransactionMode) {
            return parent::renderForm();
        }

        $idCustomer = Tools::getIntValue('id_customer');
        $idStoreCredit = Tools::getIntValue('id_store_credit');
        $customer = new Customer($idCustomer);
        if (!Validate::isLoadedObject($customer)) {
            $this->errors[] = $this->l('Please choose a valid customer.');

            return '';
        }
        $customerLabel = trim($customer->firstname . ' ' . $customer->lastname . ' (' . $customer->email . ')');
        $currency = Currency::getCurrencyInstance((int)Configuration::get('PS_CURRENCY_DEFAULT'));
        $currencySymbol = $currency ? $currency->getSign('left') . $currency->getSign('right') : '';

        $helper = new HelperForm();
        $helper->table = $this->table;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitAddStoreCreditTransaction';
        $helper->token = $this->token;
        $helper->show_toolbar = false;
        $helper->languages = $this->getLanguages();
        $helper->default_form_language = $this->getDefaultFormLanguage();
        $helper->allow_employee_form_lang = $this->getAllowEmployeeFormLanguage();

        $params = [
            'addstorecredittransaction' => 1,
            'id_customer' => $idCustomer,
        ];
        if ($idStoreCredit > 0) {
            $params['id_store_credit'] = $idStoreCredit;
        }
        $helper->currentIndex = $this->context->link->getAdminLink('AdminStoreCreditTransactions', true, $params);

        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Add store credit'),
                'icon'  => 'icon-money',
            ],
            'input'  => [
                [
                    'type' => 'hidden',
                    'name' => 'id_customer',
                ],
                [
                    'type'  => 'free',
                    'label' => $this->l('Customer'),
                    'name'  => 'customer_label',
                ],
                [
                    'type'     => 'price',
                    'prefix'   => $currencySymbol,
                    'label'    => $this->l('Amount'),
                    'name'     => 'amount_tax_incl',
                    'required' => true,
                ],
                [
                    'type'    => 'select',
                    'label'   => $this->l('Economic type'),
                    'name'    => 'economic_type',
                    'options' => [
                        'query' => [
                            [
                                'id' => StoreCreditTransaction::ECONOMIC_MANUAL_ADJUSTMENT,
                                'name' => $this->l('Manual adjustment'),
                            ],
                            [
                                'id' => StoreCreditTransaction::ECONOMIC_REFUND_CREDIT,
                                'name' => $this->l('Refund credit'),
                            ],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type'  => 'textarea',
                    'label' => $this->l('Note'),
                    'name'  => 'note',
                    'rows'  => 3,
                    'cols'  => 80,
                ],
            ],
            'submit' => [
                'name'  => 'submitAddStoreCreditTransaction',
                'title' => $this->l('Save'),
            ],
        ];

        $helper->fields_value = [
            'id_customer' => $idCustomer,
            'customer_label' => Tools::safeOutput($customerLabel),
            'amount_tax_incl' => Tools::safeOutput((string)Tools::getValue('amount_tax_incl', '')),
            'economic_type' => (int)Tools::getValue('economic_type', StoreCreditTransaction::ECONOMIC_MANUAL_ADJUSTMENT),
            'note' => Tools::safeOutput((string)Tools::getValue('note', '')),
        ];

        return $helper->generateForm([
            ['form' => $this->fields_form],
        ]);
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    protected function processAddStoreCreditTransaction(): bool
    {
        if (!$this->hasAddPermission()) {
            $this->errors[] = $this->l('You do not have permission to add this.');

            return false;
        }

        $idCustomer = Tools::getIntValue('id_customer');
        $amountRaw = str_replace([" ", ",", "'"], ['', '.', ''], (string)Tools::getValue('amount_tax_incl'));
        $amountTaxIncl = Tools::roundPrice((float)$amountRaw);
        $economicType = Tools::getIntValue('economic_type');
        $note = trim((string)Tools::getValue('note'));
        $idEmployee = (int)$this->context->employee->id;

        if ($idCustomer <= 0 || !Validate::isLoadedObject(new Customer($idCustomer))) {
            $this->errors[] = $this->l('Please choose a valid customer.');
        }
        if ($amountTaxIncl === 0.0) {
            $this->errors[] = $this->l('Amount must not be zero.');
        }
        if ($amountTaxIncl < 0.0 && $economicType !== StoreCreditTransaction::ECONOMIC_MANUAL_ADJUSTMENT) {
            $this->errors[] = $this->l('Negative amount is only allowed for manual adjustment.');
        }
        if (!StoreCreditTransaction::isValidEconomicType($economicType) || $economicType === StoreCreditTransaction::ECONOMIC_PAYMENT_INSTRUMENT) {
            $this->errors[] = $this->l('Invalid economic type.');
        }
        if ($note !== '' && !Validate::isCleanHtml($note)) {
            $this->errors[] = $this->l('Note is invalid.');
        }

        if (!empty($this->errors)) {
            return false;
        }

        $idShops = Shop::getContextListShopID();
        if (empty($idShops)) {
            $idShops = [(int)$this->context->shop->id];
        }

        if (!StoreCredit::addManualCredit(
            $idCustomer,
            $amountTaxIncl,
            $economicType,
            $idEmployee,
            $note,
            $idShops
        )) {
            if (StoreCredit::getStoreCreditIdByCustomer($idCustomer) <= 0) {
                $this->errors[] = $this->l('Unable to initialize store credit for this customer.');
            } else {
                $this->errors[] = $this->l('Unable to save store credit transaction.');
            }

            return false;
        }

        return true;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function displayTransactionType($value): string
    {
        $transactionType = (int)$value;
        if ($transactionType === StoreCreditTransaction::TYPE_INCREASE) {
            return $this->l('Increase');
        }
        if ($transactionType === StoreCreditTransaction::TYPE_DECREASE) {
            return $this->l('Decrease');
        }
        return (string)$transactionType;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function displayEconomicType($value): string
    {
        $economicType = (int)$value;
        if ($economicType === StoreCreditTransaction::ECONOMIC_PAYMENT_INSTRUMENT) {
            return $this->l('Payment instrument');
        }
        if ($economicType === StoreCreditTransaction::ECONOMIC_REFUND_CREDIT) {
            return $this->l('Refund credit');
        }
        if ($economicType === StoreCreditTransaction::ECONOMIC_MANUAL_ADJUSTMENT) {
            return $this->l('Manual adjustment');
        }
        return (string)$economicType;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function displayEntityType($value): string
    {
        $entityType = (int)$value;
        if ($entityType === StoreCreditTransaction::ENTITY_ORDER) {
            return $this->l('Order');
        }
        if ($entityType === StoreCreditTransaction::ENTITY_ORDER_SLIP) {
            return $this->l('Order slip');
        }
        if ($entityType === StoreCreditTransaction::ENTITY_MANUAL) {
            return $this->l('Manual');
        }
        return (string)$entityType;
    }

    /**
     * @param string $value
     * @param array $row
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    public function displayEntity($value, $row): string
    {
        $idEntity = (int)$value;
        $entityType = (int)$row['entity_type'];
        if ($entityType === StoreCreditTransaction::ENTITY_MANUAL) {
            return '-';
        }
        if ($idEntity <= 0) {
            return '';
        }

        if ($entityType === StoreCreditTransaction::ENTITY_ORDER) {
            $label = trim((string)($row['order_reference'] ?? ''));
            if ($label === '') {
                $label = (string)$idEntity;
            }
            $link = $this->context->link->getAdminLink('AdminOrders', true, [
                'id_order' => $idEntity,
                'vieworder' => 1,
            ]);
            return '<a href="' . $link . '">' . Tools::safeOutput($label) . '</a>';
        }

        return (string)$idEntity;
    }

    /**
     * @param int $idCustomer
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function getCustomerCreditsUrl(int $idCustomer): string
    {
        return $this->context->link->getAdminLink('AdminStoreCredit', true);
    }

    /**
     * @param int $idCustomer
     * @param int $idStoreCredit
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function getTransactionsListUrl(int $idCustomer, int $idStoreCredit = 0): string
    {
        $params = [];
        if ($idCustomer > 0) {
            $params['id_customer'] = $idCustomer;
        }
        if ($idStoreCredit > 0) {
            $params['id_store_credit'] = $idStoreCredit;
        }

        return $this->context->link->getAdminLink('AdminStoreCreditTransactions', true, $params);
    }

    /**
     * @param int $idCustomer
     * @param int $idStoreCredit
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function getAddStoreCreditTransactionUrl(int $idCustomer, int $idStoreCredit = 0): string
    {
        $params = [
            'addstorecredittransaction' => 1,
            'id_customer' => $idCustomer,
        ];
        if ($idStoreCredit > 0) {
            $params['id_store_credit'] = $idStoreCredit;
        }

        return $this->context->link->getAdminLink('AdminStoreCreditTransactions', true, $params);
    }

    /**
     * @return bool
     */
    protected function isAddStoreCreditTransactionMode(): bool
    {
        return Tools::isSubmit('addstorecredittransaction') || Tools::isSubmit('submitAddStoreCreditTransaction');
    }

    /**
     * @return int
     *
     * @throws PrestaShopException
     */
    protected function resolveCustomerId(): int
    {
        $idCustomer = Tools::getIntValue('id_customer');
        if ($idCustomer > 0) {
            return $idCustomer;
        }

        $idStoreCredit = Tools::getIntValue('id_store_credit');
        if ($idStoreCredit <= 0) {
            return 0;
        }

        $storeCredit = new StoreCredit($idStoreCredit);
        if (!Validate::isLoadedObject($storeCredit)) {
            return 0;
        }

        return (int)$storeCredit->id_customer;
    }

    /**
     * @param int $idCustomer
     *
     * @return string
     */
    protected function resolveCustomerName(int $idCustomer): string
    {
        if ($idCustomer <= 0) {
            return '';
        }

        $customer = new Customer($idCustomer);
        if (!Validate::isLoadedObject($customer)) {
            return '';
        }

        return trim($customer->firstname . ' ' . $customer->lastname);
    }

    /**
     * @param int $idCustomer
     *
     * @return float
     *
     * @throws PrestaShopException
     */
    protected function resolveCustomerBalance(int $idCustomer): float
    {
        if ($idCustomer <= 0) {
            return 0.0;
        }

        $idStoreCredit = StoreCredit::getStoreCreditIdByCustomer($idCustomer);
        if ($idStoreCredit <= 0) {
            return 0.0;
        }

        $storeCredit = new StoreCredit($idStoreCredit);
        if (!Validate::isLoadedObject($storeCredit)) {
            return 0.0;
        }

        return (float)$storeCredit->amount;
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function buildPageTitle(): string
    {
        $title = $this->l('Store credit transactions');
        $idCustomer = $this->resolveCustomerId();
        if ($idCustomer <= 0) {
            return $title;
        }

        $customerName = $this->resolveCustomerName($idCustomer);
        if ($customerName === '') {
            return $title;
        }

        $currency = Currency::getCurrencyInstance((int)Configuration::get('PS_CURRENCY_DEFAULT'));
        $balance = $this->resolveCustomerBalance($idCustomer);
        $balanceFormatted = Tools::displayPrice($balance, $currency);

        return sprintf($this->l('Store credit transactions: %1$s (Balance: %2$s)'), $customerName, $balanceFormatted);
    }
}
