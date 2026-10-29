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

{extends file='helpers/form/form.tpl'}

{block name="defaultForm"}
<div class="row order-return-workspace customer-thread-workspace" data-customer-thread-workspace data-auto-scroll="0" data-thread-id="{if $thread}{$thread->id|intval}{else}0{/if}">
    <div class="col-lg-9">
        {$smarty.block.parent}
        {include file=$return_settlement_template}
        {if !empty($conversation_template)}{include file=$conversation_template}{/if}
    </div>
    <div class="col-lg-3">
        <div class="panel">
            <div class="panel-heading"><i class="icon-link"></i> Verknüpfungen</div>
            <a class="return-link" href="{$url_order|escape:'html':'UTF-8'}"><i class="icon-shopping-cart"></i> Bestellung {$return_order_reference|escape:'html':'UTF-8'}</a>
            <a class="return-link" href="{$url_customer|escape:'html':'UTF-8'}"><i class="icon-user"></i> {$customer->firstname|escape:'html':'UTF-8'} {$customer->lastname|escape:'html':'UTF-8'}</a>
            <a class="return-link" href="{$return_slip_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-file-text"></i> Rücksendeschein</a>
            {foreach $return_settlement.slips as $slip}
                <a class="return-link" href="{$slip.url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-file-text"></i> Rechnung + Gutschrift #{$slip.id_order_slip|intval}</a>
            {/foreach}
        </div>
    </div>
</div>
{/block}

{block name="input"}
    {if $input.type == 'list_products'}
        <div class="return-meta"><span class="text-muted">Angemeldet am</span><br>{dateFormat date=$return_created full=true}</div>
        {if $return_legacy_reason|trim != ''}<p><strong>Kundenbegründung:</strong><br>{$return_legacy_reason|escape:'html':'UTF-8'|nl2br}</p>{/if}
        <div class="table-responsive">
		<table class="table return-products">
			<thead>
				<tr>
					<th>{l s='Product name'}</th>
					<th class="text-left">
						<span class="title_box">
							<span class="label-tooltip" data-toggle="tooltip" title="{l s='Quantity the customer registered for return.'}">
								{l s='Registered quantity'}
							</span>
						</span>
					</th>
					<th class="text-left">
						<span class="title_box">
							<span class="label-tooltip" data-toggle="tooltip" title="{l s='Quantity actually received from the customer.'}">
								{l s='Received quantity'}
							</span>
						</span>
					</th>
					<th class="text-left">
						<span class="title_box">
							<span class="label-tooltip" data-toggle="tooltip" title="{l s='Only returns in perfect condition may be returned to stock. Products sold in the Fundus must not be entered here.'}">
								{l s='Restocked quantity'}
							</span>
						</span>
					</th>
                    <th class="text-right">{if $return_settlement.breakdown.is_new}Gutzuschreiben{else}Gutgeschrieben{/if}</th>
                    <th></th>
				</tr>
			</thead>
			<tbody>
				{foreach $returnedCustomizations as $returnedCustomization}
					<tr>
						<td>{$returnedCustomization['name']|escape:'html':'UTF-8'}<small class="text-muted">{$returnedCustomization['reference']|escape:'html':'UTF-8'}</small></td>
						<td class="text-center">{$returnedCustomization['product_quantity']|intval}</td>
						<td class="text-center">--</td>
						<td class="text-center">--</td>
                        <td class="text-right">—</td>
						<td class="text-center">
							<a class="btn btn-default" href="{$current|escape:'html':'UTF-8'}&amp;deleteorder_return_detail&amp;id_order_detail={$returnedCustomization['id_order_detail']}&amp;id_order_return={$id_order_return}&amp;id_customization={$returnedCustomization['id_customization']}&amp;token={$token|escape:'html':'UTF-8'}">
								<i class="icon-remove"></i>
							</a>
						</td>
					</tr>
					{assign var='productId' value=$returnedCustomization.product_id}
					{assign var='productAttributeId' value=$returnedCustomization.product_attribute_id}
					{assign var='customizationId' value=$returnedCustomization.id_customization}
					{assign var='addressDeliveryId' value=$returnedCustomization.id_address_delivery}
					{foreach $customizedDatas.$productId.$productAttributeId.$addressDeliveryId.$customizationId.datas as $type => $datas}
						<tr>
							<td colspan="6">
								<div class="form-horizontal">
									{if $type == Product::CUSTOMIZE_FILE}
										{foreach from=$datas item='data'}
											<div class="form-group">
												<span class="col-lg-3 control-label"><strong>{l s='Attachment'}</strong></span>
												<div class="col-lg-9">
													<a href="displayImage.php?img={$data['value']}&amp;name={$returnedCustomization['id_order_detail']|intval}-file{$smarty.foreach.data.iteration.iteration}" class="_blank"><img class="img-thumbnail" src="{$picture_folder}{$data['value']}_small" alt="" /></a>
												</div>
											</div>
										{/foreach}
									{elseif $type == Product::CUSTOMIZE_TEXTFIELD}
											{foreach from=$datas item='data'}
												<div class="form-group">
													<span class="control-label col-lg-3"><strong>{if $data['name']}{$data['name']}{else}{l s='Text #%d' sprintf=$smarty.foreach.data.iteration}{/if}</strong></span>
													<div class="col-lg-9">
														<p class="form-control-static">
															{$data['value']}
														</p>
													</div>
												</div>
											{/foreach}
									{/if}
								</div>
							</td>
						</tr>
					{/foreach}
				{/foreach}

				{* Classic products *}
				{foreach $products as $k => $product}
					{if !isset($quantityDisplayed[$product['id_order_detail']]) || $product['product_quantity']|intval > $quantityDisplayed[$product['id_order_detail']]|intval}
						<tr>
							<td>{$product['product_name']|escape:'html':'UTF-8'}<small class="text-muted">{$product['product_reference']|escape:'html':'UTF-8'}</small></td>
							<td class="text-center">
								<div class="input-group" style="width: 90px;">
									<input
										type="number"
										min="0"
										max="{$product['return_quantity_max']|intval}"
										step="1"
										class="form-control return-quantity-input return-registered-quantity"
										name="return_registered_quantity[{$product['id_order_detail']|intval}]"
										value="{$product['return_registered_quantity']|intval}"
										data-order-detail="{$product['id_order_detail']|intval}"
									/>
									<span class="input-group-addon">/ {$product['return_quantity_max']|intval}</span>
								</div>
							</td>
							<td class="text-center">
								<div class="input-group" style="width: 90px;">
									<input
										type="number"
										min="0"
										max="{$product['return_quantity_max']|intval}"
										step="1"
										class="form-control return-quantity-input return-received-quantity"
										name="return_received_quantity[{$product['id_order_detail']|intval}]"
										value="{$product['return_received_quantity']|intval}"
										data-order-detail="{$product['id_order_detail']|intval}"
									/>
									<span class="input-group-addon">/ {$product['return_quantity_max']|intval}</span>
								</div>
							</td>
							<td class="text-center">
								<div class="input-group" style="width: 90px;">
									<input
										type="number"
										min="0"
										max="{$product['return_received_quantity']|intval}"
										step="1"
										class="form-control return-quantity-input return-restocked-quantity"
										name="return_restocked_quantity[{$product['id_order_detail']|intval}]"
										value="{$product['return_reinjected_quantity']|intval}"
										data-order-detail="{$product['id_order_detail']|intval}"
									/>
									<span class="input-group-addon">/ <span class="return-restocked-max" data-order-detail="{$product['id_order_detail']|intval}">{$product['return_received_quantity']|intval}</span></span>
								</div>
							</td>
                        <td class="text-right return-line-amount">
                            {displayPrice price=$product.credit_amount currency=$return_currency->id}
                            {if $product.previous_credit_amount > 0}<small class="text-muted">Bereits gutgeschrieben: {displayPrice price=$product.previous_credit_amount currency=$return_currency->id}</small>{/if}
                        </td>
							<td class="text-center">
								<a class="btn btn-default" href="{$current|escape:'html':'UTF-8'}&amp;deleteorder_return_detail&amp;id_order_detail={$product['id_order_detail']}&amp;id_order_return={$id_order_return}&amp;token={$token|escape:'html':'UTF-8'}">
									<i class="icon-remove"></i>
								</a>
							</td>
						</tr>
					{/if}
				{/foreach}
			</tbody>
		</table>
        </div>
		<script type="text/javascript">
			(function () {

				function asQuantity(value) {
					var quantity = parseInt(value, 10);
					return isNaN(quantity) ? 0 : quantity;
				}

				function clampInput(input) {
					var $input = $(input);
					var min = asQuantity($input.attr('min'));
					var max = asQuantity($input.attr('max'));
					var value = asQuantity($input.val());

					if (value < min) {
						value = min;
					}
					if (value > max) {
						value = max;
					}

					$input.val(value);
				}

				function syncRestockedLimit(idOrderDetail) {
					var $received = $('.return-received-quantity[data-order-detail="' + idOrderDetail + '"]');
					var $restocked = $('.return-restocked-quantity[data-order-detail="' + idOrderDetail + '"]');
					var received = asQuantity($received.val());

					$restocked.attr('max', received);
					$('.return-restocked-max[data-order-detail="' + idOrderDetail + '"]').text(received);
					clampInput($restocked);
				}

				$('.return-quantity-input').on('input change', function () {
					$(this).data('changed', true);
					clampInput(this);

					if ($(this).hasClass('return-received-quantity')) {
						syncRestockedLimit($(this).data('order-detail'));
					}
				});

			}());
		</script>
	{else}
		{$smarty.block.parent}
	{/if}
{/block}

{block name="after"}{/block}
