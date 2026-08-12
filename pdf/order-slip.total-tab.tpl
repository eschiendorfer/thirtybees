{*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
{if $tax_excluded_display}
	{assign var=products_total value=$order_slip->total_products_tax_excl}
	{assign var=cart_rule_adjustment value=($total_cart_rule + $order_slip->adjustment_cart_rule_tax_excl)}
	{assign var=shipping_total value=$order_slip->total_shipping_tax_excl}
	{assign var=fee_adjustment value=$order_slip->adjustment_fee_tax_excl}
{else}
	{assign var=products_total value=$order_slip->total_products_tax_incl}
	{assign var=cart_rule_adjustment value=($total_cart_rule + $order_slip->adjustment_cart_rule_tax_incl)}
	{assign var=shipping_total value=$order_slip->total_shipping_tax_incl}
	{assign var=fee_adjustment value=$order_slip->adjustment_fee_tax_incl}
{/if}
{assign var=subtotal value=($products_total - $cart_rule_adjustment + $shipping_total)}
{assign var=refund_total value=($subtotal - $fee_adjustment)}

<table id="total-tab" width="100%">

	{if isset($order_details) && count($order_details) > 0}
		<tr>
			<td class="grey" width="70%">
				{if $tax_excluded_display}{l s='Product total (Tax Excl.)' pdf='true'}{else}{l s='Product total (Tax Incl.)' pdf='true'}{/if}
			</td>
			<td class="white" width="30%">
				- {displayPrice currency=$order->id_currency price=$products_total}
			</td>
		</tr>
	{/if}

	{if $cart_rule_adjustment > 0}
		<tr>
			<td class="grey" width="70%">
				{l s='Cart rule adjustment' pdf='true'}{if $cart_rule_adjustment_rate > 0} ({$cart_rule_adjustment_rate|floatval}%){/if}
			</td>
			<td class="white" width="30%">
				+ {displayPrice currency=$order->id_currency price=$cart_rule_adjustment}
			</td>
		</tr>
	{/if}

	{if $shipping_total > 0}
		<tr>
			<td class="grey" width="70%">
				{if $tax_excluded_display}{l s='Shipping (Tax Excl.)' pdf='true'}{else}{l s='Shipping (Tax Incl.)' pdf='true'}{/if}
			</td>
			<td class="white" width="30%">
				- {displayPrice currency=$order->id_currency price=$shipping_total}
			</td>
		</tr>
	{/if}

	<tr>
		<td class="grey" width="70%">
			{l s='Subtotal' pdf='true'}
		</td>
		<td class="white" width="30%">
			- {displayPrice currency=$order->id_currency price=$subtotal}
		</td>
	</tr>

	{if $fee_adjustment > 0}
		<tr>
			<td class="grey" width="70%">
				{l s='Fee adjustment' pdf='true'}{if $fee_adjustment_rate > 0} ({$fee_adjustment_rate|floatval}%){/if}
			</td>
			<td class="white" width="30%">
				+ {displayPrice currency=$order->id_currency price=$fee_adjustment}
			</td>
		</tr>
	{/if}

	<tr class="bold">
		<td class="grey" width="70%">
			{if $tax_excluded_display}{l s='Total refund (Tax Excl.)' pdf='true'}{else}{l s='Total refund (Tax Incl.)' pdf='true'}{/if} *
		</td>
		<td class="white" width="30%">
			- {displayPrice currency=$order->id_currency price=$refund_total_rounded}
		</td>
	</tr>

</table>
<p style="font-size: 8px;">* {l s='Rounded to the nearest 0.05 CHF' pdf='true'}</p>
