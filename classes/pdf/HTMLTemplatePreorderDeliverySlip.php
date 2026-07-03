<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2018 thirty bees
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
 *  @author    thirty bees <contact@thirtybees.com>
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2017-2018 thirty bees
 *  @copyright 2007-2016 PrestaShop SA
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

class HTMLTemplatePreorderDeliverySlip extends HTMLTemplate
{

    private $order; // This is the Order that triggers the preorder shipping
    private $products;

    /**
     * @param array $products
     * @param Smarty $smarty
     *
     * @throws PrestaShopException
     */
    public function __construct($order, $products, Smarty $smarty)
    {
        if (is_int($order)) {
            $order = new Order($order);
        }

        $this->order = $order;
        $this->products = $products;
        $this->smarty = $smarty;

        // header informations
        $this->date = Tools::displayDate(date('Y-m-d H:i:s'));

        $this->title = 'Preorder delivery slip: '.$order->reference;

        // footer informations
        $this->shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
    }

    /**
     * Returns the template's HTML header
     *
     * @return string HTML header
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getHeader()
    {
        $this->assignCommonHeaderData();
        $this->smarty->assign(['header' => static::l('Delivery')]);

        return $this->smarty->fetch($this->getTemplate('header'));
    }

    /**
     * Returns the template's HTML content
     *
     * @return string HTML content
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent() {

        $deliveryAddress = new Address($this->order->id_address_delivery);
        $formattedDeliveryAddress = AddressFormat::generateAddress($deliveryAddress, [], '<br />', ' ');

        $this->smarty->assign(
            [
                'barcode' => '',
                'preorder_title' => 'Produkte aus Vorausbestellungen',
                'delivery_address' => $formattedDeliveryAddress,
                'invoice_address' => '',
                'order_details'          => $this->products,
                'display_product_images' => Configuration::get('PS_PDF_IMG_DELIVERY'),
            ]
        );

        return $this->smarty->fetch($this->getTemplate('delivery-slip'));
    }

    /**
     * Returns the template filename when using bulk rendering
     *
     * @return string filename
     */
    public function getBulkFilename()
    {
        return 'deliveries.pdf';
    }

    /**
     * Returns the template filename
     *
     * @return string filename
     *
     * @throws PrestaShopException
     */
    public function getFilename()
    {
        return 'preorder-delivery-slip-'.$this->order->reference.'.pdf';
    }
}
