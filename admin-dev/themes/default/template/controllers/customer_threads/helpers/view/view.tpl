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
<div class="row customer-thread-workspace"
	 data-customer-thread-workspace
	 data-thread-id="{$thread->id|intval}"
	 data-thread-setting-url="{$link->getAdminLink('AdminCustomerThreads')|escape:'html':'UTF-8'}"
	 data-thread-update-error="{l s='The thread could not be updated.'}">
	<div class="col-lg-9 customer-thread-main-column">
		{include file="./conversation_panel.tpl"}
	</div>

	<div class="col-lg-3 customer-thread-sidebar">
		<div class="panel customer-thread-settings-panel">
			<div class="panel-heading">
				<i class="icon-tasks"></i> {l s='Processing'}
			</div>
			<div class="form-group">
				<label for="id_employee_assigned">{l s='Employee'}</label>
				<select class="form-control js-thread-setting" id="id_employee_assigned" data-setting="id_employee_assigned">
					{foreach from=$assignable_employees key=id_employee item=employee_name}
						<option value="{$id_employee|intval}" {if $assigned_employee_id == $id_employee}selected="selected"{/if}>{$employee_name|escape:'html':'UTF-8'}</option>
					{/foreach}
				</select>
			</div>
		</div>

		{include file="./customer_overview_panel.tpl" customer_overview=$customer_overview}
		{include file="./entity_context_panel.tpl" entity_context=$thread_entity_context}
	</div>
</div>
{/block}
