{extends file="helpers/view/view.tpl"}

{block name="override_tpl"}
<div class="row order-cancellation-workspace customer-thread-workspace"
	 data-customer-thread-workspace
	 data-auto-scroll="0"
	 data-thread-id="{if $thread}{$thread->id|intval}{else}0{/if}"
	 data-thread-setting-url="{$thread_setting_url|escape:'html':'UTF-8'}"
	 data-thread-update-error="{l s='The communication status could not be updated.'}">
	<div class="col-lg-9 order-cancellation-main-column">
		<div class="panel order-cancellation-refund-panel">
			<div class="panel-heading">
				<i class="icon-money"></i> {if $order_is_paid}{l s='Refund'}{else}{l s='Order adjustment'}{/if}
			</div>

			<div class="order-cancellation-meta">
				{if $order_is_paid}
					<div>
						<span class="text-muted">{l s='Refund method'}</span>
						<span>{if $refund_destination_label}{$refund_destination_label|escape:'html':'UTF-8'}{else}-{/if}</span>
					</div>
				{/if}
				<div>
					<span class="text-muted">{l s='Submitted on'}</span>
					<span>{dateFormat date=$order_cancellation->date_add full=true}</span>
				</div>
				{if isset($summary.employee) && $summary.employee}
					<div>
						<span class="text-muted">{l s='Created by'}</span>
						<span>{$summary.employee|escape:'html':'UTF-8'}</span>
					</div>
				{/if}
			</div>

			{if $is_migrated}
				<div class="alert alert-success order-cancellation-alert">
					{l s='This migrated legacy case is completed. No further action is required.'}
				</div>
			{elseif $refund_completed && !$has_customer_quote}
				<div class="alert alert-success order-cancellation-alert">
					{l s='The refund is completed. No further action is required.'}
				</div>
			{elseif !$has_customer_quote}
				<div class="alert alert-warning order-cancellation-alert">
					<p>{l s='No refund amount was saved when this cancellation was created. The credit slip must therefore be checked and created manually in the order.'}</p>
					{if !empty($summary.order_url)}
						<a class="btn btn-default" href="{$summary.order_url|escape:'html':'UTF-8'}"><i class="icon-external-link"></i> {l s='View order'}</a>
					{/if}
				</div>
			{else}
				<div class="table-responsive">
					<table class="table order-cancellation-products">
						<thead>
							<tr>
								<th>{l s='Item'}</th>
								<th class="text-center">{l s='Quantity'}</th>
								<th class="text-right">{l s='Amount'}</th>
								{if $order_is_paid}<th class="text-right">{l s='Fee'}</th>{/if}
								<th class="text-right">{if $order_is_paid}{l s='Refund'}{else}{l s='Invoice reduction'}{/if}</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$details item=detail}
								<tr>
									<td>
										<span class="order-cancellation-product-name">{$detail.product_name|escape:'html':'UTF-8'}</span>
										<span class="text-muted order-cancellation-product-reference">
											{if $detail.product_reference}{$detail.product_reference|escape:'html':'UTF-8'}{else}{l s='No reference'}{/if}
											{if $detail.quoted_fee_policy_label} &middot; {$detail.quoted_fee_policy_label|escape:'html':'UTF-8'}{/if}
										</span>
									</td>
									<td class="text-center">{$detail.cancelled_quantity|intval}</td>
									<td class="text-right">
										{if isset($detail.quoted_product_amount_tax_incl)}
											{displayWtPriceWithCurrency price=$detail.quoted_product_amount_tax_incl currency=$currency}
										{else}-{/if}
									</td>
									{if $order_is_paid}
										<td class="text-right">
											{if isset($detail.quoted_fee_tax_incl) && $detail.quoted_fee_tax_incl > 0}
												&minus; {displayWtPriceWithCurrency price=$detail.quoted_fee_tax_incl currency=$currency}
											{else}-{/if}
										</td>
									{/if}
									<td class="text-right order-cancellation-line-refund">
										{if isset($detail.quoted_refund_tax_incl)}
											{displayWtPriceWithCurrency price=$detail.quoted_refund_tax_incl currency=$currency}
										{else}-{/if}
									</td>
								</tr>
							{foreachelse}
								<tr><td colspan="{if $order_is_paid}5{else}4{/if}" class="text-center text-muted">{l s='No items found.'}</td></tr>
							{/foreach}
						</tbody>
					</table>
				</div>

				<div class="order-cancellation-refund-summary">
					<div class="order-cancellation-refund-row">
						<span>{l s='Items'}</span>
						<span>{displayWtPriceWithCurrency price=$quoted_products_total currency=$currency}</span>
					</div>
					{if $has_quoted_shipping && $order_cancellation->quoted_shipping_tax_incl > 0}
						<div class="order-cancellation-refund-row">
							<span>{l s='Shipping'}</span>
							<span>{displayWtPriceWithCurrency price=$order_cancellation->quoted_shipping_tax_incl currency=$currency}</span>
						</div>
					{/if}
					{if $has_quoted_shipping && $order_cancellation->quoted_shipping_tax_incl < 0}
						<div class="order-cancellation-refund-row text-muted">
							<span>{l s='New shipping charge'}</span>
							<span>&minus; {displayWtPriceWithCurrency price=(0-$order_cancellation->quoted_shipping_tax_incl) currency=$currency}</span>
						</div>
					{/if}
					{if $has_quoted_fee && $order_cancellation->quoted_fee_tax_incl > 0}
						<div class="order-cancellation-refund-row text-muted">
							<span>{l s='Cancellation fee'}</span>
							<span>&minus; {displayWtPriceWithCurrency price=$order_cancellation->quoted_fee_tax_incl currency=$currency}</span>
						</div>
					{/if}
					<div class="order-cancellation-refund-row order-cancellation-refund-total">
						<span>{if $order_is_paid}{l s='Refund'}{else}{l s='Invoice reduction'}{/if}</span>
						<span>{displayWtPriceWithCurrency price=$order_cancellation->quoted_refund_total_tax_incl currency=$currency}</span>
					</div>
				</div>

				<div class="order-cancellation-action">
					{if $has_incomplete_refund_slip}
						<div class="alert alert-danger">
							<strong>{l s='The credit slip has been created, but the payout is not yet complete.'}</strong>
							<p>{l s='Check the transaction in Payrexx. If the status is unclear, do not issue a second refund and report the case.'}</p>
						</div>
					{/if}
					{if $can_confirm_refund}
						<p>{l s='The product quantities have already been cancelled. This action creates the credit slip and issues the financial refund.'}</p>
						<div class="btn-toolbar" role="toolbar">
							<div class="btn-group">
								<form method="post" action="{$refund_confirmation_url|escape:'html':'UTF-8'}&amp;vieworder_cancellation=1&amp;id_order_cancellation={$order_cancellation->id|intval}" onsubmit="return confirm('{l s='This action creates the credit slip and issues the refund. Do you want to continue?' js=1}');">
									<input type="hidden" name="id_order_cancellation" value="{$order_cancellation->id|intval}">
									<button type="submit" name="confirmCancellationRefund" class="btn btn-primary">
										<i class="icon-check"></i>
										{if $order_cancellation->requested_refund_method == 'store_credit'}
											{displayWtPriceWithCurrency price=$order_cancellation->quoted_refund_total_tax_incl currency=$currency} {l s='issue as store credit'}
										{else}
											{if $has_incomplete_refund_slip}{l s='Retry refund:'}{/if} {displayWtPriceWithCurrency price=$order_cancellation->quoted_refund_total_tax_incl currency=$currency} {l s='via'} {$refund_destination_label|escape:'html':'UTF-8'} {l s='refund'}
										{/if}
									</button>
								</form>
							</div>
							{if !empty($summary.adjust_refund_url)}
								<div class="btn-group">
									<a class="btn btn-default" href="{$summary.adjust_refund_url|escape:'html':'UTF-8'}"><i class="icon-pencil"></i> {l s='Adjust amounts in order'}</a>
								</div>
							{/if}
						</div>
					{elseif $can_apply_order_adjustment}
						<p>{l s='The quantities are already blocked from shipping. This action permanently adjusts order lines, discount, shipping costs and invoice total.'}</p>
						<form method="post" action="{$refund_confirmation_url|escape:'html':'UTF-8'}&amp;vieworder_cancellation=1&amp;id_order_cancellation={$order_cancellation->id|intval}">
							<input type="hidden" name="id_order_cancellation" value="{$order_cancellation->id|intval}">
							<button type="submit" name="applyCancellationOrderAdjustment" class="btn btn-primary"><i class="icon-check"></i> {l s='Adjust order'}</button>
						</form>
					{elseif $requires_manual_order_review}
						<div class="alert alert-warning">{l s='The newly applicable shipping costs are at least as high as the cancelled item amount. This rare case must be checked manually in the order; the customer will not be charged an additional amount.'}</div>
						{if !empty($summary.order_url)}
							<a class="btn btn-default" href="{$summary.order_url|escape:'html':'UTF-8'}"><i class="icon-external-link"></i> {l s='Check in order'}</a>
						{/if}
					{elseif $financial_step_completed}
						<div class="alert alert-success">
							{if $order_is_paid}
								{l s='The credit slip and refund have been created. The cancellation is completed.'}
							{else}
								{l s='The order has been adjusted. The cancellation is completed.'}
							{/if}
						</div>
					{elseif $refund_completed}
						<div class="alert alert-success">
							{l s='The credit slip and refund have been created. The cancellation is completed.'}
						</div>
					{elseif !$financial_refund_required}
						<div class="alert alert-info">
							{l s='No financial refund is required for this cancellation.'}
						</div>
					{else}
						<div class="alert alert-warning">
							{l s='This cancellation cannot be refunded automatically in its current state.'}
						</div>
					{/if}
				</div>
			{/if}
		</div>

		{if $order_is_paid}<div class="panel order-cancellation-credit-slips">
			<div class="panel-heading">
				<i class="icon-file-text"></i> {l s='Credit slips'}
			</div>
			{if $order_slips}
				<div class="table-responsive">
					<table class="table">
						<thead>
							<tr>
								<th>{l s='Credit slip'}</th>
								<th>{l s='Created on'}</th>
								<th class="text-right">{l s='Item amount'}</th>
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
							{/foreach}
						</tbody>
					</table>
				</div>
			{else}
				<p class="text-muted order-cancellation-empty">{l s='No credit slip yet.'}</p>
			{/if}
		</div>{/if}

		{include file=$conversation_template}

	</div>

	<div class="col-lg-3 order-cancellation-sidebar">
		<div class="panel order-cancellation-process-panel">
			<div class="panel-heading">
				<i class="icon-tasks"></i> {l s='Process status'}
			</div>

			{if $order_is_paid}<div class="order-cancellation-settings-form">
				<div class="form-group">
					<label for="order_cancellation_refund_method">{l s='Refund method'}</label>
					<select class="form-control js-order-cancellation-setting" id="order_cancellation_refund_method" data-setting="requested_refund_method">
						{foreach from=$refund_method_options item=refund_method_option}
							<option value="{$refund_method_option.id|escape:'html':'UTF-8'}" {if $order_cancellation->requested_refund_method == $refund_method_option.id}selected="selected"{/if}>{$refund_method_option.name|escape:'html':'UTF-8'}</option>
						{/foreach}
					</select>
				</div>
			</div>{/if}

			<div class="order-cancellation-process-progress">
			{if $is_migrated}
				<div class="order-cancellation-process-step is-complete">
					<i class="icon-check"></i>
					<div>
						<span>{l s='Completed'}</span>
						<small>{l s='Migrated legacy case'}</small>
					</div>
				</div>
			{else}

			{if !$order_is_paid}
				<div class="order-cancellation-process-step {if $financial_step_completed}is-complete{else}is-current{/if}">
					<i class="{if $financial_step_completed}icon-check{else}icon-time{/if}"></i>
					<div>
						<span>{if $financial_step_completed}{l s='Order adjusted'}{else}{l s='Order adjustment pending'}{/if}</span>
						{if !$has_customer_quote || $requires_manual_order_review}<small>{l s='Check manually'}</small>{/if}
					</div>
				</div>
			{else}
				<div class="order-cancellation-process-step is-complete">
					<i class="icon-check"></i>
					<div><span>{l s='Quantities cancelled'}</span></div>
				</div>

				<div class="order-cancellation-process-step {if $financial_step_completed}is-complete{elseif $can_confirm_refund || !$has_customer_quote}is-current{else}is-pending{/if}">
					<i class="{if $financial_step_completed}icon-check{elseif !$has_customer_quote}icon-warning-sign{else}icon-time{/if}"></i>
					<div>
						<span>{if $financial_step_completed}{l s='Refund completed'}{else}{l s='Refund pending'}{/if}</span>
						{if !$has_customer_quote}<small>{l s='Check manually'}</small>{/if}
					</div>
				</div>
			{/if}
			{/if}

			<div class="order-cancellation-next-action">
				<span class="text-muted">{l s='Next action'}</span>
				<strong>{$next_action_label|escape:'html':'UTF-8'}</strong>
			</div>
			</div>
		</div>

		<div class="panel order-cancellation-links-panel">
			<div class="panel-heading">
				<i class="icon-link"></i> {l s='Links'}
			</div>
			{if !empty($summary.order_url)}
				<a class="order-cancellation-link" href="{$summary.order_url|escape:'html':'UTF-8'}">
					<i class="icon-shopping-cart"></i>
					<span>{l s='Order'} {$summary.reference|escape:'html':'UTF-8'}</span>
				</a>
			{/if}
			{if !empty($summary.customer_url)}
				<a class="order-cancellation-link" href="{$summary.customer_url|escape:'html':'UTF-8'}">
					<i class="icon-user"></i>
					<span>{$summary.customer|escape:'html':'UTF-8'}</span>
				</a>
			{/if}
			{foreach from=$order_slips item=order_slip}
				<a class="order-cancellation-link" href="{$order_slip.url|escape:'html':'UTF-8'}">
					<i class="icon-file-text"></i>
					<span>{l s='Credit slip'} #{$order_slip.id_order_slip|intval}</span>
				</a>
			{/foreach}
		</div>
	</div>
</div>

<script type="text/javascript">
	$(function() {
		var cancellationSettingUrl = '{$refund_confirmation_url|escape:'javascript':'UTF-8'}';
		var cancellationId = {$order_cancellation->id|intval};

		$('.js-order-cancellation-setting').each(function() {
			$(this).data('saved-value', this.value);
		}).on('change', function() {
			var select = $(this);
			var previousValue = select.data('saved-value');

			select.prop('disabled', true);
			$.post(cancellationSettingUrl, {
				ajax: 1,
				action: 'updateCancellationSetting',
				id_order_cancellation: cancellationId,
				field: select.data('setting'),
				value: select.val()
			}, function(data) {
				if (data.success) {
					select.data('saved-value', select.val());
					showSuccessMessage(data.text);
				} else {
					select.val(previousValue);
					showErrorMessage(data.text);
				}
			}, 'json').fail(function() {
				select.val(previousValue);
				showErrorMessage('{l s='The cancellation could not be updated.' js=1}');
			}).always(function() {
				select.prop('disabled', false);
			});
		});
	});
</script>

<style>
	.order-cancellation-main-column,
	.order-cancellation-sidebar { min-width: 0; }
	.order-cancellation-meta { display: flex; flex-wrap: wrap; margin: -4px -12px 18px; padding-bottom: 14px; border-bottom: 1px solid #e5e5e5; }
	.order-cancellation-meta > div { min-width: 190px; padding: 4px 12px; }
	.order-cancellation-meta span { display: block; }
	.order-cancellation-meta .text-muted { margin-bottom: 2px; font-size: 11px; }
	.order-cancellation-alert p { margin-bottom: 10px; }
	.order-cancellation-products { margin-bottom: 6px; }
	.order-cancellation-products > tbody > tr > td { padding-top: 12px; padding-bottom: 12px; vertical-align: middle; }
	.order-cancellation-product-name,
	.order-cancellation-product-reference { display: block; }
	.order-cancellation-product-reference { margin-top: 3px; font-size: 11px; }
	.order-cancellation-line-refund { font-weight: 600; }
	.order-cancellation-refund-summary { width: 380px; max-width: 100%; margin: 12px 0 0 auto; }
	.order-cancellation-refund-row { display: flex; padding: 6px 0; border-bottom: 1px solid #eee; justify-content: space-between; gap: 20px; }
	.order-cancellation-refund-total { padding-top: 10px; border-bottom: 0; font-size: 16px; font-weight: 600; }
	.order-cancellation-action { margin-top: 18px; padding-top: 16px; border-top: 1px solid #e5e5e5; }
	.order-cancellation-action > :last-child,
	.order-cancellation-action .alert:last-child { margin-bottom: 0; }
	.order-cancellation-empty { margin: 0; }
	.order-cancellation-settings-form { margin-bottom: 18px; padding-bottom: 18px; border-bottom: 1px solid #e5e5e5; }
	.order-cancellation-settings-form .form-group { margin-right: 0; margin-left: 0; }
	.order-cancellation-settings-form .form-group:last-of-type { margin-bottom: 12px; }
	.order-cancellation-settings-form label { display: block; }
	.order-cancellation-process-step { position: relative; display: flex; min-height: 54px; padding: 3px 0 15px 34px; }
	.order-cancellation-process-step:not(:last-of-type):before { position: absolute; top: 25px; bottom: -1px; left: 12px; width: 1px; background: #d8d8d8; content: ''; }
	.order-cancellation-process-step > i { position: absolute; top: 0; left: 0; display: flex; width: 25px; height: 25px; border: 1px solid #c8c8c8; border-radius: 50%; background: #fff; color: #999; align-items: center; justify-content: center; }
	.order-cancellation-process-step span,
	.order-cancellation-process-step small { display: block; }
	.order-cancellation-process-step small { margin-top: 2px; color: #777; }
	.order-cancellation-process-step.is-complete > i { border-color: #72c279; background: #72c279; color: #fff; }
	.order-cancellation-process-step.is-current > i { border-color: #25b9d7; background: #25b9d7; color: #fff; }
	.order-cancellation-next-action { margin-top: 2px; padding-top: 13px; border-top: 1px solid #e5e5e5; }
	.order-cancellation-next-action span,
	.order-cancellation-next-action strong { display: block; }
	.order-cancellation-next-action span { margin-bottom: 3px; font-size: 11px; }
	.order-cancellation-link { display: flex; padding: 10px 0; border-bottom: 1px solid #eee; align-items: center; gap: 9px; text-decoration: none; }
	.order-cancellation-link:last-child { padding-bottom: 0; border-bottom: 0; }
	.order-cancellation-link i { width: 16px; color: #777; text-align: center; }
	.order-cancellation-link span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	@media (max-width: 1199px) {
		.order-cancellation-sidebar { margin-top: 0; }
	}
	@media (max-width: 767px) {
		.order-cancellation-meta { display: block; }
		.order-cancellation-meta > div { min-width: 0; }
		.order-cancellation-refund-summary { width: 100%; }
		.order-cancellation-action .btn { width: 100%; white-space: normal; }
	}
</style>
{/block}
