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

{extends file="helpers/view/view.tpl"}
{block name="override_tpl"}
<div class="row customer-thread-workspace">
	<div class="col-lg-9 customer-thread-main-column">
		<div class="panel customer-thread-conversation">
			<div class="panel-heading">
				<i class="icon-comments"></i>
				{l s='Messages'}
			</div>

			<div class="customer-thread-message-list">
				{if !$first_message.id_employee}
					{include file="./message.tpl" message=$first_message initial=true}
				{/if}
				{foreach from=$messages item=message}
					{include file="./message.tpl" message=$message initial=false}
				{/foreach}
			</div>

			<form id="customer_thread_reply_form" action="{$link->getAdminLink('AdminCustomerThreads')|escape:'html':'UTF-8'}&amp;id_customer_thread={$thread->id|intval}&amp;viewcustomer_thread" method="post" enctype="multipart/form-data" class="form-horizontal customer-thread-reply">
				<div class="customer-thread-reply-tools">
					<div class="customer-thread-template-select">
						<select class="chosen form-control" name="order_message" id="order_message" onchange="orderOverwriteMessage(this, '{l s='Do you want to overwrite your existing message?'}')">
							<option value="0" selected="selected">{l s='Select response template'}</option>
							{foreach from=$orderMessages item=orderMessage}
								<option value="{$orderMessage['message']|escape:'html':'UTF-8'}">{$orderMessage['name']|escape:'html':'UTF-8'}</option>
							{/foreach}
						</select>
					</div>
					<button type="button" class="btn btn-default" disabled="disabled">
						<i class="icon-magic"></i> {l s='Create AI draft'}
					</button>
					<button type="button" class="btn btn-default customer-thread-attach-invoice" disabled="disabled">
						<i class="icon-file-text"></i> {l s='Attach invoice'}
					</button>
				</div>

				<div class="form-group">
					<div class="col-lg-12">
						<textarea class="form-control" cols="30" rows="7" id="txt_msg" name="reply_message">{$reply_message|escape:'html':'UTF-8'}</textarea>

						<div class="customer-thread-file-upload"
							 data-file-upload
							 data-preview-style="bootstrap"
							 data-upload-url="{$customer_message_upload.upload_url|escape:'html':'UTF-8'}"
							 data-delete-url="{$customer_message_upload.delete_url|escape:'html':'UTF-8'}"
							 data-max-files="{$customer_message_upload.max_files|intval}"
							 data-max-total-size="{$customer_message_upload.max_total_size|intval}"
							 data-remove-label="{l s='Remove file'}"
							 data-uploading-label="{l s='Uploading files...'}"
							 data-upload-error="{l s='The file could not be uploaded.'}"
							 data-delete-error="{l s='The file could not be removed.'}"
							 data-count-error="{l s='Too many files were selected.'}"
							 data-total-size-error="{l s='The files exceed the maximum total size.'}"
							 data-multiple>
							<div class="customer-thread-upload-surface" data-file-upload-surface>
								<button type="button" class="customer-thread-upload-dropzone" data-file-upload-drop-area>
									<i class="icon-cloud-upload icon-2x"></i>
									<span>{l s='Drag files here or'} <u>{l s='select them'}</u></span>
									<small>{$customer_message_upload.help|escape:'html':'UTF-8'}</small>
								</button>
								<div class="customer-thread-upload-preview hidden" data-file-upload-preview></div>
							</div>
							<input type="file" class="hidden" multiple data-file-upload-input>
							<input type="hidden" name="customer_message_attachments" value="{$customer_message_upload.value|escape:'html':'UTF-8'}" data-file-upload-value>
							<div class="help-block hidden" data-file-upload-status></div>
							<div class="alert alert-danger hidden" data-file-upload-error></div>
						</div>
					</div>
				</div>

				<div class="panel-footer">
					<button class="btn btn-default pull-right" name="submitReply"><i class="process-icon-mail-reply"></i> {l s='Send'}</button>
					<input type="hidden" name="id_customer_thread" value="{$thread->id|intval}">
					<input type="hidden" name="msg_email" value="{$thread->email|escape:'htmlall':'UTF-8'}">
				</div>
			</form>
		</div>
	</div>

	<div class="col-lg-3 customer-thread-sidebar">
		<div class="panel customer-thread-settings-panel">
			<div class="panel-heading">
				<i class="icon-tasks"></i> {l s='Processing'}
			</div>
			<div class="form-group">
				<label for="thread_status">{l s='Status'}</label>
				<select class="form-control js-thread-setting" id="thread_status" data-setting="status">
					{foreach from=$thread_statuses key=status_value item=status_label}
						<option value="{$status_value|escape:'html':'UTF-8'}" {if $thread->status == $status_value}selected="selected"{/if}>{$status_label|escape:'html':'UTF-8'}</option>
					{/foreach}
				</select>
			</div>
			<div class="form-group">
				<label for="id_employee_assigned">{l s='Employee'}</label>
				<select class="form-control js-thread-setting" id="id_employee_assigned" data-setting="id_employee_assigned">
					{foreach from=$assignable_employees key=id_employee item=employee_name}
						<option value="{$id_employee|intval}" {if $thread->id_employee_assigned == $id_employee}selected="selected"{/if}>{$employee_name|escape:'html':'UTF-8'}</option>
					{/foreach}
				</select>
			</div>
		</div>

		<div class="panel customer-thread-customer-panel">
			<div class="panel-heading">
				<i class="icon-user"></i>
				{if !empty($customer_overview.url)}
					<a href="{$customer_overview.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer"><strong>{$customer_overview.name|escape:'html':'UTF-8'}</strong></a>
				{elseif !empty($customer_overview.name)}
					<strong>{$customer_overview.name|escape:'html':'UTF-8'}</strong>
				{/if}
				<small class="text-muted customer-thread-customer-email">{$customer_overview.email|idnToUtf8|escape:'html':'UTF-8'}</small>
			</div>

			{if !empty($customer_overview.note_html)}
				<div class="customer-thread-customer-note">
					<strong>{l s='Customer note'}</strong>
					<div>{$customer_overview.note_html}</div>
				</div>
			{/if}

			{if !empty($customer_overview.recent_threads) || !empty($customer_overview.recent_orders)}
				<div class="customer-thread-customer-history">
					{if !empty($customer_overview.recent_threads)}
						<div class="customer-thread-customer-history-column">
							<strong>{l s='Recent tickets'}</strong>
							{foreach from=$customer_overview.recent_threads item=recent_thread}
								<a href="{$recent_thread.url|escape:'html':'UTF-8'}" class="customer-thread-customer-history-item" target="_blank" rel="noopener noreferrer">
									<span>{$recent_thread.title|escape:'html':'UTF-8'}</span>
									<small class="text-muted">{$recent_thread.status|escape:'html':'UTF-8'} &middot; {dateFormat date=$recent_thread.date full=0}</small>
								</a>
							{/foreach}
						</div>
					{/if}
					{if !empty($customer_overview.recent_orders)}
						<div class="customer-thread-customer-history-column">
							<strong>{l s='Recent orders'}</strong>
							{foreach from=$customer_overview.recent_orders item=recent_order}
								<a href="{$recent_order.url|escape:'html':'UTF-8'}" class="customer-thread-customer-history-item" target="_blank" rel="noopener noreferrer">
									<span>{$recent_order.title|escape:'html':'UTF-8'}</span>
									<small class="text-muted">{$recent_order.status|escape:'html':'UTF-8'} &middot; {dateFormat date=$recent_order.date full=0}</small>
								</a>
							{/foreach}
						</div>
					{/if}
				</div>
			{/if}
		</div>

		{if !empty($thread_entity_context)}
			<div class="panel customer-thread-entity-context">
				<div class="panel-heading">
					<i class="icon-info-circle"></i>
					{$thread_entity_context.title|escape:'html':'UTF-8'}
					{if !empty($thread_entity_context.reference)}
						<span class="badge pull-right">{$thread_entity_context.reference|escape:'html':'UTF-8'}</span>
					{/if}
				</div>

				<dl class="customer-thread-context-list">
					{foreach from=$thread_entity_context.fields item=context_field}
						<div class="customer-thread-context-row">
							<dt>{$context_field.label|escape:'html':'UTF-8'}</dt>
							<dd>
								{if !empty($context_field.url)}
									<a href="{$context_field.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">{$context_field.value|escape:'html':'UTF-8'}</a>
								{else}
									{$context_field.value|escape:'html':'UTF-8'}
								{/if}
							</dd>
						</div>
					{/foreach}
				</dl>

				{if !empty($thread_entity_context.products)}
					<div class="customer-thread-context-products">
						{foreach from=$thread_entity_context.products item=context_product}
							<div class="customer-thread-context-product">
								{if !empty($context_product.thumbnail_url) && !empty($context_product.image_url)}
									<a href="{$context_product.image_url|escape:'html':'UTF-8'}"
									   class="customer-thread-context-product-image js-customer-thread-image"
									   data-attachment-name="{$context_product.product_name|escape:'html':'UTF-8'}">
										<img src="{$context_product.thumbnail_url|escape:'html':'UTF-8'}" alt="{$context_product.product_name|escape:'html':'UTF-8'}">
									</a>
								{/if}
								<div class="customer-thread-context-product-details">
									<strong>{$context_product.product_name|escape:'html':'UTF-8'}</strong>
									<small class="text-muted">
									{if !empty($context_product.product_reference)}
									{if !empty($context_product.url)}
										<a href="{$context_product.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">{$context_product.product_reference|escape:'html':'UTF-8'}</a>
										{else}
											{$context_product.product_reference|escape:'html':'UTF-8'}
										{/if} &middot;
									{/if}
									{if !empty($thread_entity_context.product_quantity_label)}
										{$thread_entity_context.product_quantity_label|escape:'html':'UTF-8'}:
									{else}
										{l s='Quantity'}:
									{/if} {$context_product.quantity|intval}
									{if isset($context_product.real_stock)}
										&middot; {l s='Real stock'}: {$context_product.real_stock|intval}
									{/if}
									</small>
								</div>
							</div>
						{/foreach}
					</div>
				{/if}

				{if !empty($thread_entity_context.action.url)}
					<a href="{$thread_entity_context.action.url|escape:'html':'UTF-8'}"
					   class="btn btn-default btn-block customer-thread-context-action"
					   target="_blank" rel="noopener noreferrer">
						<i class="icon-external-link"></i> {$thread_entity_context.action.label|escape:'html':'UTF-8'}
					</a>
				{/if}
			</div>
		{/if}
	</div>
</div>

<div class="modal fade" id="customer-thread-attachment-modal" tabindex="-1" role="dialog" aria-labelledby="customer-thread-attachment-title" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="{l s='Close'}"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="customer-thread-attachment-title"></h4>
			</div>
			<div class="modal-body text-center">
				<img class="img-responsive customer-thread-attachment-image" src="" alt="">
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	$(function() {
		var threadSettingUrl = '{$link->getAdminLink('AdminCustomerThreads')|escape:'javascript':'UTF-8'}';
		var threadId = {$id_customer_thread|intval};
		var openAttachmentImage = function(url, name) {
			$('#customer-thread-attachment-title').text(name || '');
			$('#customer-thread-attachment-modal .customer-thread-attachment-image')
				.attr('src', url)
				.attr('alt', name || '');
			$('#customer-thread-attachment-modal').modal('show');
		};

		$('.js-thread-setting').each(function() {
			$(this).data('saved-value', this.value);
		}).on('change', function() {
			var select = $(this);
			var previousValue = select.data('saved-value');

			$.post(threadSettingUrl, {
				ajax: 1,
				action: 'updateThreadSetting',
				id_customer_thread: threadId,
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
				showErrorMessage('{l s='The thread could not be updated.' js=1}');
			});
		});

		$('.js-customer-thread-image').on('click', function(event) {
			event.preventDefault();
			var attachment = $(this);
			openAttachmentImage(attachment.attr('href'), attachment.data('attachment-name'));
		});

		document.querySelector('.customer-thread-file-upload').addEventListener('tb:file-upload:open', function(event) {
			var attachment = event.detail && event.detail.attachment ? event.detail.attachment : null;
			if (!attachment || !attachment.open_url) {
				return;
			}

			if (String(attachment.mime || '').toLowerCase().indexOf('image/') === 0) {
				openAttachmentImage(attachment.open_url, attachment.name);
				return;
			}

			window.open(attachment.open_url, '_blank', 'noopener');
		});

		$('#customer-thread-attachment-modal').on('hidden.bs.modal', function() {
			$(this).find('.customer-thread-attachment-image').attr('src', '').attr('alt', '');
		});

		if (!window.location.hash) {
			var latestMessage = document.querySelector('.customer-thread-message-list .customer-thread-message:last-child');
			if (latestMessage) {
				window.requestAnimationFrame(function() {
					window.scrollTo(0, latestMessage.getBoundingClientRect().top + window.pageYOffset - 55);
				});
			}
		}
	});
</script>

<style>
	.customer-thread-main-column,
	.customer-thread-sidebar { min-width: 0; }
	.customer-thread-message-list { max-width: 100%; }
	.customer-thread-message { margin-bottom: 12px; padding: 14px 16px; border: 1px solid #dce4e8; border-radius: 4px; background: #fff; }
	.customer-thread-message-employee { margin-left: 7%; background: #f8f9fa; }
	.customer-thread-message-header { margin-bottom: 10px; }
	.customer-thread-message-header .text-muted { font-size: 11px; font-weight: normal; }
	.customer-thread-message-header .icon-time { margin-left: 5px; }
	.customer-thread-message-text { color: #363a41; font-size: 14px; line-height: 1.55; overflow-wrap: anywhere; }
	.customer-thread-message-text > :last-child { margin-bottom: 0; }
	.customer-message-attachments { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
	.customer-message-attachment { display: flex; width: 82px; height: 74px; padding: 4px; border: 1px solid #d5dce0; border-radius: 4px; background: #fff; color: #555; align-items: center; justify-content: center; text-decoration: none; }
	.customer-message-attachment:hover { border-color: #25b9d7; text-decoration: none; }
	.customer-message-attachment-image img { display: block; width: 100%; height: 100%; object-fit: cover; }
	.customer-message-attachment-file { width: 150px; padding: 7px; justify-content: flex-start; }
	.customer-message-file-icon { margin-right: 8px; flex: 0 0 auto; }
	.customer-message-file-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; }
	.customer-thread-settings-panel .form-group:last-child { margin-bottom: 0; }
	#content.bootstrap .customer-thread-customer-panel .panel-heading { overflow: hidden; text-overflow: ellipsis; text-transform: none; white-space: nowrap; }
	.customer-thread-customer-email { margin-left: 5px; font-weight: normal; }
	.customer-thread-customer-note { margin-top: 0; }
	.customer-thread-customer-note > div { max-height: 90px; margin-top: 4px; overflow: auto; overflow-wrap: anywhere; }
	.customer-thread-customer-note > div > :last-child { margin-bottom: 0; }
	.customer-thread-customer-history { display: flex; margin-top: 12px; padding-top: 10px; border-top: 1px solid #eee; gap: 18px; }
	.customer-thread-customer-history-column { min-width: 0; flex: 1 1 0; }
	.customer-thread-customer-history-item { display: block; padding: 7px 0; border-bottom: 1px solid #eee; text-decoration: none; }
	.customer-thread-customer-history-item:last-child { padding-bottom: 0; border-bottom: 0; }
	.customer-thread-customer-history-item span,
	.customer-thread-customer-history-item small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.customer-thread-context-list { margin: 0; }
	.customer-thread-context-row { padding: 7px 0; border-bottom: 1px solid #eee; }
	.customer-thread-context-row:first-child { padding-top: 0; }
	.customer-thread-context-row dt { color: #777; font-size: 11px; font-weight: normal; }
	.customer-thread-context-row dd { margin: 2px 0 0; overflow-wrap: anywhere; }
	.customer-thread-context-products { margin-top: 12px; }
	.customer-thread-context-product { display: flex; padding: 8px 0; border-top: 1px solid #eee; align-items: center; gap: 8px; }
	.customer-thread-context-product-image { width: 32px; height: 32px; flex: 0 0 32px; }
	.customer-thread-context-product-image img { display: block; width: 100%; height: 100%; object-fit: cover; }
	.customer-thread-context-product-details { min-width: 0; flex: 1 1 auto; }
	.customer-thread-context-product-details strong,
	.customer-thread-context-product-details small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.customer-thread-context-product small { display: block; margin-top: 2px; }
	.customer-thread-context-action { margin-top: 12px; }
	.customer-thread-reply { margin: 18px -20px -20px; padding: 15px 20px 0; border-top: 1px solid #dce4e8; background: #f8f9fa; }
	.customer-thread-reply-tools { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; align-items: center; }
	.customer-thread-template-select { width: 300px; max-width: 100%; }
	.customer-thread-template-select .chosen-container { width: 100% !important; }
	.customer-thread-attach-invoice { margin-left: auto; }
	.customer-thread-reply textarea { min-height: 135px; resize: vertical; }
	.customer-thread-file-upload { margin-top: 12px; }
	.customer-thread-upload-surface { overflow: hidden; border: 1px solid #c7d6db; border-radius: 4px; background: #f8f9fa; transition: border-color .15s, background-color .15s; }
	.customer-thread-upload-surface.file-upload-drag-active { border-color: #25b9d7; background: #eef9fb; }
	.customer-thread-upload-dropzone { display: flex; width: 100%; min-height: 58px; padding: 10px 14px; border: 0; background: transparent; align-items: center; gap: 10px; text-align: left; cursor: pointer; }
	.customer-thread-upload-dropzone small { margin-left: auto; color: #777; }
	.customer-thread-upload-preview { display: flex; flex-wrap: wrap; gap: 10px; padding: 10px 14px; border-top: 1px solid #dce4e8; background: #fff; }
	.customer-thread-upload-item { position: relative; display: flex; width: 78px; height: 78px; padding: 4px; border: 1px solid #ccc; border-radius: 4px; background: #fff; align-items: center; justify-content: center; }
	.customer-thread-upload-item[role="button"] { cursor: pointer; }
	.customer-thread-upload-item[role="button"]:hover { border-color: #25b9d7; }
	.customer-thread-upload-image { display: block; width: 100%; height: 100%; object-fit: cover; }
	.customer-thread-upload-document { flex-direction: column; text-align: center; }
	.customer-thread-upload-name { display: block; width: 100%; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 10px; }
	.customer-thread-upload-remove { position: absolute; top: -8px; right: -8px; width: 22px; height: 22px; padding: 0; border: 1px solid #bbb; border-radius: 50%; background: #fff; color: #555; font-size: 18px; line-height: 18px; }
	.customer-thread-attachment-image { max-height: 75vh; margin: 0 auto; }
	@media (max-width: 1199px) {
		.customer-thread-sidebar { margin-top: 0; }
	}
	@media (max-width: 767px) {
		.customer-thread-message-employee { margin-left: 0; }
		.customer-thread-message-header .pull-right { display: block; float: none !important; margin-top: 3px; }
		.customer-thread-customer-history { display: block; }
		.customer-thread-customer-history-column + .customer-thread-customer-history-column { margin-top: 12px; }
		.customer-thread-upload-dropzone { align-items: flex-start; }
		.customer-thread-upload-dropzone small { display: none; }
	}
</style>
{/block}
