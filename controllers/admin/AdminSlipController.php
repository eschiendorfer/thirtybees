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
 * Class AdminSlipControllerCore
 *
 * @property OrderSlip|null $object
 */
class AdminSlipControllerCore extends AdminController
{
    /**
     * AdminSlipControllerCore constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'order_slip';
        $this->className = 'OrderSlip';

        $this->_select = 'a.`id_order_slip` AS id_pdf, o.`id_shop`, o.`reference`, c.`id_customer`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer`, COALESCE((SELECT NULLIF(op.`payment_method`, \'\') FROM `'._DB_PREFIX_.'order_payment` op WHERE op.`id_order_slip` = a.`id_order_slip` ORDER BY op.`id_order_payment` DESC LIMIT 1), o.`payment`) AS refund_payment_method, ROUND(ROUND((a.`total_products_tax_incl` + a.`total_shipping_tax_incl` - a.`adjustment_cart_rule_tax_incl` - a.`adjustment_fee_tax_incl`) * 20) / 20, 2) AS total_tax_incl';
        $this->_join .= ' LEFT JOIN '._DB_PREFIX_.'orders o ON (o.`id_order` = a.`id_order`)';
        $this->_join .= ' LEFT JOIN '._DB_PREFIX_.'customer c ON (o.`id_customer` = c.`id_customer`)';
        $this->_group = ' GROUP BY a.`id_order_slip`';

        $this->fields_list = [
            'id_order_slip' => [
                'title' => $this->l('ID'),
                'align' => 'center',
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
            'total_tax_incl' => [
                'title' => $this->l('Total (tax incl.)'),
                'align'         => 'text-right',
                'type'      => 'price',
                'havingFilter' => true,
                'class'      => 'fixed-width-xs',
                'callback' => 'formatTotalBadge',
            ],
            'refund_payment_method' => [
                'title' => $this->l('Refund method'),
                'havingFilter' => true,
                'callback' => 'formatRefundPaymentMethod',
            ],
            'date_add'      => [
                'title'      => $this->l('Date issued'),
                'type'       => 'date',
                'align'      => 'right',
                'filter_key' => 'a!date_add',
            ],
            'id_pdf'        => [
                'title'          => $this->l('PDF'),
                'align'          => 'center',
                'callback'       => 'printPDFIcons',
                'orderby'        => false,
                'search'         => false,
                'remove_onclick' => true,
            ],
        ];

        $this->_orderBy = 'id_order_slip';
        $this->_orderWay = 'DESC';

        $this->fields_options = [
            'general' => [
                'title'  => $this->l('Credit slip options'),
                'fields' => [
                    'PS_CREDIT_SLIP_PREFIX' => [
                        'title' => $this->l('Credit slip prefix'),
                        'desc'  => $this->l('Prefix used for credit slips.'),
                        'size'  => 6,
                        'type'  => 'textLang',
                    ],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        parent::__construct();

        $this->_where = Shop::addSqlRestriction(false, 'o');
    }

    /**
     * @param string $paymentMethod
     *
     * @return string
     */
    public function formatRefundPaymentMethod($paymentMethod): string
    {
        if ((string)$paymentMethod === 'Store Credit') {
            return $this->l('Store Credit');
        }

        return (string)$paymentMethod;
    }

    /**
     * Initialize content
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function initContent()
    {
        $this->initToolbar();
        $this->initPageHeaderToolbar();
        $this->content .= $this->renderList();
        $this->content .= $this->renderOptions();

        $this->context->smarty->assign(
            [
                'content'                   => $this->content,
                'url_post'                  => static::$currentIndex.'&token='.$this->token,
                'show_page_header_toolbar'  => $this->show_page_header_toolbar,
                'page_header_toolbar_title' => $this->page_header_toolbar_title,
                'page_header_toolbar_btn'   => $this->page_header_toolbar_btn,
            ]
        );
    }

    /**
     * Print PDF icons
     *
     * @param int $idOrderSlip
     * @param array $tr
     *
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function printPDFIcons($idOrderSlip, $tr)
    {
        $orderSlip = new OrderSlip((int) $idOrderSlip);
        if (!Validate::isLoadedObject($orderSlip)) {
            return '';
        }

        $this->context->smarty->assign([
            'order_slip' => $orderSlip,
            'tr'         => $tr,
        ]);

        return $this->createTemplate('_print_pdf_icon.tpl')->fetch();
    }

    public function formatTotalBadge($total, $row)
    {
        return '<span class="badge">'.Tools::displayPrice((float)$total).'</span>';
    }

}
