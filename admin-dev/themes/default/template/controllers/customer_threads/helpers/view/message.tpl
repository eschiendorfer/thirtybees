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

{if !$message.id_employee}
	{assign var="type" value="customer"}
{else}
	{assign var="type" value="employee"}
{/if}

<div class="customer-thread-message customer-thread-message-{$type|escape:'html':'UTF-8'}">
	<div class="customer-thread-message-header clearfix">
		<strong>
			{if $type == 'customer'}
				{if !empty($message.customer_name)}{$message.customer_name|escape:'html':'UTF-8'}{else}{l s='Guest'}{/if}
			{else}
				{$message.employee_name|escape:'html':'UTF-8'}
			{/if}
		</strong>
		<span class="text-muted pull-right">
			<i class="icon-calendar"></i> {dateFormat date=$message.date_add full=0}
			<i class="icon-time"></i> {$message.date_add|substr:11:5}
		</span>
	</div>

	<div class="customer-thread-message-text">{$message.message_html}</div>

	{if !empty($message.attachments)}
		<div class="customer-message-attachments">
			{foreach from=$message.attachments item=attachment}
				{if !empty($attachment.preview_url)}
					<a href="{$attachment.inline_url|escape:'html':'UTF-8'}"
					   class="customer-message-attachment customer-message-attachment-image js-customer-thread-image"
					   target="_blank"
					   data-attachment-name="{$attachment.full_file_name|escape:'html':'UTF-8'}"
					   title="{$attachment.full_file_name|escape:'html':'UTF-8'}">
						<img src="{$attachment.preview_url|escape:'html':'UTF-8'}" alt="{$attachment.full_file_name|escape:'html':'UTF-8'}">
					</a>
				{else}
					<a href="{$attachment.inline_url|escape:'html':'UTF-8'}" class="customer-message-attachment customer-message-attachment-file" target="_blank" title="{$attachment.full_file_name|escape:'html':'UTF-8'}">
						<span class="customer-message-file-icon"><i class="icon-file-text icon-2x"></i></span>
						<span class="customer-message-file-name">{$attachment.full_file_name|escape:'html':'UTF-8'}</span>
					</a>
				{/if}
			{/foreach}
		</div>
	{/if}
</div>
