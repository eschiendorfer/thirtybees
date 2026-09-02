<div class="panel customer-thread-conversation">
	<div class="panel-heading">
		<i class="icon-comments"></i> {l s='Messages'}
	</div>

	{if !empty($first_message) || !empty($messages)}
		<div class="customer-thread-message-list">
			{if !empty($first_message) && !$first_message.id_employee}
				{include file="./message.tpl" message=$first_message initial=true}
			{/if}
			{foreach from=$messages item=message}
				{include file="./message.tpl" message=$message initial=false}
			{/foreach}
		</div>
	{/if}

	{include file="./message_form.tpl" message_form=$message_form}
</div>

{include file="./attachment_modal.tpl"}
