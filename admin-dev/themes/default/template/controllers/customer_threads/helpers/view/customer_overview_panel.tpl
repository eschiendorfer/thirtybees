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
