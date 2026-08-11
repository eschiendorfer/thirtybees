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
<div class="panel">
	<div class="panel-heading">
		<i class="icon-comments"></i>
		{l s="Thread"}: <span class="badge">#{$id_customer_thread|intval}</span>
		{if isset($next_thread) && $next_thread}
			<a class="btn btn-default pull-right" href="{$next_thread.href|escape:'html':'UTF-8'}">
				{$next_thread.name|escape:'htmlall':'UTF-8'} <i class="icon-forward"></i>
			</a>
		{/if}
	</div>
	<div class="well">
		<form action="{$link->getAdminLink('AdminCustomerThreads')|escape:'html':'UTF-8'}&amp;viewcustomer_thread&amp;id_customer_thread={$id_customer_thread|intval}" method="post" enctype="multipart/form-data" class="form-horizontal">
			<div class="row">
				<div class="col-sm-4">
					<div class="form-group">
						<label class="control-label col-sm-4" for="thread_status">{l s='Status'}</label>
						<div class="col-sm-8">
							<select class="form-control" id="thread_status" name="thread_status">
								{foreach from=$thread_statuses key=status_value item=status_label}
									<option value="{$status_value|escape:'html':'UTF-8'}" {if $thread->status == $status_value || ($thread->status == 'pending2' && $status_value == 'pending1')}selected="selected"{/if}>{$status_label|escape:'html':'UTF-8'}</option>
								{/foreach}
							</select>
						</div>
					</div>
				</div>
				<div class="col-sm-4">
					<div class="form-group">
						<label class="control-label col-sm-4" for="id_employee_assigned">{l s='Employee'}</label>
						<div class="col-sm-8">
							<select class="form-control" id="id_employee_assigned" name="id_employee_assigned">
								{foreach from=$assignable_employees key=id_employee item=employee_name}
									<option value="{$id_employee|intval}" {if $thread->id_employee_assigned == $id_employee}selected="selected"{/if}>{$employee_name|escape:'html':'UTF-8'}</option>
								{/foreach}
							</select>
						</div>
					</div>
				</div>
				<div class="col-sm-4">
					<button class="btn btn-primary" name="submitThreadSettings" value="1">
						<i class="icon-save"></i> {l s='Save'}
					</button>
				</div>
			</div>
		</form>
	</div>
	<div class="row">
		<div class="message-item-initial media">
			<a href="{if isset($customer->id)}{$link->getAdminLink('AdminCustomers')|escape:'html':'UTF-8'}&amp;id_customer={$customer->id|intval}&amp;viewcustomer&{else}#{/if}" class="avatar-lg pull-left"><i class="icon-user icon-3x"></i></a>
			<div class="media-body">
				<div class="row">
					<div class="col-sm-6">
					{if isset($customer->firstname)}
						<h2>
							<a href="{$link->getAdminLink('AdminCustomers')|escape:'html':'UTF-8'}&amp;id_customer={$customer->id|intval}&amp;viewcustomer&">
							{$customer->firstname|escape:'html':'UTF-8'} {$customer->lastname|escape:'html':'UTF-8'} <small>({$customer->email|escape:'html':'UTF-8'})</small>
							</a>
						</h2>
					{else}
						<h2>{$thread->email|idnToUtf8|escape:'html':'UTF-8'}</h2>
					{/if}
					{if isset($contact) && trim($contact) != ''}
						<span>{l s="To:"} </span><span class="badge">{$contact|escape:'html':'UTF-8'}</span>
					{/if}
					</div>
					{if isset($customer->firstname)}
						<div class="col-sm-6">
							<p>
							{if $count_ok}
								{l s='[1]%1$d[/1] order(s) validated for a total amount of [2]%2$s[/2]' sprintf=[$count_ok, $total_ok] tags=['<span class="badge">', '<span class="badge badge-success">']}
							{else}
								{l s="No orders validated for the moment"}
							{/if}
							</p>
							<p class="text-muted">{l s="Customer since: %s" sprintf=[{dateFormat date=$customer->date_add full=0}]}</p>
						</div>
					{/if}
				</div>
				<div class="row">
					<div class="col-sm-12">
						{if !$first_message.id_employee}
							{include file="./message.tpl" message=$first_message initial=true}
						{/if}
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		{foreach $messages as $message}
			{include file="./message.tpl" message=$message initial=false}
		{/foreach}
	</div>
</div>
<div class="panel">

	<form id="customer_thread_reply_form" action="{$link->getAdminLink('AdminCustomerThreads')|escape:'html':'UTF-8'}&amp;id_customer_thread={$thread->id|intval}&amp;viewcustomer_thread" method="post" enctype="multipart/form-data" class="form-horizontal">
		<h3>{l s="Your answer to"} {if isset($customer->firstname)}{$customer->firstname|escape:'html':'UTF-8'} {$customer->lastname|escape:'html':'UTF-8'} {else} {$thread->email|idnToUtf8|escape:'htmlall':'UTF-8'}{/if}</h3>
		<div class="form-group">
			<label class="col-lg-12">{l s='Choose a standard message'}</label>
			<div class="col-lg-12">
				<select class="chosen form-control" name="order_message" id="order_message" onchange="orderOverwriteMessage(this, '{l s='Do you want to overwrite your existing message?'}')">
					<option value="0" selected="selected">-</option>
					{foreach from=$orderMessages item=orderMessage}
						<option value="{$orderMessage['message']|escape:'html':'UTF-8'}">{$orderMessage['name']}</option>
					{/foreach}
				</select>
			</div>
		</div>
		<br>
		<div class="row">
			<div class="media">
				<div class="pull-left">
					<span class="avatar-md">{if isset($current_employee->firstname)}<img src="{$current_employee->getImage()|escape:'htmlall':'UTF-8'}" alt="">{/if}</span>
				</div>
				<div class="media-body">
					<textarea cols="30" rows="7" id="txt_msg" name="reply_message">{$PS_CUSTOMER_SERVICE_SIGNATURE|escape:'html':'UTF-8'}</textarea>
					<div class="row" style="margin-top:10px;">
						<div class="col-sm-12 form-inline">
							<label for="file_attachment" class="control-label">{l s='Attach file'}</label>
							<input type="file" id="file_attachment" name="file_attachment" class="form-control">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="panel-footer">
			<!--
			<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
				<i class="icon-magic icon-2x"></i><br>
				{l s="Choose a template"}
			</button>
			-->
			<button class="btn btn-default pull-right" name="submitReply"><i class="process-icon-mail-reply"></i> {l s='Send'}</button>
			<input type="hidden" name="id_customer_thread" value="{$thread->id|intval}" />
			<input type="hidden" name="msg_email" value="{$thread->email|escape:'htmlall':'UTF-8'}" />
			<input type="hidden" id="reply_thread_status" name="thread_status" value="{$thread->status|escape:'html':'UTF-8'}" />
		</div>
	</form>
</div>

{if count($timeline_items)}
<div class="panel">
	<h3>
		<i class="icon-clock-o"></i>
		{l s="Orders and messages timeline"}
	</h3>
	<div class="timeline">
		{foreach $timeline_items as $dates}
			{foreach from=$dates key=date item=timeline_item}
				{include file="controllers/customer_threads/helpers/view/timeline_item.tpl" timeline_item=$timeline_item}
			{/foreach}
		{/foreach}
	</div>
</div>
{/if}
<script type="text/javascript">
		$(document).ready(function(){
			$('#customer_thread_reply_form').on('submit', function() {
				$('#reply_thread_status').val($('#thread_status').val());
			});
		});
</script>

{/block}


