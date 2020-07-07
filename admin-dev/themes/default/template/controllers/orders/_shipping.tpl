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
<div class="table-responsive">
	<table class="table" id="shipping_table">
		<thead>
		<tr>
			<th>
				<span class="title_box ">{l s='Date'}</span>
			</th>
			<th>
				<span class="title_box ">&nbsp;</span>
			</th>
			<th>
				<span class="title_box ">{l s='Carrier'}</span>
			</th>
			<th>
				<span class="title_box ">{l s='Weight'}</span>
			</th>
			<th>
				<span class="title_box ">{l s='Shipping cost'}</span>
			</th>
			<th>
				<span class="title_box ">{l s='Tracking number'}</span>
			</th>
			<th></th>
		</tr>
		</thead>
		<tbody>
		{foreach from=$order->getShipping() item=line}
			<tr>
				<td>{dateFormat date=$line.date_add full=true}</td>
				<td>&nbsp;</td>
				<td>{$line.carrier_name}</td>
				<td class="weight">{$line.weight|string_format:"%.3f"} {Configuration::get('PS_WEIGHT_UNIT')}</td>
				<td class="center">
					{if $order->getTaxCalculationMethod() == $smarty.const.PS_TAX_INC}
						{displayPrice price=$line.shipping_cost_tax_incl currency=$currency->id}
					{else}
						{displayPrice price=$line.shipping_cost_tax_excl currency=$currency->id}
					{/if}
				</td>
				<td>
					<span class="shipping_number_show">{if $line.url && $line.tracking_number}<a class="_blank" href="{$line.url|replace:'@':$line.tracking_number}">{$line.tracking_number}</a>{else}{$line.tracking_number}{/if}</span>
				</td>
				<td>
					{if $line.can_edit}
						<a href="#" class="edit_shipping_number_link btn btn-default">
							<i class="icon-pencil"></i>
							{l s='Edit'}
						</a>
					{/if}
				</td>
			</tr>
			<tr class="shipping_edit" style="display:none;">
				<td colspan="7">
					<input type="hidden" name="id_order_carrier" value="{$line.id_order_carrier|htmlentities}" />
					<div class="form-group">
						<label class="control-label col-lg-3">{l s='Carrier'}</label>
						<div class="col-lg-9">
							<select name="id_carrier">
								{foreach from=$carriers item=carrier}
									<option value="{$carrier.id_carrier}" {if $line.id_carrier==$carrier.id_carrier}selected{/if}>{$carrier.name}</option>
								{/foreach}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3">{l s='Recalculate Shipping'}</label>
						<div class="col-lg-9">
							<span class="switch prestashop-switch fixed-width-lg">
								<input type="radio" name="recalculate_shipping" id="recalculate_shipping_on" value="1">
								<label for="recalculate_shipping_on">
									{l s='Yes'}
								</label>
								<input type="radio" name="recalculate_shipping" id="recalculate_shipping_off" value="0" checked="checked">
								<label for="recalculate_shipping_off">
									{l s='No'}
								</label>
								<a class="slide-button btn"></a>
							</span>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3">{l s='Weight'}</label>
						<div class="col-lg-9">
							<div class="input-group">
								<input type="text" name="weight" value="{$line.weight|string_format:"%.3f"}" onkeyup="this.value = this.value.replace(/,/g, '.');" />
								<div class="input-group-addon">{Configuration::get('PS_WEIGHT_UNIT')}</div>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3">{l s='Shipping Cost'}</label>
						<div class="col-lg-9">
							<div class="input-group">
								<input type="text" name="shipping_cost_tax_incl" value="{$line.shipping_cost_tax_incl}" onkeyup="this.value = this.value.replace(/,/g, '.');" />
								<div class="input-group-addon">{$currency->iso_code} {l s='Tax incl.'}</div>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3">{l s='Tracking number'}</label>
						<div class="col-lg-9">
							<input type="text" name="tracking_number" value="{$line.tracking_number|htmlentities}" />
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3">{l s='Send Tracking Email'}</label>
						<div class="col-lg-9">
							<span class="switch prestashop-switch fixed-width-lg">
								<input type="radio" name="send_transit_email" id="send_transit_email_on" value="1">
								<label for="send_transit_email_on">
									{l s='Yes'}
								</label>
								<input type="radio" name="send_transit_email" id="send_transit_email_off" value="0" checked="checked">
								<label for="send_transit_email_off">
									{l s='No'}
								</label>
								<a class="slide-button btn"></a>
							</span>
						</div>
					</div>
					<button type="submit" id="submitShippingNumber" class="btn btn-primary pull-right" name="submitShippingNumber">
						{l s='Update'}
					</button>
					<a href="#" class="cancel_shipping_number_link btn btn-default">
						<i class="icon-remove"></i>
						{l s='Cancel'}
					</a>
				</td>
			</tr>
		{/foreach}
		</tbody>
	</table>
</div>
