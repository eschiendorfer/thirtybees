<form id="customer_thread_reply_form"
      action="{$message_form.form_action|escape:'html':'UTF-8'}"
      method="post"
      enctype="multipart/form-data"
      class="form-horizontal customer-thread-reply"
      data-can-save-status="{if $message_form.can_save_status}1{else}0{/if}"
      data-has-reply-content="{if $message_form.has_reply_content}1{else}0{/if}"
      data-current-status="{$message_form.current_status|escape:'html':'UTF-8'}"
      data-default-reply-status="closed"
      data-reply-label="{l s='Send reply'}"
      data-status-save-label="{l s='Save status'}">
	<div class="customer-thread-reply-tools">
		<div class="customer-thread-template-select">
			<select class="chosen form-control" name="order_message" id="order_message" onchange="orderOverwriteMessage(this, '{l s='Do you want to overwrite your existing message?' js=1}')">
				<option value="0" selected="selected">{l s='Select response template'}</option>
				{foreach from=$message_form.order_messages item=order_message}
					<option value="{$order_message.message|escape:'html':'UTF-8'}">{$order_message.name|escape:'html':'UTF-8'}</option>
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
			<textarea class="form-control" cols="30" rows="7" id="txt_msg" name="reply_message">{$message_form.message|escape:'html':'UTF-8'}</textarea>

			<div class="customer-thread-file-upload"
				 data-file-upload
				 data-preview-style="bootstrap"
				 data-upload-url="{$message_form.upload.upload_url|escape:'html':'UTF-8'}"
				 data-delete-url="{$message_form.upload.delete_url|escape:'html':'UTF-8'}"
				 data-max-files="{$message_form.upload.max_files|intval}"
				 data-max-total-size="{$message_form.upload.max_total_size|intval}"
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
						<small>{$message_form.upload.help|escape:'html':'UTF-8'}</small>
					</button>
					<div class="customer-thread-upload-preview hidden" data-file-upload-preview></div>
				</div>
				<input type="file" class="hidden" multiple data-file-upload-input>
				<input type="hidden" name="customer_message_attachments" value="{$message_form.upload.value|escape:'html':'UTF-8'}" data-file-upload-value>
				<div class="help-block hidden" data-file-upload-status></div>
				<div class="alert alert-danger hidden" data-file-upload-error></div>
			</div>
		</div>
	</div>

	<div class="form-group customer-thread-reply-actions">
		<div class="col-lg-12">
			<div class="btn-group customer-thread-reply-status" data-status-control>
			<button type="submit"
					class="btn {$message_form.selected_status_option.button_class|escape:'html':'UTF-8'}"
					name="{$message_form.submit_name|escape:'html':'UTF-8'}"
					data-status-button>
				<i class="{if $message_form.has_reply_content || !$message_form.can_save_status}icon-mail-reply{else}icon-save{/if}" data-reply-action-icon></i>
				<span data-reply-action-label>{if $message_form.has_reply_content || !$message_form.can_save_status}{l s='Send reply'}{else}{l s='Save status'}{/if}</span>
				· <span data-selected-status-label>{$message_form.selected_status_option.label|escape:'html':'UTF-8'}</span>
			</button>
			<button type="button"
					class="btn {$message_form.selected_status_option.button_class|escape:'html':'UTF-8'} dropdown-toggle"
					data-toggle="dropdown"
					data-status-button
					aria-haspopup="true"
					aria-expanded="false">
				<span class="caret"></span>
				<span class="sr-only">{l s='Select status'}</span>
			</button>
			<ul class="dropdown-menu dropdown-menu-right">
				{foreach from=$message_form.status_options key=status_value item=status_option}
					<li>
						<a href="#"
						   data-status-choice
						   data-status-value="{$status_value|escape:'html':'UTF-8'}"
						   data-status-label="{$status_option.label|escape:'html':'UTF-8'}"
						   data-status-button-class="{$status_option.button_class|escape:'html':'UTF-8'}">
							<span class="badge {$status_option.badge_class|escape:'html':'UTF-8'} customer-thread-status-swatch">&nbsp;</span>
							{$status_option.label|escape:'html':'UTF-8'}
						</a>
					</li>
				{/foreach}
			</ul>
			<input type="hidden" name="thread_status" data-status-input value="{$message_form.selected_status|escape:'html':'UTF-8'}">
			</div>
		</div>
	</div>
	{foreach from=$message_form.hidden_fields key=field_name item=field_value}
		<input type="hidden" name="{$field_name|escape:'html':'UTF-8'}" value="{$field_value|escape:'html':'UTF-8'}">
	{/foreach}
</form>
