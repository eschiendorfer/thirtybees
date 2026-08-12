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
 * Class AdminStoreCreditController
 *
 * @property StoreCredit|null $object
 */
class AdminStoreCreditControllerCore extends AdminController
{
    /**
     * @var bool
     */
    protected $addStoreCreditTransactionMode = false;

    /**
     * AdminStoreCreditController constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'store_credit';
        $this->className = 'StoreCredit';
        $this->lang = false;
        $this->_orderWay = 'DESC';

        $this->bulk_actions = [];
        $this->list_id = 'storecredits';
        $this->list_no_link = true;
        $this->_join = implode('', [
            ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` `c` ON (`c`.`id_customer` = `a`.`id_customer`)',
        ]);
        $this->_select = implode(',', [
            'CONCAT(`c`.`firstname`, " ", `c`.`lastname`) AS `customer_name`',
            '`c`.`email` AS `email`',
        ]);
        $this->_defaultOrderBy = 'id_store_credit';
        $this->_defaultOrderWay = 'DESC';
        $this->addRowAction('transactions');
        $this->addRowAction('edit');
        $this->fields_list = [
            'id_store_credit' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'id_customer' => [
                'title' => $this->l('Customer ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'customer_name' => [
                'title' => $this->l('Customer Name'),
                'callback_object' => $this,
                'callback' => 'displayCustomerInfo',
                'havingFilter' => true,
            ],
            'email' => [
                'title' => $this->l('Customer Email'),
                'havingFilter' => true,
            ],
            'amount' => [
                'title' => $this->l('Balance'),
                'align' => 'text-right',
                'type' => 'price',
                'currency' => true,
                'havingFilter' => true,
            ],
            'date_to' => [
                'title' => $this->l('Expiration date'),
                'type' => 'datetime',
                'class' => 'fixed-width-lg',
            ],
        ];

        $currency = Currency::getCurrencyInstance(Configuration::get('PS_CURRENCY_DEFAULT'));
        $currencySymbol = $currency->getSign('left') . $currency->getSign('right');
        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Store credit'),
                'icon'  => 'icon-money',
            ],
            'input'  => [
                [
                    'type' => 'hidden',
                    'name' => 'id_customer',
                ],
                [
                    'type'  => 'price',
                    'prefix' => $currencySymbol,
                    'label' => $this->l('Balance'),
                    'name'  => 'amount',
                    'readonly' => true,
                    'disabled' => true,
                    'hint'  => $this->l('Balance is adjusted via transactions.'),
                ],
                [
                    'type'  => 'datetime',
                    'label' => $this->l('Valid from'),
                    'name'  => 'date_from',
                    'hint'  => $this->l('Date this credit is valid from'),
                ],
                [
                    'type'  => 'datetime',
                    'label' => $this->l('Valid to'),
                    'name'  => 'date_to',
                    'hint'  => $this->l('Credit expiration date'),
                ],
                [
                    'type'  => 'shop',
                    'label' => $this->l('Shop association'),
                    'name'  => 'checkBoxShopAsso',
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
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

        if ($this->addStoreCreditTransactionMode) {
            $this->page_header_toolbar_btn['back_to_list'] = [
                'href' => $this->getStoreCreditListUrl(),
                'desc' => $this->l('Back to list'),
                'icon' => 'process-icon-back',
            ];
            unset($this->page_header_toolbar_btn['new']);

            return;
        }

        if (empty($this->display)) {
            $this->page_header_toolbar_btn['add_store_credit_transaction'] = [
                'href' => $this->getAddStoreCreditTransactionUrl(),
                'desc' => $this->l('Add manual correction'),
                'icon' => 'process-icon-new',
            ];
        }
    }

    /**
     * @param Helper $helper
     * @return void
     * @throws PrestaShopException
     */
    public function setHelperDisplay(Helper $helper)
    {
        parent::setHelperDisplay($helper);
        if ($helper instanceof HelperList) {
            $helper->title = $this->l('Store credits');
        }
    }

    /**
     * @param string $value
     * @param array $row
     * @return string
     *
     * @throws PrestaShopException
     */
    public function displayCustomerInfo($value, $row): string
    {
        $customerId = (int)$row['id_customer'];
        if ($customerId) {
            $link = $this->context->link->getAdminLink('AdminCustomers', true, [
                'id_customer' => $customerId,
                'viewcustomer' => 1,
            ]);
            return '<a href="'.$link.'">' . Tools::safeOutput($value) . "</a>";
        } else {
            return $this->l('Unknown customer');
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
                Tools::redirectAdmin($this->getCustomerTransactionsUrl($idCustomer));
            }

            return false;
        }

        if (
            !$this->addStoreCreditTransactionMode
            && Tools::isSubmit('submitAdd' . $this->table)
            && Tools::getIntValue($this->identifier) > 0
        ) {
            $idStoreCredit = Tools::getIntValue($this->identifier);
            $storeCredit = new StoreCredit($idStoreCredit);
            if (Validate::isLoadedObject($storeCredit)) {
                $_POST['amount'] = (string)$storeCredit->amount;
            }
        }

        $result = parent::postProcess();
        if ($this->redirect_after && Tools::isSubmit('id_customer')) {
            $this->setRedirectAfter($this->redirect_after . '&id_customer=' . (int)Tools::getValue('id_customer'));
        }
        return $result;
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
        $customerLabel = '';
        if ($idCustomer > 0) {
            $customer = new Customer($idCustomer);
            if (Validate::isLoadedObject($customer)) {
                $customerLabel = trim($customer->firstname . ' ' . $customer->lastname . ' (' . $customer->email . ')');
            }
        }
        $currency = Currency::getCurrencyInstance((int)Configuration::get('PS_CURRENCY_DEFAULT'));
        $currencySymbol = $currency ? $currency->getSign('left') . $currency->getSign('right') : '';

        $helper = new HelperForm();
        $helper->table = $this->table;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitAddStoreCreditTransaction';
        $helper->currentIndex = static::$currentIndex . '&addstorecredittransaction=1';
        $helper->token = $this->token;
        $helper->show_toolbar = false;
        $helper->languages = $this->getLanguages();
        $helper->default_form_language = $this->getDefaultFormLanguage();
        $helper->allow_employee_form_lang = $this->getAllowEmployeeFormLanguage();

        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Manual store credit correction'),
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
                    'name'  => 'customer_picker',
                    'desc'  => $this->l('Search and choose a customer.'),
                ],
                [
                    'type'     => 'price',
                    'prefix'   => $currencySymbol,
                    'label'    => $this->l('Amount'),
                    'name'     => 'amount_tax_incl',
                    'required' => true,
                    'desc'     => $this->l('Positive amounts increase the store credit. Negative amounts reduce it (for example: -25.00). A reduction is only possible up to the currently available amount.'),
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
            'customer_picker' => $this->renderCustomerPicker($idCustomer, $customerLabel),
            'amount_tax_incl' => Tools::safeOutput((string)Tools::getValue('amount_tax_incl', '')),
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
        $transactionType = StoreCreditTransaction::TYPE_MANUAL_ADJUSTMENT;
        $note = trim((string)Tools::getValue('note'));
        $idEmployee = (int)$this->context->employee->id;

        if ($idCustomer <= 0 || !Validate::isLoadedObject(new Customer($idCustomer))) {
            $this->errors[] = $this->l('Please select a valid customer.');
        }
        if ($amountTaxIncl === 0.0) {
            $this->errors[] = $this->l('Amount must not be zero.');
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
            $transactionType,
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
     * @param int $idCustomer
     * @param string $customerLabel
     *
     * @return string
     * @throws PrestaShopException
     */
    protected function renderCustomerPicker(int $idCustomer, string $customerLabel): string
    {
        $this->context->smarty->assign([
            'storeCreditCustomerPickerAdminCustomersUrl' => $this->context->link->getAdminLink('AdminCustomers', true),
            'storeCreditCustomerPickerIdCustomer' => (int)$idCustomer,
            'storeCreditCustomerPickerInitialCustomerLabel' => $customerLabel,
        ]);

        return $this->createTemplate('controllers/store_credit/customer_picker.tpl')->fetch();
    }

    /**
     * @return bool
     */
    protected function isAddStoreCreditTransactionMode(): bool
    {
        return Tools::isSubmit('addstorecredittransaction') || Tools::isSubmit('submitAddStoreCreditTransaction');
    }


    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function getAddStoreCreditTransactionUrl(): string
    {
        return $this->context->link->getAdminLink('AdminStoreCredit', true, [
            'addstorecredittransaction' => 1,
        ]);
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function getStoreCreditListUrl(): string
    {
        return $this->context->link->getAdminLink('AdminStoreCredit', true);
    }

    /**
     * @param int $idCustomer
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function getCustomerTransactionsUrl(int $idCustomer): string
    {
        return $this->context->link->getAdminLink('AdminStoreCreditTransactions', true, [
            'id_customer' => (int)$idCustomer,
        ]);
    }

    /**
     * @param int $idStoreCredit
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function getStoreCreditTransactionsUrl(int $idStoreCredit, int $idCustomer = 0): string
    {
        $params = [
            'id_store_credit' => (int)$idStoreCredit,
        ];
        if ($idCustomer > 0) {
            $params['id_customer'] = $idCustomer;
        }

        return $this->context->link->getAdminLink('AdminStoreCreditTransactions', true, $params);
    }

    /**
     * @param string|null $token
     * @param int $id
     * @param string|null $name
     *
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function displayTransactionsLink($token, $id, $name = null)
    {
        $idStoreCredit = (int)$id;
        $idCustomer = 0;
        if ($idStoreCredit > 0) {
            $storeCredit = new StoreCredit($idStoreCredit);
            if (Validate::isLoadedObject($storeCredit)) {
                $idCustomer = (int)$storeCredit->id_customer;
            }
        }

        $tpl = $this->createTemplate('helpers/list/list_action_view.tpl');
        $tpl->assign([
            'href' => $this->getStoreCreditTransactionsUrl($idStoreCredit, $idCustomer),
            'action' => $this->l('Transactions'),
        ]);

        return $tpl->fetch();
    }

    /**
     * @return HelperKpi[]
     *
     * @throws PrestaShopException
     */
    public function getKpis(): array
    {
        $currency = Currency::getCurrencyInstance((int)Configuration::get('PS_CURRENCY_DEFAULT'));
        $totalOutstanding = $this->getTotalOutstandingStoreCredit();

        $helper = new HelperKpi();
        $helper->id = 'box-store-credit-outstanding';
        $helper->icon = 'icon-money';
        $helper->color = 'color1';
        $helper->title = $this->l('Outstanding store credit', null, null, false);
        $helper->subtitle = $this->l('All customers', null, null, false);
        $helper->value = Tools::displayPrice($totalOutstanding, $currency);

        return [$helper];
    }

    /**
     * @return float
     */
    protected function getTotalOutstandingStoreCredit(): float
    {
        $query = (new DbQuery())
            ->select('SUM(amount)')
            ->from('store_credit')
            ->where('amount > 0');

        return (float)Db::readOnly()->getValue($query);
    }
}
