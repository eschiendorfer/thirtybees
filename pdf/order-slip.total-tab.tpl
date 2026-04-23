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
<table id="total-tab" width="100%">

	{if $order_slip->shipping_cost_amount > 0}
		<tr>
			{if $tax_excluded_display}
				<td class="grey" width="70%">{l s='Shipping (Tax Excl.)' pdf='true'}</td>
			{else}
				<td class="grey" width="70%">{l s='Shipping (Tax Incl.)' pdf='true'}</td>
			{/if}
			<td class="white" width="30%">
				- {displayPrice currency=$order->id_currency price=$order_slip->shipping_cost_amount}
			</td>
		</tr>
	{/if}

	{if isset($order_details) && count($order_details) > 0}
		{if $tax_excluded_display}
			<tr>
				<td class="grey" width="70%">
					{l s='Product Total (Tax Excl.)' pdf='true'}
				</td>
				<td class="white" width="30%">
					- {displayPrice currency=$order->id_currency price=$order_slip->total_products_tax_excl}
				</td>
			</tr>
		{else}
			<tr>
				<td class="grey" width="70%">
					{l s='Product Total (Tax Incl.)' pdf='true'}
				</td>
				<td class="white" width="30%">
					- {displayPrice currency=$order->id_currency price=$order_slip->total_products_tax_incl}
				</td>
			</tr>
		{/if}
	{/if}
	
	<tr class="bold">
		<td class="grey" width="70%">
			{if $tax_excluded_display}{l s='Total (Tax Excl.)' pdf='true'}{else}{l s='Total (Tax Incl.)' pdf='true'}{/if}
		</td>
		<td class="white" width="30%">
			{if $tax_excluded_display}
				- {displayPrice currency=$order->id_currency price=($order_slip->total_products_tax_excl + $order_slip->total_shipping_tax_excl - $total_cart_rule)}
			{else}
				- {displayPrice currency=$order->id_currency price=($order_slip->total_products_tax_incl + $order_slip->total_shipping_tax_incl - $total_cart_rule)}
			{/if}
		</td>
	</tr>

</table>
