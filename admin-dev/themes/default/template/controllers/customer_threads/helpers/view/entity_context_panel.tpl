{if !empty($entity_context)}
	<div class="panel customer-thread-entity-context">
		<div class="panel-heading">
			<i class="icon-info-circle"></i>
			{$entity_context.title|escape:'html':'UTF-8'}
			{if !empty($entity_context.reference)}
				<span class="badge pull-right">{$entity_context.reference|escape:'html':'UTF-8'}</span>
			{/if}
		</div>

		{if !empty($entity_context.fields)}
			<dl class="customer-thread-context-list">
				{foreach from=$entity_context.fields item=context_field}
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
		{/if}

		{if !empty($entity_context.products)}
			<div class="customer-thread-context-products">
				{foreach from=$entity_context.products item=context_product}
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
								{if !empty($entity_context.product_quantity_label)}
									{$entity_context.product_quantity_label|escape:'html':'UTF-8'}:
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

		{if !empty($entity_context.action.url)}
			<a href="{$entity_context.action.url|escape:'html':'UTF-8'}"
			   class="btn btn-default btn-block customer-thread-context-action"
			   target="_blank" rel="noopener noreferrer">
				<i class="icon-external-link"></i> {$entity_context.action.label|escape:'html':'UTF-8'}
			</a>
		{/if}
	</div>
{/if}
