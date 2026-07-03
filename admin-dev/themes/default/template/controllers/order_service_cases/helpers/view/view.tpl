{extends file="helpers/view/view.tpl"}

{block name="override_tpl"}
<div class="panel">
	<h3><i class="icon-life-ring"></i> {l s='Order service case'}</h3>
	<div class="row form-horizontal">
		<div class="col-lg-6">
			<div class="form-group">
				<label class="control-label col-lg-4">{l s='ID'}</label>
				<div class="col-lg-8">
					<p class="form-control-static">{$order_service_case->id|intval}</p>
				</div>
			</div>
			<div class="form-group">
				<label class="control-label col-lg-4">{l s='Order'}</label>
				<div class="col-lg-8">
					<p class="form-control-static">
						{if isset($summary.id_order) && $summary.id_order}
							<a href="{$summary.order_url|escape:'html':'UTF-8'}">{$summary.reference|escape:'html':'UTF-8'}</a>
						{else}
							<span class="text-muted">-</span>
						{/if}
					</p>
				</div>
			</div>
			<div class="form-group">
				<label class="control-label col-lg-4">{l s='Customer'}</label>
				<div class="col-lg-8">
					<p class="form-control-static">
						{if isset($summary.id_customer) && $summary.id_customer}
							<a href="{$summary.customer_url|escape:'html':'UTF-8'}">{$summary.customer|escape:'html':'UTF-8'}</a>
						{else}
							<span class="text-muted">-</span>
						{/if}
					</p>
				</div>
			</div>
			<div class="form-group">
				<label class="control-label col-lg-4">{l s='Status'}</label>
				<div class="col-lg-8">
					<p class="form-control-static">{$status_label|escape:'html':'UTF-8'}</p>
				</div>
			</div>
			<div class="form-group">
				<label class="control-label col-lg-4">{l s='Case type'}</label>
				<div class="col-lg-8">
					<p class="form-control-static">{$case_type_label|escape:'html':'UTF-8'}</p>
				</div>
			</div>
			<div class="form-group">
				<label class="control-label col-lg-4">{l s='Requested solution'}</label>
				<div class="col-lg-8">
					<p class="form-control-static">
						{if $requested_solution_label}
							{$requested_solution_label|escape:'html':'UTF-8'}
						{else}
							<span class="text-muted">-</span>
						{/if}
					</p>
				</div>
			</div>
		</div>
		<div class="col-lg-6">
			<div class="form-group">
				<label class="control-label col-lg-4">{l s='Employee'}</label>
				<div class="col-lg-8">
					<p class="form-control-static">
						{if isset($summary.employee) && $summary.employee}
							{$summary.employee|escape:'html':'UTF-8'}
						{else}
							<span class="text-muted">-</span>
						{/if}
					</p>
				</div>
			</div>
			<div class="form-group">
				<label class="control-label col-lg-4">{l s='Replacement order'}</label>
				<div class="col-lg-8">
					<p class="form-control-static">
						{if isset($summary.id_replacement_order) && $summary.id_replacement_order}
							<a href="{$summary.replacement_order_url|escape:'html':'UTF-8'}">{$summary.replacement_reference|escape:'html':'UTF-8'}</a>
						{else}
							<span class="text-muted">-</span>
						{/if}
					</p>
				</div>
			</div>
			<div class="form-group">
				<label class="control-label col-lg-4">{l s='Migrated'}</label>
				<div class="col-lg-8">
					<p class="form-control-static">{if $order_service_case->migrated}{l s='Yes'}{else}{l s='No'}{/if}</p>
				</div>
			</div>
			<div class="form-group">
				<label class="control-label col-lg-4">{l s='Date issued'}</label>
				<div class="col-lg-8">
					<p class="form-control-static">{dateFormat date=$order_service_case->date_add full=true}</p>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="panel">
	<h3><i class="icon-list"></i> {l s='Products'}</h3>
	<table class="table">
		<thead>
			<tr>
				<th>{l s='Order detail ID'}</th>
				<th>{l s='Reference'}</th>
				<th>{l s='Product'}</th>
				<th class="text-right">{l s='Ordered quantity'}</th>
				<th class="text-right">{l s='Service case quantity'}</th>
			</tr>
		</thead>
		<tbody>
			{foreach from=$details item=detail}
				<tr>
					<td class="text-center">{$detail.id_order_detail|intval}</td>
					<td>
						{if $detail.product_reference}
							{$detail.product_reference|escape:'html':'UTF-8'}
						{else}
							<span class="text-muted">-</span>
						{/if}
					</td>
					<td>{$detail.product_name|escape:'html':'UTF-8'}</td>
					<td class="text-right">{$detail.ordered_quantity|intval}</td>
					<td class="text-right">{$detail.service_case_quantity|intval}</td>
				</tr>
			{foreachelse}
				<tr>
					<td colspan="5" class="text-center">{l s='No products found.'}</td>
				</tr>
			{/foreach}
		</tbody>
	</table>
</div>

<div class="panel">
	<h3><i class="icon-file-text"></i> {l s='Credit slips'}</h3>
	<table class="table">
		<thead>
			<tr>
				<th>{l s='Credit slip ID'}</th>
				<th>{l s='Date issued'}</th>
				<th class="text-right">{l s='Total products'}</th>
				<th class="text-right">{l s='Fee'}</th>
			</tr>
		</thead>
		<tbody>
			{foreach from=$order_slips item=order_slip}
				<tr>
					<td><a href="{$order_slip.url|escape:'html':'UTF-8'}">#{$order_slip.id_order_slip|intval}</a></td>
					<td>{dateFormat date=$order_slip.date_add full=true}</td>
					<td class="text-right">{displayWtPriceWithCurrency price=$order_slip.total_products_tax_incl currency=$currency}</td>
					<td class="text-right">{displayWtPriceWithCurrency price=$order_slip.adjustment_fee_tax_incl currency=$currency}</td>
				</tr>
			{foreachelse}
				<tr>
					<td colspan="4" class="text-center">{l s='No credit slips found.'}</td>
				</tr>
			{/foreach}
		</tbody>
	</table>
</div>
{/block}
