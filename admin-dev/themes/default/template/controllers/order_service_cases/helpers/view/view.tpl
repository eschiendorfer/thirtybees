{extends file="helpers/view/view.tpl"}

{block name="override_tpl"}
<div class="row customer-thread-workspace service-case-workspace"
	 data-customer-thread-workspace
	 data-auto-scroll="0"
	 data-thread-id="{if $thread}{$thread->id|intval}{else}0{/if}"
	 data-thread-setting-url="{$thread_setting_url|escape:'html':'UTF-8'}"
	 data-thread-update-error="{l s='The communication status could not be updated.'}"
	 data-entity-setting-url="{$service_case_setting_url|escape:'html':'UTF-8'}"
	 data-entity-setting-action="updateServiceCaseSetting"
	 data-entity-id="{$order_service_case->id|intval}"
	 data-entity-update-error="{l s='The service case could not be updated.'}">
	<div class="col-lg-9 customer-thread-main-column">
		<div class="panel">
			<div class="panel-heading">
				<i class="icon-life-ring"></i> {l s='Service case'}
			</div>

			<div class="row">
				<div class="col-sm-4">
					<small class="text-muted">{l s='Case type'}</small>
					<p><strong>{$case_type_label|escape:'html':'UTF-8'}</strong></p>
				</div>
				<div class="col-sm-4">
					<small class="text-muted">{l s='Requested solution'}</small>
					<p>{if $requested_solution_label}<strong>{$requested_solution_label|escape:'html':'UTF-8'}</strong>{else}<span class="text-muted">-</span>{/if}</p>
				</div>
				<div class="col-sm-4">
					<small class="text-muted">{l s='Received'}</small>
					<p>{dateFormat date=$order_service_case->date_add full=true}</p>
				</div>
			</div>

			<table class="table">
				<thead>
					<tr>
						<th>{l s='Reference'}</th>
						<th>{l s='Product'}</th>
						<th class="text-right">{l s='Ordered'}</th>
						<th class="text-right">{l s='Affected'}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$details item=detail}
						<tr>
							<td>
								{if $detail.product_reference}
									{if $detail.admin_product_url}<a href="{$detail.admin_product_url|escape:'html':'UTF-8'}">{$detail.product_reference|escape:'html':'UTF-8'}</a>{else}{$detail.product_reference|escape:'html':'UTF-8'}{/if}
								{else}
									<span class="text-muted">-</span>
								{/if}
							</td>
							<td>{if $detail.product_url}<a href="{$detail.product_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">{$detail.product_name|escape:'html':'UTF-8'}</a>{else}{$detail.product_name|escape:'html':'UTF-8'}{/if}</td>
							<td class="text-right">{$detail.ordered_quantity|intval}</td>
							<td class="text-right"><strong>{$detail.service_case_quantity|intval}</strong></td>
						</tr>
					{foreachelse}
						<tr>
							<td colspan="4" class="text-center text-muted">{l s='No products found.'}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		</div>

		{include file=$conversation_template}
	</div>

	<div class="col-lg-3 customer-thread-sidebar">
		<div class="panel customer-thread-settings-panel">
			<div class="panel-heading">
				<i class="icon-tasks"></i> {l s='Processing'}
			</div>
			<div class="form-group">
				<label for="service_case_status">{l s='Case status'}</label>
				<select class="form-control js-entity-setting" id="service_case_status" data-setting="status">
					{foreach from=$status_options key=status_value item=status_name}
						<option value="{$status_value|escape:'html':'UTF-8'}" {if $order_service_case->status == $status_value}selected="selected"{/if}>{$status_name|escape:'html':'UTF-8'}</option>
					{/foreach}
				</select>
			</div>

			<div class="form-group">
				<label for="id_employee_assigned">{l s='Employee'}</label>
				<select class="form-control js-entity-employee-assignment"
						id="id_employee_assigned"
						data-update-url="{$assignment_update_url|escape:'html':'UTF-8'}"
						data-entity-type="{$entity_type|intval}"
						data-id-entity="{$order_service_case->id|intval}">
					{foreach from=$assignable_employees key=id_employee item=employee_name}
						<option value="{$id_employee|intval}" {if $assigned_employee_id == $id_employee}selected="selected"{/if}>{$employee_name|escape:'html':'UTF-8'}</option>
					{/foreach}
				</select>
			</div>
		</div>

		<div class="panel service-case-links-panel">
			<div class="panel-heading">
				<i class="icon-link"></i> {l s='Links'}
			</div>
			{if !empty($summary.order_url)}
				<a class="service-case-link" href="{$summary.order_url|escape:'html':'UTF-8'}">
					<i class="icon-shopping-cart"></i>
					<span>{l s='Order'} {$summary.reference|escape:'html':'UTF-8'}</span>
				</a>
			{/if}
			{if !empty($summary.customer_url)}
				<a class="service-case-link" href="{$summary.customer_url|escape:'html':'UTF-8'}">
					<i class="icon-user"></i>
					<span>{$summary.customer|escape:'html':'UTF-8'}</span>
				</a>
			{/if}
			{if !empty($summary.replacement_order_url)}
				<a class="service-case-link" href="{$summary.replacement_order_url|escape:'html':'UTF-8'}">
					<i class="icon-exchange"></i>
					<span>{l s='Replacement order'} {$summary.replacement_reference|escape:'html':'UTF-8'}</span>
				</a>
			{/if}
			{foreach from=$order_slips item=order_slip}
				<a class="service-case-link" href="{$order_slip.url|escape:'html':'UTF-8'}">
					<i class="icon-file-text"></i>
					<span>{l s='Credit slip'} #{$order_slip.id_order_slip|intval}</span>
				</a>
			{/foreach}
		</div>
	</div>
</div>
{/block}
