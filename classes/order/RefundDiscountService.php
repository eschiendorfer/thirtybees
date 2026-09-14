<?php

/** Uses the discount actually booked on the order, excluding paid legacy vouchers. */
class RefundDiscountServiceCore
{
    public function isBoughtVoucher(int $idCartRule): bool
    {
        if (!class_exists('\\CrmModule\\CartRuleExtension') && defined('_PS_MODULE_DIR_')
            && is_file(_PS_MODULE_DIR_.'genzo_crm/autoload.php')) {
            require_once _PS_MODULE_DIR_.'genzo_crm/autoload.php';
        }
        if (!class_exists('\\CrmModule\\CartRuleExtension')) {
            return false;
        }
        $extension = new \CrmModule\CartRuleExtension($idCartRule);
        return (int)$extension->cart_rule_type === \CrmModule\CartRuleTypeEnum::VOUCHER_BOUGHT->value;
    }

    public function getLegacyPaidVoucherAmount(Order $order): float
    {
        $amount = 0.0;
        foreach ($order->getCartRules() as $row) {
            if ($this->isBoughtVoucher((int)$row['id_cart_rule'])) {
                $amount += max(0.0, (float)$row['value']);
            }
        }
        return Tools::roundPrice($amount);
    }

    /** Effective percentages per order detail; fixed discounts are optional for BO suggestions. */
    public function getProductRates(Order $order, bool $percentageOnly): array
    {
        $products = [];
        foreach ($order->getProducts() as $product) {
            $products[(int)$product['id_order_detail']] = $product;
        }
        $discounts = array_fill_keys(array_keys($products), 0.0);
        $shippingLeft = max(0.0, (float)$order->total_shipping_tax_incl);
        foreach ($order->getCartRules() as $row) {
            if ($this->isBoughtVoucher((int)$row['id_cart_rule'])) {
                continue;
            }
            $rule = new CartRule((int)$row['id_cart_rule']);
            $amount = max(0.0, (float)$row['value']);
            if (!empty($row['free_shipping'])) {
                $shipping = min($shippingLeft, $amount);
                $shippingLeft -= $shipping;
                $amount -= $shipping;
            }
            if ($amount <= 0.0 || (Validate::isLoadedObject($rule)
                && (float)$rule->reduction_percent <= 0.0 && (float)$rule->reduction_amount <= 0.0)) {
                continue;
            }
            // Deleted rules cannot safely be guessed to be either fixed or percentage discounts.
            if (!Validate::isLoadedObject($rule)) {
                throw new PrestaShopException(Tools::displayError('A historical discount is missing. Please review the order discount before refunding.'));
            }
            if ($percentageOnly && (float)$rule->reduction_percent <= 0.0) {
                continue;
            }
            $eligible = $products;
            if ((int)$rule->reduction_product > 0) {
                $eligible = array_filter($products, static function ($product) use ($rule) {
                    return (int)$product['product_id'] === (int)$rule->reduction_product;
                });
            } elseif ((int)$rule->reduction_product < 0) {
                $keys = $rule->getRefundEligibleProductKeys($order);
                $eligible = array_filter($products, static function ($product) use ($keys) {
                    return in_array($product['product_id'].'-'.$product['product_attribute_id'], $keys, true);
                });
                if ((int)$rule->reduction_product === -1 && $eligible) {
                    uasort($eligible, static function ($a, $b) {
                        return ($a['total_price_tax_incl'] / max(1, $a['product_quantity']))
                            <=> ($b['total_price_tax_incl'] / max(1, $b['product_quantity']));
                    });
                    $eligible = array_slice($eligible, 0, 1, true);
                }
            }
            $base = array_sum(array_column($eligible, 'total_price_tax_incl'));
            if ($base <= 0.0) {
                throw new PrestaShopException(Tools::displayError('The historical discount cannot be assigned to order products.'));
            }
            foreach ($eligible as $id => $product) {
                $discounts[$id] += min($amount, $base) * (float)$product['total_price_tax_incl'] / $base;
            }
        }
        $rates = [];
        foreach ($products as $id => $product) {
            $base = (float)$product['total_price_tax_incl'];
            $rates[$id] = $base > 0 ? min(100.0, $discounts[$id] / $base * 100) : 0.0;
        }
        return $rates;
    }
}
