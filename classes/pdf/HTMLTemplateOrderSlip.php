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
 * Class HTMLTemplateOrderSlipCore
 */
class HTMLTemplateOrderSlipCore extends HTMLTemplate
{
    /**
     * @var Order $order
     */
    public $order;

    /**
     * @var array[]
     */
    public $products;

    /**
     * @var OrderSlip $order_slip
     */
    public $order_slip;

    /**
     * @var array|null
     */
    protected $adjustedTaxBreakdowns = null;

    /**
     * @param OrderSlip $orderSlip
     * @param Smarty $smarty
     *
     * @throws PrestaShopException
     */
    public function __construct(OrderSlip $orderSlip, Smarty $smarty)
    {
        $this->order_slip = $orderSlip;
        $this->order = new Order((int) $orderSlip->id_order);

        $products = OrderSlip::getOrdersSlipProducts($this->order_slip->id, $this->order);
        $customizedDatas = Product::getAllCustomizedDatas((int) $this->order->id_cart);
        Product::addCustomizationPrice($products, $customizedDatas);

        $this->products = $products;
        $this->smarty = $smarty;

        // header informations
        $this->date = Tools::displayDate($this->order_slip->date_add);
        $prefix = Configuration::get('PS_CREDIT_SLIP_PREFIX', Context::getContext()->language->id);
        $this->title = sprintf(static::l('%1$s%2$06d'), $prefix, (int) $this->order_slip->id);

        $this->shop = new Shop((int) $this->order->id_shop);
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
        $this->smarty->assign(
            [
                'header' => static::l('Credit slip'),
            ]
        );

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
    public function getContent()
    {
        $deliveryAddress = $invoiceAddress = new Address((int) $this->order->id_address_invoice);
        $formattedInvoiceAddress = AddressFormat::generateAddress($invoiceAddress, [], '<br />', ' ');
        $formattedDeliveryAddress = '';

        if ($this->order->id_address_delivery != $this->order->id_address_invoice) {
            $deliveryAddress = new Address((int) $this->order->id_address_delivery);
            $formattedDeliveryAddress = AddressFormat::generateAddress($deliveryAddress, [], '<br />', ' ');
        }

        $customer = new Customer((int) $this->order->id_customer);
        $this->order->total_paid_tax_excl = $this->order->total_paid_tax_incl = $this->order->total_products = $this->order->total_products_wt = 0;

        if ($this->order_slip->amount > 0) {
            foreach ($this->products as &$product) {
                $product['total_price_tax_excl'] = $product['unit_price_tax_excl'] * $product['product_quantity'];
                $product['total_price_tax_incl'] = $product['unit_price_tax_incl'] * $product['product_quantity'];

                if ($this->order_slip->partial == 1) {
                    $orderSlipDetail = Db::readOnly()->getRow(
                        (new DbQuery())
                            ->select('*')
                            ->from('order_slip_detail')
                            ->where('`id_order_slip` = '.(int) $this->order_slip->id)
                            ->where('`id_order_detail` = '.(int) $product['id_order_detail'])
                    );

                    $product['total_price_tax_excl'] = $orderSlipDetail['amount_tax_excl'];
                    $product['total_price_tax_incl'] = $orderSlipDetail['amount_tax_incl'];
                }

                $this->order->total_products += $product['total_price_tax_excl'];
                $this->order->total_products_wt += $product['total_price_tax_incl'];
                $this->order->total_paid_tax_excl = $this->order->total_products;
                $this->order->total_paid_tax_incl = $this->order->total_products_wt;
            }
        } else {
            $this->products = [];
        }

        unset($product); // remove reference

        if ($this->order_slip->shipping_cost == 0) {
            $this->order->total_shipping_tax_incl = $this->order->total_shipping_tax_excl = 0;
        }

        $tax = new Tax();
        $tax->rate = $this->order->carrier_tax_rate;

        $taxExcludedDisplay = Group::getPriceDisplayMethod((int) $customer->id_default_group);

        $this->order->total_shipping_tax_incl = $this->order_slip->total_shipping_tax_incl;
        $this->order->total_shipping_tax_excl = $this->order_slip->total_shipping_tax_excl;
        $this->order_slip->shipping_cost_amount = $taxExcludedDisplay ? $this->order_slip->total_shipping_tax_excl : $this->order_slip->total_shipping_tax_incl;

        $this->order->total_paid_tax_incl += $this->order->total_shipping_tax_incl;
        $this->order->total_paid_tax_excl += $this->order->total_shipping_tax_excl;

        $totalCartRule = 0;
        if ($this->order_slip->order_slip_type == 1 && is_array($cartRules = $this->order->getCartRules())) {
            foreach ($cartRules as $cartRule) {
                if ($taxExcludedDisplay) {
                    $totalCartRule += $cartRule['value_tax_excl'];
                } else {
                    $totalCartRule += $cartRule['value'];
                }
            }
        }
        $refundTotalRounded = $this->getRoundedRefundTotal((bool)$taxExcludedDisplay, (float)$totalCartRule);
        $refundPayment = $this->getRefundPayment();

        $this->smarty->assign(
            [
                'order'                => $this->order,
                'order_slip'           => $this->order_slip,
                'order_details'        => $this->products,
                'cart_rules'           => $this->order_slip->order_slip_type == 1 ? $this->order->getCartRules() : false,
                'amount_choosen'       => $this->order_slip->order_slip_type == 2,
                'delivery_address'     => $formattedDeliveryAddress,
                'invoice_address'      => $formattedInvoiceAddress,
                'addresses'            => ['invoice' => $invoiceAddress, 'delivery' => $deliveryAddress],
                'tax_excluded_display' => $taxExcludedDisplay,
                'total_cart_rule'      => $totalCartRule,
                'cart_rule_adjustment_rate' => $this->getOrderSlipCartRuleAdjustmentRate(),
                'fee_adjustment_rate' => $this->getOrderSlipFeeAdjustmentRate(),
                'refund_total_rounded' => $refundTotalRounded,
                'refund_payment_method' => (string)($refundPayment['payment_method'] ?? ''),
                'refund_payment_date' => (string)($refundPayment['date_add'] ?? ''),
            ]
        );

        $tpls = [
            'style_tab'     => $this->smarty->fetch($this->getTemplate('invoice.style-tab')),
            'addresses_tab' => $this->smarty->fetch($this->getTemplate('invoice.addresses-tab')),
            'summary_tab'   => $this->smarty->fetch($this->getTemplate('order-slip.summary-tab')),
            'product_tab'   => $this->smarty->fetch($this->getTemplate('order-slip.product-tab')),
            'total_tab'     => $this->smarty->fetch($this->getTemplate('order-slip.total-tab')),
            'payment_tab'   => $this->smarty->fetch($this->getTemplate('order-slip.payment-tab')),
            'tax_tab'       => $this->getTaxTabContent(),
        ];
        $this->smarty->assign($tpls);

        return $this->smarty->fetch($this->getTemplate('order-slip'));
    }

    /**
     * Returns the template filename when using bulk rendering
     *
     * @return string filename
     */
    public function getBulkFilename()
    {
        return 'order-slips.pdf';
    }

    /**
     * Returns the template filename
     *
     * @return string filename
     */
    public function getFilename()
    {
        return 'order-slip-'.sprintf('%06d', $this->order_slip->id).'.pdf';
    }

    /**
     * Returns the tax tab content
     *
     * @return String Tax tab html content
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getTaxTabContent()
    {
        $address = new Address((int) $this->order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});

        $taxExempt = false;
        // @TODO: Use a hook for this
        if (Module::isEnabled('vatnumber')) {
            require_once _PS_MODULE_DIR_.'/vatnumber/VATNumberTaxManager.php';

            $taxExempt = VATNumberTaxManager::isAvailableForThisAddress($address);
        }

        $this->smarty->assign(
            [
                'tax_exempt'                      => $taxExempt,
                'product_tax_breakdown'           => $this->getProductTaxesBreakdown(),
                'shipping_tax_breakdown'          => $this->getShippingTaxesBreakdown(),
                'order'                           => $this->order,
                'ecotax_tax_breakdown'            => $this->order_slip->getEcoTaxTaxesBreakdown(),
                'is_order_slip'                   => true,
                'tax_breakdowns'                  => $this->getTaxBreakdown(),
                'display_tax_bases_in_breakdowns' => false,
            ]
        );

        return $this->smarty->fetch($this->getTemplate('invoice.tax-tab'));
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getProductTaxesBreakdown()
    {
        $breakdowns = $this->getAdjustedTaxBreakdowns();

        return $breakdowns['product_tax'] ?? [];
    }

    /**
     * Returns Shipping tax breakdown elements
     *
     * @return array Shipping tax breakdown elements
     *
     * @throws PrestaShopException
     */
    public function getShippingTaxesBreakdown()
    {
        $breakdowns = $this->getAdjustedTaxBreakdowns();

        return $breakdowns['shipping_tax'] ?? [];
    }

    protected function getAdjustedTaxBreakdowns(): array
    {
        if ($this->adjustedTaxBreakdowns !== null) {
            return $this->adjustedTaxBreakdowns;
        }

        $entries = [];
        foreach ($this->products as $product) {
            $taxExcl = max(0.0, (float)$product['total_price_tax_excl']);
            $taxIncl = max(0.0, (float)$product['total_price_tax_incl']);
            if ($taxIncl <= 0.0 && $taxExcl <= 0.0) {
                continue;
            }

            $entries[] = [
                'type' => 'product_tax',
                'rate' => sprintf('%.3f', (float)($product['tax_rate'] ?? 0)),
                'tax_excl' => $taxExcl,
                'tax_incl' => $taxIncl,
            ];
        }

        if ((float)$this->order_slip->total_shipping_tax_incl > 0.0 || (float)$this->order_slip->total_shipping_tax_excl > 0.0) {
            $entries[] = [
                'type' => 'shipping_tax',
                'rate' => sprintf('%.3f', (float)$this->order->carrier_tax_rate),
                'tax_excl' => max(0.0, (float)$this->order_slip->total_shipping_tax_excl),
                'tax_incl' => max(0.0, (float)$this->order_slip->total_shipping_tax_incl),
            ];
        }

        $this->applyOrderSlipAdjustment(
            $entries,
            (float)$this->order_slip->adjustment_cart_rule_tax_excl,
            (float)$this->order_slip->adjustment_cart_rule_tax_incl,
            'product_tax'
        );
        $this->applyOrderSlipAdjustment(
            $entries,
            (float)$this->order_slip->adjustment_fee_tax_excl,
            (float)$this->order_slip->adjustment_fee_tax_incl
        );

        $decimals = Currency::getCurrencyInstance($this->order->id_currency)->getDisplayPrecision();
        $breakdowns = [];
        foreach ($entries as $entry) {
            $taxExcl = Tools::ps_round(max(0.0, (float)$entry['tax_excl']), $decimals, $this->order->round_mode);
            $taxIncl = Tools::ps_round(max(0.0, (float)$entry['tax_incl']), $decimals, $this->order->round_mode);
            $taxAmount = Tools::ps_round(max(0.0, $taxIncl - $taxExcl), $decimals, $this->order->round_mode);
            if ($taxIncl <= 0.0 && $taxExcl <= 0.0) {
                continue;
            }

            $type = $entry['type'];
            $rate = $entry['rate'];
            if (!isset($breakdowns[$type][$rate])) {
                $breakdowns[$type][$rate] = [
                    'total_price_tax_excl' => 0.0,
                    'total_tax_excl' => 0.0,
                    'total_amount' => 0.0,
                    'rate' => $rate,
                ];
            }

            $breakdowns[$type][$rate]['total_price_tax_excl'] += $taxExcl;
            $breakdowns[$type][$rate]['total_tax_excl'] += $taxExcl;
            $breakdowns[$type][$rate]['total_amount'] += $taxAmount;
        }

        foreach ($breakdowns as &$rows) {
            ksort($rows);
        }
        unset($rows);

        $this->adjustedTaxBreakdowns = $breakdowns;

        return $this->adjustedTaxBreakdowns;
    }

    protected function applyOrderSlipAdjustment(array &$entries, float $adjustmentTaxExcl, float $adjustmentTaxIncl, ?string $type = null): void
    {
        $eligibleKeys = [];
        $totalTaxExcl = 0.0;
        $totalTaxIncl = 0.0;

        foreach ($entries as $key => $entry) {
            if ($type !== null && $entry['type'] !== $type) {
                continue;
            }

            $eligibleKeys[] = $key;
            $totalTaxExcl += max(0.0, (float)$entry['tax_excl']);
            $totalTaxIncl += max(0.0, (float)$entry['tax_incl']);
        }

        if (!$eligibleKeys || ($adjustmentTaxExcl <= 0.0 && $adjustmentTaxIncl <= 0.0)) {
            return;
        }

        foreach ($eligibleKeys as $key) {
            $taxExclRatio = $totalTaxExcl > 0.0 ? max(0.0, (float)$entries[$key]['tax_excl']) / $totalTaxExcl : 0.0;
            $taxInclRatio = $totalTaxIncl > 0.0 ? max(0.0, (float)$entries[$key]['tax_incl']) / $totalTaxIncl : 0.0;

            $entries[$key]['tax_excl'] = max(0.0, (float)$entries[$key]['tax_excl'] - ($adjustmentTaxExcl * $taxExclRatio));
            $entries[$key]['tax_incl'] = max(0.0, (float)$entries[$key]['tax_incl'] - ($adjustmentTaxIncl * $taxInclRatio));
        }
    }

    protected function getOrderSlipCartRuleAdjustmentRate(): float
    {
        if ((float)$this->order_slip->total_products_tax_incl <= 0.0) {
            return 0.0;
        }

        return Tools::ps_round(
            ((float)$this->order_slip->adjustment_cart_rule_tax_incl / (float)$this->order_slip->total_products_tax_incl) * 100,
            2
        );
    }

    protected function getOrderSlipFeeAdjustmentRate(): float
    {
        $feeBase = max(
            0.0,
            (float)$this->order_slip->total_products_tax_incl
            + (float)$this->order_slip->total_shipping_tax_incl
            - (float)$this->order_slip->adjustment_cart_rule_tax_incl
        );

        if ($feeBase <= 0.0) {
            return 0.0;
        }

        return Tools::ps_round(
            ((float)$this->order_slip->adjustment_fee_tax_incl / $feeBase) * 100,
            2
        );
    }

    protected function getRoundedRefundTotal(bool $taxExcludedDisplay, float $totalCartRule): float
    {
        if ($taxExcludedDisplay) {
            $productsTotal = (float)$this->order_slip->total_products_tax_excl;
            $cartRuleAdjustment = $totalCartRule + (float)$this->order_slip->adjustment_cart_rule_tax_excl;
            $shippingTotal = (float)$this->order_slip->total_shipping_tax_excl;
            $feeAdjustment = (float)$this->order_slip->adjustment_fee_tax_excl;
        } else {
            $productsTotal = (float)$this->order_slip->total_products_tax_incl;
            $cartRuleAdjustment = $totalCartRule + (float)$this->order_slip->adjustment_cart_rule_tax_incl;
            $shippingTotal = (float)$this->order_slip->total_shipping_tax_incl;
            $feeAdjustment = (float)$this->order_slip->adjustment_fee_tax_incl;
        }

        $refundTotal = max(0.0, $productsTotal - $cartRuleAdjustment + $shippingTotal - $feeAdjustment);
        $roundingUnit = class_exists('RefundPolicy') ? RefundPolicy::ROUNDING_UNIT : 0.05;

        return Tools::roundPrice(round($refundTotal / $roundingUnit) * $roundingUnit);
    }

    protected function getRefundPayment(): array
    {
        $row = Db::getInstance()->getRow(
            (new DbQuery())
                ->select('`payment_method`, `date_add`')
                ->from('order_payment')
                ->where('`id_order_slip` = '.(int)$this->order_slip->id)
                ->orderBy('`id_order_payment` DESC')
        );

        return is_array($row) ? $row : [];
    }

    /**
     * Returns different tax breakdown elements
     *
     * @return array Different tax breakdown elements
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getTaxBreakdown()
    {
        $breakdowns = [
            'product_tax'  => $this->getProductTaxesBreakdown(),
            'shipping_tax' => $this->getShippingTaxesBreakdown(),
            'ecotax_tax'   => $this->order_slip->getEcoTaxTaxesBreakdown(),
        ];

        foreach ($breakdowns as $type => $bd) {
            if (empty($bd)) {
                unset($breakdowns[$type]);
            }
        }

        if (empty($breakdowns)) {
            $breakdowns = false;
        }

        if (isset($breakdowns['product_tax'])) {
            foreach ($breakdowns['product_tax'] as &$bd) {
                $bd['total_tax_excl'] = $bd['total_price_tax_excl'];
            }
        }

        if (isset($breakdowns['ecotax_tax'])) {
            foreach ($breakdowns['ecotax_tax'] as &$bd) {
                $bd['total_tax_excl'] = $bd['ecotax_tax_excl'];
                $bd['total_amount'] = $bd['ecotax_tax_incl'] - $bd['ecotax_tax_excl'];
            }
        }

        return $breakdowns;
    }
}
