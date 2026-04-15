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
            ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` `c` ON (`c`.`id_customer` = `a`.`id_customer`)',
            ' LEFT JOIN `' . _DB_PREFIX_ . 'employee` `e` ON (`e`.`id_employee` = `a`.`id_employee`)',
            ' LEFT JOIN `' . _DB_PREFIX_ . 'orders` `o` ON (`a`.`entity_type` = ' . (int)StoreCreditTransaction::ENTITY_ORDER . ' AND `o`.`id_order` = `a`.`id_entity`)',
        ]);
        $this->_select = implode(', ', [
            'CONCAT(`c`.`firstname`, " ", `c`.`lastname`) AS `customer_name`',
            '`c`.`email` AS `customer_email`',
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
            'id_store_credit' => [
                'title' => $this->l('Store Credit ID'),
                'align' => 'center',
                'class' => 'fixed-width-sm',
            ],
            'customer_name' => [
                'title' => $this->l('Customer'),
                'callback_object' => $this,
                'callback' => 'displayCustomerInfo',
                'havingFilter' => true,
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
    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
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
            $idCustomer = Tools::getIntValue('id_customer');
            $helper->title = $this->l('Store credit transactions');
            if ($idCustomer) {
                $customer = new Customer($idCustomer);
                if (Validate::isLoadedObject($customer)) {
                    $customerName = trim($customer->firstname . ' ' . $customer->lastname);
                    $helper->title = sprintf($this->l('Store credit transactions: %s'), $customerName);
                }

                $helper->toolbar_btn['back_to_credits'] = [
                    'href' => $this->getCustomerCreditsUrl($idCustomer),
                    'desc' => $this->l('Back to credits'),
                    'icon' => 'process-icon-back',
                ];
            }
        }
    }

    /**
     * @param string $value
     * @param array $row
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    public function displayCustomerInfo($value, $row): string
    {
        $idCustomer = (int)$row['id_customer'];
        if ($idCustomer) {
            $customerLabel = trim((string)$value);
            $customerEmail = trim((string)($row['customer_email'] ?? ''));
            if ($customerEmail !== '') {
                $customerLabel .= ' (' . $customerEmail . ')';
            }
            $link = $this->context->link->getAdminLink('AdminCustomers', true, [
                'id_customer' => $idCustomer,
                'viewcustomer' => 1,
            ]);
            return '<a href="' . $link . '">' . Tools::safeOutput($customerLabel) . '</a>';
        }
        return $this->l('Unknown customer');
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

        if ($entityType === StoreCreditTransaction::ENTITY_MANUAL) {
            return '-';
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
        return $this->context->link->getAdminLink('AdminStoreCredit', true, [
            'id_customer' => $idCustomer,
        ]);
    }
}
