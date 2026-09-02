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
 * Class OrderDetailControllerCore
 */
class OrderDetailControllerCore extends FrontController
{
    /** @var string $php_self */
    public $php_self = 'order-detail';
    /** @var bool $auth */
    public $auth = true;
    /** @var string $authRedirection */
    public $authRedirection = 'history';
    /** @var bool $ssl */
    public $ssl = true;

    /**
     * Initialize order detail controller
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function init()
    {
        parent::init();
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    }

    /**
     * Handle ajax call
     *
     * @return void
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function displayAjax()
    {
        $this->display();
    }

    /**
     * Assign template vars related to page content
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function initContent()
    {
        parent::initContent();

        if (!($idOrder = Tools::getIntValue('id_order')) || !Validate::isUnsignedId($idOrder)) {
            $this->errors[] = Tools::displayError('Order ID required');
        } else {
            $order = new Order($idOrder);
            if (Validate::isLoadedObject($order) && $order->id_customer == $this->context->customer->id) {
                $idOrderState = (int) $order->getCurrentState();
                $carrier = new Carrier((int) $order->id_carrier, (int) $order->id_lang);
                $addressInvoice = new Address((int) $order->id_address_invoice);
                $addressDelivery = new Address((int) $order->id_address_delivery);

                $invAdrFields = AddressFormat::getOrderedAddressFields($addressInvoice->id_country);
                $dlvAdrFields = AddressFormat::getOrderedAddressFields($addressDelivery->id_country);

                $invoiceAddressFormatedValues = AddressFormat::getFormattedAddressFieldsValues($addressInvoice, $invAdrFields);
                $deliveryAddressFormatedValues = AddressFormat::getFormattedAddressFieldsValues($addressDelivery, $dlvAdrFields);

                if ($order->total_discounts > 0) {
                    $this->context->smarty->assign('total_old', (float) $order->total_paid - $order->total_discounts);
                }
                $products = $order->getProducts();

                /* DEPRECATED: customizedDatas @since 1.5 */
                $customizedDatas = Product::getAllCustomizedDatas((int) $order->id_cart);
                Product::addCustomizationPrice($products, $customizedDatas);

                OrderReturn::addReturnedQuantity($products, $order->id);
                if (class_exists('\\CrmModule\\OrderDetailExtension')) {
                    foreach ($products as &$product) {
                        $orderDetailExtension = new \CrmModule\OrderDetailExtension((int)$product['id_order_detail']);
                        $product['shipping_quantity'] = max(0, (int)$orderDetailExtension->shipping_quantity);
                    }
                    unset($product);
                }
                $orderStatus = new OrderState((int) $idOrderState, (int) $order->id_lang);

                $customer = new Customer($order->id_customer);
                $storeCreditUsedTaxIncl = 0.0;
                try {
                    $storeCreditUsedTaxIncl = Tools::roundPrice((float)StoreCreditTransaction::getOrderConsumptionAmount((int)$order->id));
                } catch (Exception $exception) {
                    $storeCreditUsedTaxIncl = 0.0;
                }
                $outstandingAmountTaxIncl = Tools::roundPrice(max(0.0, (float)$order->total_paid_tax_incl - (float)$storeCreditUsedTaxIncl));
                $orderPaymentMethodsText = trim((string)$order->getDisplayPaymentMethodsText(' + ', false, true));
                if ($orderPaymentMethodsText === '' && (string)$order->payment !== '') {
                    $orderPaymentMethodsText = (string)$order->payment;
                }
                $customerServiceItems = class_exists('\\CrmModule\\CustomerServiceOverviewProvider')
                    ? (new \CrmModule\CustomerServiceOverviewProvider())->getOrderItems((int)$order->id)
                    : [];
                $this->context->smarty->assign(
                    [
                        'shop_name'                     => strval(Configuration::get('PS_SHOP_NAME')),
                        'order'                         => $order,
                        'return_allowed'                => (int) $order->isReturnable(),
                        'currency'                      => new Currency($order->id_currency),
                        'order_state'                   => (int) $idOrderState,
                        'invoiceAllowed'                => (int) Configuration::get('PS_INVOICE'),
                        // An existing invoice remains a valid purchase document after later status changes or cancellations.
                        'invoice'                       => count($order->getInvoicesCollection()) > 0,
                        'logable'                       => (bool) $orderStatus->logable,
                        'order_history'                 => $order->getHistory($this->context->language->id, false, true),
                        'products'                      => $products,
                        'discounts'                     => $order->getCartRules(),
                        'carrier'                       => $carrier,
                        'address_invoice'               => $addressInvoice,
                        'invoiceState'                  => (Validate::isLoadedObject($addressInvoice) && $addressInvoice->id_state) ? new State($addressInvoice->id_state) : false,
                        'address_delivery'              => $addressDelivery,
                        'inv_adr_fields'                => $invAdrFields,
                        'dlv_adr_fields'                => $dlvAdrFields,
                        'invoiceAddressFormatedValues'  => $invoiceAddressFormatedValues,
                        'deliveryAddressFormatedValues' => $deliveryAddressFormatedValues,
                        'deliveryState'                 => (Validate::isLoadedObject($addressDelivery) && $addressDelivery->id_state) ? new State($addressDelivery->id_state) : false,
                        'is_guest'                      => false,
                        'CUSTOMIZE_FILE'                => Product::CUSTOMIZE_FILE,
                        'CUSTOMIZE_TEXTFIELD'           => Product::CUSTOMIZE_TEXTFIELD,
                        'isRecyclable'                  => Configuration::get('PS_RECYCLABLE_PACK'),
                        'use_tax'                       => Configuration::get('PS_TAX'),
                        'group_use_tax'                 => (Group::getPriceDisplayMethod($customer->id_default_group) == PS_TAX_INC),
                        /* DEPRECATED: customizedDatas @since 1.5 */
                        'customizedDatas'               => $customizedDatas,
                        /* DEPRECATED: customizedDatas @since 1.5 */
                        'reorderingAllowed'             => !Configuration::get('PS_DISALLOW_HISTORY_REORDERING'),
                        'store_credit_used_tax_incl'    => $storeCreditUsedTaxIncl,
                        'outstanding_amount_tax_incl'   => $outstandingAmountTaxIncl,
                        'order_payment_methods_text'    => $orderPaymentMethodsText,
                        'customer_service_items'        => $customerServiceItems,
                    ]
                );

                if ($carrier->url && $order->shipping_number) {
                    $this->context->smarty->assign('followup', str_replace('@', $order->shipping_number, $carrier->url));
                }
                $this->context->smarty->assign('HOOK_ORDERDETAILDISPLAYED', Hook::displayHook('displayOrderDetail', ['order' => $order]));
                Hook::triggerEvent('actionOrderDetail', ['carrier' => $carrier, 'order' => $order]);

                unset($carrier, $addressInvoice, $addressDelivery);
            } else {
                $this->errors[] = Tools::displayError('This order cannot be found.');
            }
            unset($order);
        }

        $this->setTemplate(_PS_THEME_DIR_.'order-detail.tpl');
    }

    /**
     * Set media
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function setMedia()
    {
        if (Tools::getValue('ajax') != 'true') {
            parent::setMedia();
            $this->addCSS(_THEME_CSS_DIR_.'history.css');
            $this->addCSS(_THEME_CSS_DIR_.'addresses.css');
        }
    }

}
