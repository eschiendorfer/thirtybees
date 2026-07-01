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
  <script type="text/javascript">
    var admin_order_tab_link = "{$link->getAdminLink('AdminOrders')|addslashes}";
    var id_order = {$order->id};
    var id_lang = {$current_id_lang};
    var id_currency = {$order->id_currency};
    var id_customer = {$order->id_customer|intval};
    {assign var=PS_TAX_ADDRESS_TYPE value=Configuration::get('PS_TAX_ADDRESS_TYPE')}
    var id_address = {$order->$PS_TAX_ADDRESS_TYPE};
    var currency_sign = "{$currency->sign}";
    var currency_format = "{$currency->format}";
    var currency_blank = "{$currency->blank}";
    var currency_iso_code = "{$currency->iso_code}";
    {if ($currency->decimals)}
      var priceDisplayPrecision = {$smarty.const._PS_PRICE_DISPLAY_PRECISION_};
    {else}
      var priceDisplayPrecision = 0;
    {/if}
    var priceDatabasePrecision = {$smarty.const._TB_PRICE_DATABASE_PRECISION_};
    var use_taxes = {if $order->getTaxCalculationMethod() == $smarty.const.PS_TAX_INC}true{else}false{/if};
    var stock_management = {$stock_management|intval};
    var txt_add_product_stock_issue = "{l s='Are you sure you want to add this quantity?' js=1}";
    var txt_add_product_new_invoice = "{l s='Are you sure you want to create a new invoice?' js=1}";
    var txt_add_product_no_product = "{l s='Error: No product has been selected' js=1}";
    var txt_add_product_no_product_quantity = "{l s='Error: Quantity of products must be set' js=1}";
    var txt_confirm = "{l s='Are you sure?' js=1}";
    var statesShipped = new Array();
    var has_voucher = {if count($discounts)}1{else}0{/if};
    {foreach from=$states item=state}
    {if (isset($currentState->shipped) && !$currentState->shipped && $state['shipped'])}
    statesShipped.push({$state['id_order_state']});
    {/if}
    {/foreach}
    var order_discount_price = {if ($order->getTaxCalculationMethod() == $smarty.const.PS_TAX_EXC)}
            {$order->total_discounts_tax_excl}
            {else}
            {$order->total_discounts_tax_incl}
            {/if};

    var errorRefund = "{l s='Error. You cannot refund a negative amount.'}";
    var originalPaymentRefundConfirmationTemplate = "{l s='Ich bestätige, dass %s über Payrexx an die ursprüngliche Zahlungsmethode zurückerstattet werden.' js=1}";
  </script>
  {assign var="hook_invoice" value={hook h="displayInvoice" id_order=$order->id}}
  {if ($hook_invoice)}
    <div>{$hook_invoice}</div>
  {/if}
  <div class="panel kpi-container">
    <div class="row">
      <div class="col-xs-6 col-sm-3 box-stats color3">
        <div class="kpi-content">
          <i class="icon-calendar-empty"></i>
          <span class="title">{l s='Date'}</span>
          <span class="value">{dateFormat date=$order->date_add full=false}</span>
        </div>
      </div>
      <div class="col-xs-6 col-sm-3 box-stats color4">
        <div class="kpi-content">
          <i class="icon-money"></i>
          <span class="title">{l s='Total'}</span>
          <span class="value">{displayPrice price=$order->total_paid_tax_incl currency=$currency->id}</span>
        </div>
      </div>
      <div class="col-xs-6 col-sm-3 box-stats color2">
        <div class="kpi-content">
          <i class="icon-comments"></i>
          <span class="title">{l s='Messages'}</span>
          <span class="value"><a href="{$link->getAdminLink('AdminCustomerThreads')|escape:'html':'UTF-8'}&amp;id_order={$order->id|intval}">{sizeof($customer_thread_message)}</a></span>
        </div>
      </div>
      <div class="col-xs-6 col-sm-3 box-stats color1">
        <a href="#start_products">
          <div class="kpi-content">
            <i class="icon-book"></i>
            <span class="title">{l s='Products'}</span>
            <span class="value">{sizeof($products)}</span>
          </div>
        </a>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-lg-7">
      <div class="panel">
        <div class="panel-heading">
          <i class="icon-credit-card"></i>
          {l s='Order'}
          <span class="badge">{$order->reference}</span>
          <span class="badge">{l s="#"}{$order->id}</span>
          {if $shop_feature_active|default:false}
            <span class="badge">{$shop_name}</span>
          {/if}
          <div class="panel-heading-action">
            <div class="btn-group">
              <a class="btn btn-default{if !$previousOrder} disabled{/if}" href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$previousOrder|intval}">
                <i class="icon-backward"></i>
              </a>
              <a class="btn btn-default{if !$nextOrder} disabled{/if}" href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$nextOrder|intval}">
                <i class="icon-forward"></i>
              </a>
            </div>
          </div>
        </div>
        <!-- Orders Actions -->
        <div class="well hidden-print">
          <a class="btn btn-default" href="javascript:window.print()">
            <i class="icon-print"></i>
            {l s='Print order'}
          </a>
          &nbsp;
          {if Configuration::get('PS_INVOICE') && $invoices && $order->invoice_number}
            <a data-selenium-id="view_invoice" class="btn btn-default _blank" href="{$link->getAdminLink('AdminPdf')|escape:'html':'UTF-8'}&amp;submitAction=generateInvoicePDF&amp;id_order={$order->id|intval}">
              <i class="icon-file"></i>
              {l s='View invoice'}
            </a>
          {else}
            <span class="span label label-inactive">
							<i class="icon-remove"></i>
              {l s='No invoice'}
						</span>
          {/if}
          &nbsp;
          {if $order->delivery_number}
            <a class="btn btn-default _blank" href="{$link->getAdminLink('AdminPdf')|escape:'html':'UTF-8'}&amp;submitAction=generateDeliverySlipPDF&amp;id_order={$order->id|intval}">
              <i class="icon-truck"></i>
              {l s='View delivery slip'}
            </a>
          {else}
            <span class="span label label-inactive">
							<i class="icon-remove"></i>
              {l s='No delivery slip'}
						</span>
          {/if}
          &nbsp;
          {if $can_edit}
            {assign var=has_order_action_cancel value=false}
            {assign var=has_order_action_return value=false}
            {assign var=has_order_action_service value=false}
            {foreach from=$products item=order_action_product}
              {if isset($order_action_product.order_action_capabilities.cancelable_quantity) && $order_action_product.order_action_capabilities.cancelable_quantity > 0}
                {assign var=has_order_action_cancel value=true}
              {/if}
              {if isset($order_action_product.order_action_capabilities.returnable_quantity) && $order_action_product.order_action_capabilities.returnable_quantity > 0}
                {assign var=has_order_action_return value=true}
              {/if}
              {if isset($order_action_product.order_action_capabilities.serviceable_quantity) && $order_action_product.order_action_capabilities.serviceable_quantity > 0}
                {assign var=has_order_action_service value=true}
              {/if}
            {/foreach}
            {if $has_order_action_cancel}
            <a id="desc-order-action-cancel" class="btn btn-default order-product-action-button" href="#refundForm" onclick="selectOrderProductActionMode('cancel'); return false;">
              <i class="icon-remove"></i>
              {l s='Cancel products'}
            </a>
            &nbsp;
            {/if}
            {if $has_order_action_return}
            <a id="desc-order-action-return" class="btn btn-default order-product-action-button" href="#refundForm" onclick="selectOrderProductActionMode('return'); return false;">
              <i class="icon-mail-reply"></i>
              {l s='Return products'}
            </a>
            &nbsp;
            {/if}
            {if $has_order_action_service}
            <a id="desc-order-action-service" class="btn btn-default order-product-action-button" href="#refundForm" onclick="selectOrderProductActionMode('service'); return false;">
              <i class="icon-wrench"></i>
              {l s='Service case'}
            </a>
            &nbsp;
            {/if}
            {if $order->hasInvoice()}
            <a id="desc-order-credit" class="btn btn-default order-credit-button" href="#refundForm">
              <i class="icon-file-text"></i>
              {l s='Gutschrift'}
            </a>
            {/if}
          {/if}
        </div>
        <!-- Tab nav -->
        <ul class="nav nav-tabs" id="tabOrder">
          <li class="active">
            <a href="#status">
              <i class="icon-time"></i>
              {l s='Status'} <span class="badge">{$history|@count}</span>
            </a>
          </li>
          <li>
            <a href="#documents">
              <i class="icon-file-text"></i>
              {l s='Documents'} <span class="badge">{$order->getDocuments()|@count}</span>
            </a>
          </li>
	  {$HOOK_TAB_ORDER}
        </ul>
        <!-- Tab content -->
        <div class="tab-content panel">
          <!-- Tab status -->
          <div class="tab-pane active" id="status">
            <h4 class="visible-print">{l s='Status'} <span class="badge">({$history|@count})</span></h4>
            <!-- History of status -->
            <div class="table-responsive">
              <table class="table history-status row-margin-bottom">
                <tbody>
                {foreach from=$history item=row key=key}
                  {if ($key == 0)}
                    <tr>
                      <td style="background-color:{$row['color']}"><img src="../img/os/{$row['id_order_state']|intval}.gif" width="16" height="16" alt="{$row['ostate_name']|stripslashes}"/></td>
                      <td style="background-color:{$row['color']};color:{$row['text-color']}">{$row['ostate_name']|stripslashes}</td>
                      <td style="background-color:{$row['color']};color:{$row['text-color']}">{if $row['employee_lastname']}{$row['employee_firstname']|stripslashes} {$row['employee_lastname']|stripslashes}{/if}</td>
                      <td style="background-color:{$row['color']};color:{$row['text-color']}">{dateFormat date=$row['date_add'] full=true}</td>
                      <td style="background-color:{$row['color']};color:{$row['text-color']}" class="text-right">
                        {if $row['send_email']|intval}
                          <a class="btn btn-default" href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$order->id|intval}&amp;sendStateEmail={$row['id_order_state']|intval}&amp;id_order_history={$row['id_order_history']|intval}" title="{l s='Resend this email to the customer'}">
                            <i class="icon-mail-reply"></i>
                            {l s='Resend email'}
                          </a>
                        {/if}
                      </td>
                    </tr>
                  {else}
                    <tr>
                      <td><img src="../img/os/{$row['id_order_state']|intval}.gif" width="16" height="16"/></td>
                      <td>{$row['ostate_name']|stripslashes}</td>
                      <td>{if $row['employee_lastname']}{$row['employee_firstname']|stripslashes} {$row['employee_lastname']|stripslashes}{else}&nbsp;{/if}</td>
                      <td>{dateFormat date=$row['date_add'] full=true}</td>
                      <td class="text-right">
                        {if $row['send_email']|intval}
                          <a class="btn btn-default" href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$order->id|intval}&amp;sendStateEmail={$row['id_order_state']|intval}&amp;id_order_history={$row['id_order_history']|intval}" title="{l s='Resend this email to the customer'}">
                            <i class="icon-mail-reply"></i>
                            {l s='Resend email'}
                          </a>
                        {/if}
                      </td>
                    </tr>
                  {/if}
                {/foreach}
                </tbody>
              </table>
            </div>
            <!-- Change status form -->
            <form action="{$currentIndex|escape:'html':'UTF-8'}&amp;vieworder&amp;token={$smarty.get.token}" method="post" class="form-horizontal well hidden-print">
              <div class="row">
                <div class="col-lg-9">
                  <select id="id_order_state" class="chosen form-control" name="id_order_state">
                    {foreach from=$states item=state}
                      <option value="{$state['id_order_state']|intval}"{if isset($currentState) && $state['id_order_state'] == $currentState->id} selected="selected" disabled="disabled"{/if}>{$state['name']|escape}</option>
                    {/foreach}
                  </select>
                  <input type="hidden" name="id_order" value="{$order->id}"/>
                </div>
                <div class="col-lg-3">
                  <button type="submit" name="submitState" class="btn btn-primary">
                    {l s='Update status'}
                  </button>
                </div>
              </div>
            </form>
          </div>
          <!-- Tab documents -->
          <div class="tab-pane" id="documents">
            <h4 class="visible-print">{l s='Documents'} <span class="badge">({$orderDocuments|count})</span></h4>
            {* Include document template *}
            {include file='controllers/orders/_documents.tpl' orderDocuments=$orderDocuments}
          </div>
	  <!-- Additional tabs from modules -->
	  {$HOOK_CONTENT_ORDER}
        </div>
        <script>
          $('#tabOrder a').click(function (e) {
            e.preventDefault()
            $(this).tab('show')
          })
        </script>
        <hr/>
        <!-- Tab nav -->
        <ul class="nav nav-tabs" id="myTab">
          {$HOOK_TAB_SHIP}
          <li class="active">
            <a href="#shipping">
              <i class="icon-truck "></i>
              {l s='Shipping'} <span class="badge">{$order->getShipping()|@count}</span>
            </a>
          </li>
          <li>
            <a href="#returns">
              <i class="icon-undo"></i>
              {l s='Merchandise Returns'} <span class="badge">{$order->getReturn()|@count}</span>
            </a>
          </li>
        </ul>
        <!-- Tab content -->
        <div class="tab-content panel">
          {$HOOK_CONTENT_SHIP}
          <!-- Tab shipping -->
          <div class="tab-pane active" id="shipping">
            <h4 class="visible-print">{l s='Shipping'} <span class="badge">({$order->getShipping()|@count})</span></h4>
            <!-- Shipping block -->
            {if !$order->isVirtual()}
              <form method="post" class="form-horizontal">
                {if $order->gift_message}
                  <div class="form-group">
                    <label class="control-label col-lg-3">{l s='Message'}</label>
                    <div class="col-lg-9">
                      <p class="form-control-static">{$order->gift_message|nl2br}</p>
                    </div>
                  </div>
                {/if}
                {include file='controllers/orders/_shipping.tpl'}
                {if $carrierModuleCall}
                  {$carrierModuleCall}
                {/if}
                <hr/>
                {if $order->recyclable}
                  <span class="label label-success"><i class="icon-check"></i> {l s='Recycled packaging'}</span>
                {else}
                  <span class="label label-inactive"><i class="icon-remove"></i> {l s='Recycled packaging'}</span>
                {/if}

                {if $order->gift}
                  <span class="label label-success"><i class="icon-check"></i> {l s='Gift wrapping'}</span>
                {else}
                  <span class="label label-inactive"><i class="icon-remove"></i> {l s='Gift wrapping'}</span>
                {/if}
              </form>
            {/if}
          </div>
          <!-- Tab returns -->
          <div class="tab-pane" id="returns">
            <h4 class="visible-print">{l s='Merchandise Returns'} <span class="badge">({$order->getReturn()|@count})</span></h4>
            {if !$order->isVirtual()}
              <!-- Return block -->
              {if $order->getReturn()|count > 0}
                <div class="table-responsive">
                  <table class="table">
                    <thead>
                    <tr>
                      <th><span class="title_box ">{l s='Date'}</span></th>
                      <th><span class="title_box ">{l s='Type'}</span></th>
                      <th><span class="title_box ">{l s='Carrier'}</span></th>
                      <th><span class="title_box ">{l s='Tracking number'}</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach from=$order->getReturn() item=line}
                      <tr>
                        <td>{$line.date_add}</td>
                        <td>{l s=$line.type}</td>
                        <td>{$line.state_name}</td>
                        <td class="actions">
                          <span class="shipping_number_show">{if isset($line.url) && isset($line.tracking_number)}<a href="{$line.url|replace:'@':$line.tracking_number|escape:'html':'UTF-8'}">{$line.tracking_number}</a>{elseif isset($line.tracking_number)}{$line.tracking_number}{/if}</span>
                          {if $line.can_edit}
                            <form method="post" action="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$order->id|intval}&amp;id_order_invoice={if $line.id_order_invoice}{$line.id_order_invoice|intval}{else}0{/if}&amp;id_carrier={if $line.id_carrier}{$line.id_carrier|escape:'html':'UTF-8'}{else}0{/if}">
													<span class="shipping_number_edit" style="display:none;">
														<button type="button" name="tracking_number">
															{$line.tracking_number|htmlentities}
														</button>
														<button type="submit" class="btn btn-default" name="submitShippingNumber">
															{l s='Update'}
														</button>
													</span>
                              <button href="#" class="edit_shipping_number_link">
                                <i class="icon-pencil"></i>
                                {l s='Edit'}
                              </button>
                              <button href="#" class="cancel_shipping_number_link" style="display: none;">
                                <i class="icon-remove"></i>
                                {l s='Cancel'}
                              </button>
                            </form>
                          {/if}
                        </td>
                      </tr>
                    {/foreach}
                    </tbody>
                  </table>
                </div>
              {else}
                <div class="list-empty hidden-print">
                  <div class="list-empty-msg">
                    <i class="icon-warning-sign list-empty-icon"></i>
                    {l s='No merchandise returned yet'}
                  </div>
                </div>
              {/if}
              {if $carrierModuleCall}
                {$carrierModuleCall}
              {/if}
            {/if}
          </div>
        </div>
        <script>
          $('#myTab a').click(function (e) {
            e.preventDefault()
            $(this).tab('show')
          })
        </script>
      </div>
      <!-- Payments block -->
      <div id="formAddPaymentPanel" class="panel">
        <div class="panel-heading">
          <i class="icon-money"></i>
          {l s="Payment"} <span class="badge">{$order->getOrderPayments()|@count}</span>
        </div>
        {if count($order->getOrderPayments()) > 0}
          <p class="alert alert-danger"{if Tools::ps_round($orders_total_paid_tax_incl, $smarty.const._PS_PRICE_DISPLAY_PRECISION_) == Tools::ps_round($total_paid, $smarty.const._PS_PRICE_DISPLAY_PRECISION_) || (isset($currentState) && $currentState->id == Configuration::get('PS_OS_CANCELED'))} style="display: none;"{/if}>
            {l s='Warning'}
            <strong>{displayPrice price=$total_paid currency=$currency->id}</strong>
            {l s='paid instead of'}
            <strong class="total_paid">{displayPrice price=$orders_total_paid_tax_incl currency=$currency->id}</strong>
            {foreach $order->getBrother() as $brother_order}
              {if $brother_order@first}
                {if count($order->getBrother()) == 1}
                  <br/>
                  {l s='This warning also concerns order '}
                {else}
                  <br/>
                  {l s='This warning also concerns the next orders:'}
                {/if}
              {/if}
              <a href="{$current_index}&amp;vieworder&amp;id_order={$brother_order->id}&amp;token={$smarty.get.token|escape:'html':'UTF-8'}">
                #{'%06d'|sprintf:$brother_order->id}
              </a>
            {/foreach}
          </p>
        {/if}
        <form id="formAddPayment" method="post" action="{$current_index}&amp;vieworder&amp;id_order={$order->id}&amp;token={$smarty.get.token|escape:'html':'UTF-8'}">
          <div class="table-responsive">
            <table class="table">
              <thead>
              <tr>
                <th><span class="title_box ">{l s='Date'}</span></th>
                <th><span class="title_box ">{l s='Payment method'}</span></th>
                <th><span class="title_box ">{l s='Transaction ID'}</span></th>
                <th><span class="title_box ">{l s='Amount'}</span></th>
                <th><span class="title_box ">{l s='Invoice'}</span></th>
                <th></th>
              </tr>
              </thead>
              <tbody>
              {foreach from=$order->getOrderPaymentCollection() item=payment}
                <tr>
                  <td>{dateFormat date=$payment->date_add full=true}</td>
                  <td>{$payment->payment_method|escape:'html':'UTF-8'}</td>
                  <td>
                    {if isset($store_credit_payment_links[$payment->id])}
                      <a href="{$store_credit_payment_links[$payment->id].url|escape:'html':'UTF-8'}">{$store_credit_payment_links[$payment->id].id_store_credit_transaction|intval}</a>
                    {else}
                      {$payment->transaction_id|escape:'html':'UTF-8'}
                    {/if}
                  </td>
                  <td>{displayPrice price=$payment->amount currency=$payment->id_currency}</td>
                  <td>
                    {if $invoice = $payment->getOrderInvoice($order->id)}
                      {$invoice->getInvoiceNumberFormatted($current_id_lang, $order->id_shop)}
                    {else}
                    {/if}
                  </td>
                  <td class="actions">
                    <button class="btn btn-default open_payment_information">
                      <i class="icon-search"></i>
                      {l s='Details'}
                    </button>
                  </td>
                </tr>
                <tr class="payment_information" style="display: none;">
                  <td colspan="5">
                    <p>
                      <b>{l s='Card Number'}</b>&nbsp;
                      {if $payment->card_number}
                        {$payment->card_number}
                      {else}
                        <i>{l s='Not defined'}</i>
                      {/if}
                    </p>
                    <p>
                      <b>{l s='Card Brand'}</b>&nbsp;
                      {if $payment->card_brand}
                        {$payment->card_brand}
                      {else}
                        <i>{l s='Not defined'}</i>
                      {/if}
                    </p>
                    <p>
                      <b>{l s='Card Expiration'}</b>&nbsp;
                      {if $payment->card_expiration}
                        {$payment->card_expiration}
                      {else}
                        <i>{l s='Not defined'}</i>
                      {/if}
                    </p>
                    <p>
                      <b>{l s='Card Holder'}</b>&nbsp;
                      {if $payment->card_holder}
                        {$payment->card_holder}
                      {else}
                        <i>{l s='Not defined'}</i>
                      {/if}
                    </p>
                  </td>
                </tr>
                {foreachelse}
                <tr>
                  <td class="list-empty hidden-print" colspan="6">
                    <div class="list-empty-msg">
                      <i class="icon-warning-sign list-empty-icon"></i>
                      {l s='No payment methods are available'}
                    </div>
                  </td>
                </tr>
              {/foreach}
              <tr class="current-edit hidden-print">
                <td>
                  <div class="input-group fixed-width-xl">
                    <input type="text" name="payment_date" class="datepicker" value="{date('Y-m-d')}"/>
                    <div class="input-group-addon">
                      <i class="icon-calendar-o"></i>
                    </div>
                  </div>
                </td>
                <td>
                  <input name="payment_method" list="payment_method" class="payment_method">
                  <datalist id="payment_method">
                    {foreach from=$payment_methods item=payment_method}
                    <option value="{$payment_method}">
                      {/foreach}
                  </datalist>
                </td>
                <td>
                  <input type="text" name="payment_transaction_id" value="" class="form-control fixed-width-sm"/>
                </td>
                <td>
                  <input type="text" name="payment_amount" value="" class="form-control fixed-width-sm pull-left"/>
                  <select name="payment_currency" class="payment_currency form-control fixed-width-xs pull-left">
                    {foreach from=$currencies item=current_currency}
                      <option value="{$current_currency['id_currency']}"{if $current_currency['id_currency'] == $currency->id} selected="selected"{/if}>{$current_currency['sign']}</option>
                    {/foreach}
                  </select>
                </td>
                <td>
                  {if $invoices}
                    <select name="payment_invoice" id="payment_invoice">
                      {foreach from=$invoices item=invoice}
                        <option value="{$invoice.id}" selected="selected">{$invoice.name}</option>
                      {/foreach}
                    </select>
                  {/if}
                </td>
                <td class="actions">
                  <button class="btn btn-primary" type="submit" name="submitAddPayment">
                    {l s='Add'}
                  </button>
                </td>
              </tr>
              {if $can_edit && isset($store_credit_max_applicable_tax_incl) && $store_credit_max_applicable_tax_incl > 0}
              <tr class="hidden-print">
                <td></td>
                <td><strong>{l s='Apply store credit'}</strong></td>
                <td></td>
                <td>
                  <input type="text" name="store_credit_amount" value="{$store_credit_max_applicable_tax_incl|string_format:'%.2f'}" class="form-control fixed-width-sm pull-left"/>
                  <p class="help-block" style="margin-bottom: 0;">
                    {l s='Available'}: {displayPrice price=$store_credit_available_tax_incl currency=$currency->id}
                  </p>
                </td>
                <td></td>
                <td class="actions">
                  <button class="btn btn-default" type="submit" name="submitApplyStoreCredit">
                    {l s='Apply'}
                  </button>
                </td>
              </tr>
              {/if}
              </tbody>
            </table>
          </div>
        </form>
        {if (!$order->valid && sizeof($currencies) > 1)}
          <form class="form-horizontal well" method="post" action="{$currentIndex|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$order->id}&amp;token={$smarty.get.token|escape:'html':'UTF-8'}">
            <div class="row">
              <label class="control-label col-lg-3">{l s='Change currency'}</label>
              <div class="col-lg-6">
                <select name="new_currency">
                  {foreach from=$currencies item=currency_change}
                    {if $currency_change['id_currency'] != $order->id_currency}
                      <option value="{$currency_change['id_currency']}">{$currency_change['name']} - {$currency_change['sign']}</option>
                    {/if}
                  {/foreach}
                </select>
                <p class="help-block">{l s='Do not forget to update your exchange rate before making this change.'}</p>
              </div>
              <div class="col-lg-3">
                <button type="submit" class="btn btn-default" name="submitChangeCurrency"><i class="icon-refresh"></i> {l s='Change'}</button>
              </div>
            </div>
          </form>
        {/if}
      </div>
      {hook h="displayAdminOrderLeft" id_order=$order->id}
    </div>
    <div class="col-lg-5">
      <!-- Customer informations -->
      <div class="panel">
        {if $customer->id}
          <div class="panel-heading">
            <i class="icon-user"></i>
            {l s='Customer'}
            <span class="badge">
							<a href="?tab=AdminCustomers&amp;id_customer={$customer->id}&amp;viewcustomer&amp;token={getAdminToken tab='AdminCustomers'}">
								{if Configuration::get('PS_B2B_ENABLE') && $customer->company}{$customer->company} - {/if}
                {$gender->name|default:''|escape:'html':'UTF-8'}
                {$customer->firstname}
                {$customer->lastname}
							</a>
						</span>
            <span class="badge">
							{l s='#'}{$customer->id}
						</span>
          </div>
          <div class="row">
            <div class="col-xs-6">
              {if ($customer->isGuest())}
                {l s='This order has been placed by a guest.'}
                {if (!Customer::customerExists($customer->email))}
                  <form method="post" action="index.php?tab=AdminCustomers&amp;id_customer={$customer->id}&amp;id_order={$order->id|intval}&amp;token={getAdminToken tab='AdminCustomers'}">
                    <input type="hidden" name="id_lang" value="{$order->id_lang}"/>
                    <input class="btn btn-default" type="submit" name="submitGuestToCustomer" value="{l s='Transform a guest into a customer'}"/>
                    <p class="help-block">{l s='This feature will generate a random password and send an email to the customer.'}</p>
                  </form>
                {else}
                  <div class="alert alert-warning">
                    {l s='A registered customer account has already claimed this email address'}
                  </div>
                {/if}
              {else}
                <dl class="well list-detail">
                  <dt>{l s='Email'}</dt>
                  <dd><a href="mailto:{$customer->email}"><i class="icon-envelope-o"></i> {$customer->email|idnToUtf8}</a></dd>
                  <dt>{l s='Account registered'}</dt>
                  <dd class="text-muted"><i class="icon-calendar-o"></i> {dateFormat date=$customer->date_add full=true}</dd>
                  <dt>{l s='Valid orders placed'}</dt>
                  <dd><span class="badge">{$customerStats['nb_orders']|intval}</span></dd>
                  <dt>{l s='Total spent since registration'}</dt>
                  <dd><span class="badge badge-success">{displayPrice price=Tools::convertPrice($customerStats['total_orders'], $currency) currency=$currency->id}</span></dd>
                  {if Configuration::get('PS_B2B_ENABLE')}
                    <dt>{l s='Siret'}</dt>
                    <dd>{$customer->siret}</dd>
                    <dt>{l s='APE'}</dt>
                    <dd>{$customer->ape}</dd>
                  {/if}
                </dl>
              {/if}
            </div>

            <div class="col-xs-6">
              <div class="form-group hidden-print">
                <a href="?tab=AdminCustomers&amp;id_customer={$customer->id}&amp;viewcustomer&amp;token={getAdminToken tab='AdminCustomers'}" class="btn btn-default btn-block">{l s='View full details...'}</a>
              </div>
              <div class="panel panel-sm">
                <div class="panel-heading">
                  <i class="icon-eye-slash"></i>
                  {l s='Private note'}
                </div>
                <form id="customer_note" class="form-horizontal" action="ajax.php" method="post" onsubmit="saveCustomerNote({$customer->id});return false;">
                  <div class="form-group">
                    <div class="col-lg-12">
                      <textarea name="note" id="noteContent" class="textarea-autosize" onkeyup="$(this).val().length > 0 ? $('#submitCustomerNote').removeAttr('disabled') : $('#submitCustomerNote').attr('disabled', 'disabled')">{$customer->note}</textarea>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-lg-12">
                      <button type="submit" id="submitCustomerNote" class="btn btn-default pull-right" disabled="disabled">
                        <i class="icon-save"></i>
                        {l s='Save'}
                      </button>
                    </div>
                  </div>
                  <span id="note_feedback"></span>
                </form>
              </div>
            </div>
          </div>
        {/if}
        <!-- Tab nav -->
        <div class="row">
          <ul class="nav nav-tabs" id="tabAddresses">
            <li class="active">
              <a href="#addressShipping">
                <i class="icon-truck"></i>
                {l s='Shipping address'}
              </a>
            </li>
            <li>
              <a href="#addressInvoice">
                <i class="icon-file-text"></i>
                {l s='Invoice address'}
              </a>
            </li>
          </ul>
          <!-- Tab content -->
          <div class="tab-content panel">
            <!-- Tab status -->
            <div class="tab-pane  in active" id="addressShipping">
              <!-- Addresses -->
              <h4 class="visible-print">{l s='Shipping address'}</h4>
              {if !$order->isVirtual()}
                <!-- Shipping address -->
                {if $can_edit}
                  <form class="form-horizontal hidden-print" method="post" action="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$order->id|intval}">
                    <div class="form-group">
                      <div class="col-lg-9">
                        <select name="id_address">
                          {foreach from=$customer_addresses item=address}
                            <option value="{$address['id_address']}"
                                    {if $address['id_address'] == $order->id_address_delivery}
                              selected="selected"
                                    {/if}>
                              {$address['alias']} -
                              {$address['address1']}
                              {$address['postcode']}
                              {$address['city']}
                              {if !empty($address['state'])}
                                {$address['state']}
                              {/if},
                              {$address['country']}
                            </option>
                          {/foreach}
                        </select>
                      </div>
                      <div class="col-lg-3">
                        <button class="btn btn-default" type="submit" name="submitAddressShipping"><i class="icon-refresh"></i> {l s='Change'}</button>
                      </div>
                    </div>
                  </form>
                {/if}
                <div class="well">
                  <div class="row">
                    <div class="col-sm-6">
                      {capture assign=addressDeliveryText}{displayAddressDetail address=$addresses.delivery newLine='\n'}{/capture}
                      <a class="btn btn-default pull-right" style="margin-left: 5px" onclick="copyToClipboard('{$addressDeliveryText|escape:'html':'UTF-8'}')">
                        <i class="icon-copy"></i>
                        {l s='Copy'}
                      </a>
                      <a class="btn btn-default pull-right" href="?tab=AdminAddresses&amp;id_address={$addresses.delivery->id}&amp;addaddress&amp;realedit=1&amp;id_order={$order->id}&amp;address_type=1&amp;token={getAdminToken tab='AdminAddresses'}&amp;back={$smarty.server.REQUEST_URI|urlencode}">
                        <i class="icon-pencil"></i>
                        {l s='Edit'}
                      </a>

                      <div onclick="copyHighlightedTextInDiv(event, true);" data-placement="left" data-content="{l s='Copied!'}" data-trigger="manual">
                      {displayAddressDetail address=$addresses.delivery newLine='</div><div onclick="copyHighlightedTextInDiv(event, true);" data-placement="left" data-content="Copied!" data-trigger="manual">'}
                      </div>

                      {if $addresses.delivery->other}
                        <hr/>
                        {$addresses.delivery->other}
                        <br/>
                      {/if}
                    </div>
                    <div class="col-sm-6 hidden-print">
                      <div id="map-delivery-canvas" style="height: 190px"></div>
                    </div>
                  </div>
                </div>
              {/if}
            </div>
            <div class="tab-pane " id="addressInvoice">
              <!-- Invoice address -->
              <h4 class="visible-print">{l s='Invoice address'}</h4>
              {if $can_edit}
                <form class="form-horizontal hidden-print" method="post" action="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$order->id|intval}">
                  <div class="form-group">
                    <div class="col-lg-9">
                      <select name="id_address">
                        {foreach from=$customer_addresses item=address}
                          <option value="{$address['id_address']}"
                                  {if $address['id_address'] == $order->id_address_invoice}
                            selected="selected"
                                  {/if}>
                            {$address['alias']} -
                            {$address['address1']}
                            {$address['postcode']}
                            {$address['city']}
                            {if !empty($address['state'])}
                              {$address['state']}
                            {/if},
                            {$address['country']}
                          </option>
                        {/foreach}
                      </select>
                    </div>
                    <div class="col-lg-3">
                      <button class="btn btn-default" type="submit" name="submitAddressInvoice"><i class="icon-refresh"></i> {l s='Change'}</button>
                    </div>
                  </div>
                </form>
              {/if}
              <div class="well">
                <div class="row">
                  <div class="col-sm-6">
                    {capture assign=addressInvoiceText}{displayAddressDetail address=$addresses.invoice newLine='\n'}{/capture}
                    <a class="btn btn-default pull-right" style="margin-left: 5px" onclick="copyToClipboard('{$addressInvoiceText|escape:'html':'UTF-8'}');">
                      <i class="icon-copy"></i>
                      {l s='Copy'}
                    </a>
                    <a class="btn btn-default pull-right" href="?tab=AdminAddresses&amp;id_address={$addresses.invoice->id}&amp;addaddress&amp;realedit=1&amp;id_order={$order->id}&amp;address_type=2&amp;back={$smarty.server.REQUEST_URI|urlencode}&amp;token={getAdminToken tab='AdminAddresses'}">
                      <i class="icon-pencil"></i>
                      {l s='Edit'}
                    </a>

                    <div onclick="copyHighlightedTextInDiv(event, true);" data-placement="left" data-content="{l s='Copied!'}" data-trigger="manual">
                    {displayAddressDetail address=$addresses.invoice newLine='</div><div onclick="copyHighlightedTextInDiv(event, true);" data-placement="left" data-content="Copied!" data-trigger="manual">'}
                    </div>

                    {if $addresses.invoice->other}
                      <hr/>
                      {$addresses.invoice->other}
                      <br/>
                    {/if}
                  </div>
                  <div class="col-sm-6 hidden-print">
                    <div id="map-invoice-canvas" style="height: 190px"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <script>
          $('#tabAddresses a').click(function (e) {
            e.preventDefault()
            $(this).tab('show')
          })
        </script>
      </div>
      <div class="panel">
        <div class="panel-heading">
          <i class="icon-envelope"></i> {l s='Messages'} <span class="badge">{sizeof($customer_thread_message)}</span>
        </div>
        {if (sizeof($messages))}
          <div class="panel panel-highlighted">
            <div class="message-item">
              {foreach from=$messages item=message}
                <div class="message-avatar">
                  <div class="avatar-md">
                    <i class="icon-user icon-2x"></i>
                  </div>
                </div>
                <div class="message-body">

									<span class="message-date">&nbsp;<i class="icon-calendar"></i>
                    {dateFormat date=$message['date_add']} -
									</span>
                  <h4 class="message-item-heading">
                    {if ($message['elastname']|escape:'html':'UTF-8')}{$message['efirstname']|escape:'html':'UTF-8'}
                      {$message['elastname']|escape:'html':'UTF-8'}{else}{$message['cfirstname']|escape:'html':'UTF-8'} {$message['clastname']|escape:'html':'UTF-8'}
                    {/if}
                    {if ($message['private'] == 1)}
                      <span class="badge badge-info">{l s='Private'}</span>
                    {/if}
                  </h4>
                  <p class="message-item-text">
                    {$message['message']|escape:'html':'UTF-8'|nl2br}
                  </p>
                </div>
                {*if ($message['is_new_for_me'])}
                  <a class="new_message" title="{l s='Mark this message as \'viewed\''}" href="{$smarty.server.REQUEST_URI}&amp;token={$smarty.get.token}&amp;messageReaded={$message['id_message']}">
                    <i class="icon-ok"></i>
                  </a>
                {/if*}
              {/foreach}
            </div>
          </div>
        {/if}
        <div id="messages" class="hidden-print panel panel-sm">
          <form action="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}&amp;token={$smarty.get.token|escape:'html':'UTF-8'}" method="post" enctype="multipart/form-data" onsubmit="if (getE('visibility').checked == true) return confirm('{l s='Do you want to send this message to the customer?'}');" class="form-horizontal">
            <div id="message">
              <div class="form-group">
                <label class="control-label col-lg-3">{l s='Choose a standard message'}</label>
                <div class="col-lg-9">
                  <select class="chosen form-control" name="order_message" id="order_message" onchange="orderOverwriteMessage(this, '{l s='Do you want to overwrite your existing message?'}')">
                    <option value="0" selected="selected">-</option>
                    {foreach from=$orderMessages item=orderMessage}
                      <option value="{$orderMessage['message']|escape:'html':'UTF-8'}">{$orderMessage['name']}</option>
                    {/foreach}
                  </select>
                  <p class="help-block">
                    <a href="{$link->getAdminLink('AdminOrderMessage')|escape:'html':'UTF-8'}">
                      {l s='Configure predefined messages'}
                      <i class="icon-external-link"></i>
                    </a>
                  </p>
                </div>
              </div>

              <div class="form-group">
                <label class="control-label col-lg-3">{l s='Display to customer?'}</label>
                <div class="col-lg-9">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="visibility" id="visibility_on" value="0"/>
                        <label for="visibility_on">
                            {l s='Yes'}
                        </label>
                        <input type="radio" name="visibility" id="visibility_off" value="1" checked="checked"/>
                        <label for="visibility_off">
                            {l s='No'}
                        </label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
              </div>

              <div class="form-group">
                <label for="status_msg" class="control-label col-lg-3">{l s='Status'}</label>
                <div class="col-lg-9">
                  <select id="status_msg" name="status_msg">
                    <option value="open" {if isset($customer_thread_message[0]['status']) && $customer_thread_message[0]['status']=='open'}selected{/if}>{l s='Open'}</option>
                    <option value="closed" {if isset($customer_thread_message[0]['status']) && $customer_thread_message[0]['status']=='closed'}selected{/if}>{l s='Closed'}</option>
                    <option value="pending1" {if isset($customer_thread_message[0]['status']) && $customer_thread_message[0]['status']=='pending1'}selected{/if}>{l s='Pending 1'}</option>
                    <option value="pending2" {if isset($customer_thread_message[0]['status']) && $customer_thread_message[0]['status']=='pending2'}selected{/if}>{l s='Pending 2'}</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label class="control-label col-lg-3">{l s='Message'}</label>
                <div class="col-lg-9">
                  <textarea id="txt_msg" class="textarea-autosize" name="message">{Tools::getValue('message')|escape:'html':'UTF-8'}</textarea>
                  <p id="nbchars"></p>
                </div>
              </div>

              <div class="form-group">
                <label class="control-label col-lg-3">{l s='Attach file'}</label>
                <div class="col-lg-9">
                  <input type="file" id="file_attachment" name="file_attachment" class="form-control">
                </div>
              </div>

              <input type="hidden" name="id_order" value="{$order->id}"/>
              <input type="hidden" name="id_customer" value="{$order->id_customer}"/>
              <button type="submit" id="submitMessage" class="btn btn-primary pull-right" name="submitMessage">
                {l s='Send message'}
              </button>
              <a class="btn btn-default" href="{$link->getAdminLink('AdminCustomerThreads')|escape:'html':'UTF-8'}&amp;id_order={$order->id|intval}">
                {l s='Show all messages'}
                <i class="icon-external-link"></i>
              </a>
            </div>
          </form>
        </div>
      </div>
      {hook h="displayAdminOrderRight" id_order=$order->id}
    </div>
  </div>
  {hook h="displayAdminOrder" id_order=$order->id}
  <div class="row" id="start_products">
    <div class="col-lg-12">
      <form class="container-command-top-spacing" action="{$current_index}&amp;vieworder&amp;token={$smarty.get.token|escape:'html':'UTF-8'}&amp;id_order={$order->id|intval}" method="post" onsubmit="return orderDeleteProduct('{l s='This product cannot be returned.'}', '{l s='Quantity to cancel is greater than quantity available.'}');">
        <input type="hidden" name="id_order" value="{$order->id}"/>
        <div style="display: none">
          <input type="hidden" value="{$order->getWarehouseList()|implode}" id="warehouse_list"/>
        </div>

        <div class="panel">
          <div class="panel-heading">
            <i class="icon-shopping-cart"></i>
            {l s='Products'} <span class="badge">{$products|@count}</span>
          </div>
          <div id="refundForm">
            <div id="order_product_action_form" class="form-horizontal row-margin-top" style="display:none;">
              <input type="hidden" id="order_product_action_type" name="order_product_action_type" value="" disabled="disabled" />
              <div class="form-group">
                <div class="col-lg-12">
                  <button type="submit" name="submitOrderProductAction" class="btn btn-default">
                    <i class="icon-check"></i>
                    <span id="order_product_action_submit_label">{l s='Apply order action'}</span>
                  </button>
                </div>
              </div>
            </div>
            <div id="credit_reason_form" class="partial_refund_fields form-horizontal row-margin-top" style="display:none;">
              <div class="form-group">
                <label class="control-label col-lg-2" for="reason_entity_type">
                  <span class="label-tooltip" data-toggle="tooltip" title="{l s='Create Cancellation, Return, or Service case first to select them here.'}">
                    {l s='Reason'}
                  </span>
                </label>
                <div class="col-lg-3">
                  <select id="reason_entity_type" name="reason_entity_type" class="form-control" disabled="disabled" required="required">
                    <option value="" selected="selected" disabled="disabled">{l s='Please select'}</option>
                    <option value="order_return" {if empty($credit_order_return_options)}disabled="disabled"{/if}>{l s='Return'}</option>
                    <option value="cancellation" {if empty($cancellation_credit_available)}disabled="disabled"{/if}>{l s='Cancellation'}</option>
                    <option value="service_case" disabled="disabled">{l s='Service case'}</option>
                    <option value="manual">{l s='Other reason'}</option>
                  </select>
                </div>
              </div>
              <div class="form-group credit-reason-entity-group">
                <label class="control-label col-lg-2" for="reason_id_entity">{l s='Reference'}</label>
                <div class="col-lg-3">
                  <select id="reason_id_entity" name="reason_id_entity" class="form-control" disabled="disabled">
                    <option value="" disabled="disabled">{l s='Please select'}</option>
                    {if !empty($cancellation_credit_available)}
                      <option value="{$order->id|intval}" data-reason-type="cancellation">{$order->reference|escape:'html':'UTF-8'} - {l s='Cancellation'}</option>
                    {/if}
                    {foreach from=$credit_order_return_options item=credit_order_return}
                      <option value="{$credit_order_return.id_order_return|intval}" data-reason-type="order_return">{$credit_order_return.label|escape:'html':'UTF-8'}</option>
                    {/foreach}
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="control-label col-lg-2" for="credit_refund_method">
                  <span class="label-tooltip" data-toggle="tooltip" title="{l s='Select whether this credit creates a payout, store credit, or only the slip.'}">
                    {l s='Refund method'}
                  </span>
                </label>
                <div class="col-lg-3">
                  <select id="credit_refund_method" name="order_product_refund_method" class="form-control" disabled="disabled" required="required">
                    <option value="" selected="selected" disabled="disabled">{l s='Please select'}</option>
                    <option value="none">{l s='No payout'}</option>
                    <option value="store_credit">{l s='Store Credit'}</option>
                    <option value="original_payment" {if empty($original_payment_refund_available)}disabled="disabled"{/if}>{if !empty($original_payment_refund_label)}{$original_payment_refund_label|escape:'html':'UTF-8'}{else}{l s='Original payment method'}{/if}</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
          <script type="text/javascript">
            var orderCreditSuggestions = JSON.parse('{$credit_suggestions_json|escape:'javascript':'UTF-8'}');

            function selectOrderProductActionMode(action) {
              var submitLabels = {
                'cancel': '{l s='Apply cancellation'}',
                'return': '{l s='Apply return'}',
                'service': '{l s='Apply service case'}'
              };

              $('.order-product-action-button').removeClass('btn-primary').addClass('btn-default');
              $('#desc-order-action-' + action).removeClass('btn-default').addClass('btn-primary');

              $('.partial_refund_fields, .order_product_action_fields').css('display', 'none');
              $('.partial_refund_fields :input, .order_product_action_fields :input').prop('disabled', true);
              var $orderActionCells = $('#orderProducts td.order_product_action_fields');
              $('#orderProducts th.order_product_action_fields').css('display', 'table-cell');
              $orderActionCells.css('display', 'table-cell');
              $orderActionCells.find(':input').prop('disabled', false);
              $('.order-product-action-limit').hide();
              $('.order-product-action-limit-' + action).show();
              $('#order_product_action_product_header').text('{l s='Quantity'}');

              if ($('#order_product_action_type').val() !== action) {
                $('input.order-product-action-quantity-input').val('0');
              }

              var $orderProductActionForm = $('#order_product_action_form');
              $orderProductActionForm.insertAfter($('#orderProducts').closest('.table-responsive'));
              $orderProductActionForm.show();
              $orderProductActionForm.find(':input').prop('disabled', false);
              $('#order_product_action_type').val(action);
              $('#order_product_action_submit_label').text(submitLabels[action]);

              $('.order-product-action-quantity').removeClass('col-lg-4').addClass('col-lg-12');

              $orderActionCells.find('input.order-product-action-quantity-input').each(function () {
                var $quantityInput = $(this);
                var $actionCell = $quantityInput.closest('td.order_product_action_fields');
                var availableQuantity = parseInt($quantityInput.attr('data-' + action + 'able-quantity'), 10);

                if (isNaN(availableQuantity)) {
                  availableQuantity = parseInt(
                    $actionCell.find('.order-product-action-limit-' + action).text().replace(/[^0-9]/g, ''),
                    10
                  );
                }

                var hasLimit = !isNaN(availableQuantity);
                var isAvailable = !hasLimit || availableQuantity > 0;

                $quantityInput.prop('disabled', !isAvailable);
                if (!isAvailable) {
                  $quantityInput.val('0');
                }
                $actionCell.toggleClass('text-muted', !isAvailable);
              });

              if (typeof scrollToOrderProductsPanel === 'function') {
                scrollToOrderProductsPanel();
              } else if ($('#start_products').length) {
                $('html, body').animate({
                  scrollTop: Math.max(0, $('#start_products').offset().top - 110)
                }, 250);
              }
            }
          </script>

          {capture "TaxMethod"}
            {if ($order->getTaxCalculationMethod() == $smarty.const.PS_TAX_EXC)}
              {l s='tax excluded.'}
            {else}
              {l s='tax included.'}
            {/if}
          {/capture}
          {if ($order->getTaxCalculationMethod() == $smarty.const.PS_TAX_EXC)}
            <input type="hidden" name="TaxMethod" value="0">
          {else}
            <input type="hidden" name="TaxMethod" value="1">
          {/if}
          <div class="table-responsive">
            <table class="table" id="orderProducts">
              <thead>
              <tr>
                <th></th>
                <th><span class="title_box ">{l s='Product'}</span></th>
                {hook h='displayOrderHeaderExtra' order=$order}
                {if ($order->getTaxCalculationMethod() != $smarty.const.PS_TAX_EXC)}
                  <th>
                    <span class="title_box ">{l s='Unit Price'}</span>
                    <small class="text-muted">{l s='tax excluded.'}</small>
                  </th>
                {/if}
                <th>
                  <span class="title_box ">{l s='Unit Price'}</span>
                  <small class="text-muted">{$smarty.capture.TaxMethod}</small>
                </th>
                <th class="text-center"><span class="title_box ">{l s='Qty'}</span></th>
                {if $display_warehouse}
                  <th><span class="title_box ">{l s='Warehouse'}</span></th>
                {/if}
                <th class="text-center"><span class="title_box ">{l s='Refunded'}</span></th>
                {if ($order->hasBeenDelivered() || $order->hasProductReturned())}
                  <th class="text-center"><span class="title_box ">{l s='Returned'}</span></th>
                {/if}
                {if $stock_management}
                  <th class="text-center"><span class="title_box ">{l s='Available quantity'}</span></th>
                {/if}
                <th>
                  <span class="title_box ">{l s='Total'}</span>
                  <small class="text-muted">{$smarty.capture.TaxMethod}</small>
                </th>
                <th style="display: none;" class="add_product_fields"></th>
                <th style="display: none;" class="edit_product_fields"></th>
                <th style="display:none" class="partial_refund_fields">
                  <span class="title_box ">{l s='Gutschrift'}</span>
                </th>
                <th style="display:none" class="order_product_action_fields">
                  <span class="title_box " id="order_product_action_product_header">{l s='Quantity'}</span>
                </th>
                {if $order->canEditProducts()}
                  <th></th>
                {/if}
              </tr>
              </thead>
              <tbody>
              {foreach from=$products item=product key=k}
                {* Include customized datas partial *}
                {include file='controllers/orders/_customized_data.tpl'}
                {* Include product line partial *}
                {include file='controllers/orders/_product_line.tpl'}
              {/foreach}
              {if ($can_edit && $order->canEditProducts())}
                {include file='controllers/orders/_new_product.tpl'}
              {/if}
              </tbody>
            </table>
          </div>

          {if $can_edit}
            <div class="row-margin-bottom row-margin-top order_action">
              {if $order->canEditProducts()}
                <button type="button" id="add_product" class="btn btn-default">
                  <i class="icon-plus-sign"></i>
                  {l s='Add a product'}
                </button>
              {/if}
              <button id="add_voucher" class="btn btn-default" type="button">
                <i class="icon-ticket"></i>
                {l s='Add a new discount'}
              </button>
            </div>
          {/if}
          <div class="clear">&nbsp;</div>
          <div class="row">
            <div class="col-xs-6">
              <div class="alert alert-warning">
                {l s='For this customer group, prices are displayed as: [1]%s[/1]' sprintf=[$smarty.capture.TaxMethod] tags=['<strong>']}
                {if !Configuration::get('PS_ORDER_RETURN')}
                  <br/>
                  <strong>{l s='Merchandise returns are disabled'}</strong>
                {/if}
              </div>
            </div>
            <div class="col-xs-6">
              <div class="panel panel-vouchers" style="{if !sizeof($discounts)}display:none;{/if}">
                {if (sizeof($discounts) || $can_edit)}
                  <div class="table-responsive">
                    <table class="table">
                      <thead>
                      <tr>
                        <th>
													<span class="title_box ">
														{l s='Discount name'}
													</span>
                        </th>
                        <th>
													<span class="title_box ">
														{l s='Value'}
													</span>
                        </th>
                        {if $can_edit}
                          <th></th>
                        {/if}
                      </tr>
                      </thead>
                      <tbody>
                      {foreach from=$discounts item=discount}
                        <tr>
                          <td>{$discount['name']}</td>
                          <td>
                            {if $discount['value'] != 0.00}
                              -
                            {/if}
                            {displayPrice price=$discount['value'] currency=$currency->id}
                          </td>
                          {if $can_edit}
                            <td>
                              <a href="{$current_index}&amp;submitDeleteVoucher&amp;id_order_cart_rule={$discount['id_order_cart_rule']}&amp;id_order={$order->id}&amp;token={$smarty.get.token|escape:'html':'UTF-8'}">
                                <i class="icon-minus-sign"></i>
                                {l s='Delete voucher'}
                              </a>
                            </td>
                          {/if}
                        </tr>
                      {/foreach}
                      </tbody>
                    </table>
                  </div>
                  <div class="current-edit" id="voucher_form" style="display:none;">
                    {include file='controllers/orders/_discount_form.tpl'}
                  </div>
                {/if}
              </div>
              <div class="panel panel-total">
                <div class="table-responsive">
                  <table class="table">
                    {* Assign order price *}
                    {if ($order->getTaxCalculationMethod() == $smarty.const.PS_TAX_EXC)}
                      {assign var=order_product_price value=($order->total_products)}
                      {assign var=order_discount_price value=$order->total_discounts_tax_excl}
                      {assign var=order_wrapping_price value=$order->total_wrapping_tax_excl}
                      {assign var=order_shipping_price value=$order->total_shipping_tax_excl}
                    {else}
                      {assign var=order_product_price value=$order->total_products_wt}
                      {assign var=order_discount_price value=$order->total_discounts_tax_incl}
                      {assign var=order_wrapping_price value=$order->total_wrapping_tax_incl}
                      {assign var=order_shipping_price value=$order->total_shipping_tax_incl}
                    {/if}
                    <tr id="total_products">
                      <td class="text-right">{l s='Products:'}</td>
                      <td class="amount text-right nowrap">
                        {displayPrice price=$order_product_price currency=$currency->id}
                      </td>
                      <td class="partial_refund_fields current-edit" style="display:none;"></td>
                    </tr>
                    <tr id="total_discounts" {if $order->total_discounts_tax_incl == 0}style="display: none;"{/if}>
                      <td class="text-right">{l s='Discounts'}</td>
                      <td class="amount text-right nowrap">
                        -{displayPrice price=$order_discount_price currency=$currency->id}
                      </td>
                      <td class="partial_refund_fields current-edit" style="display:none;"></td>
                    </tr>
                    <tr id="total_wrapping" {if $order->total_wrapping_tax_incl == 0}style="display: none;"{/if}>
                      <td class="text-right">{l s='Wrapping'}</td>
                      <td class="amount text-right nowrap">
                        {displayPrice price=$order_wrapping_price currency=$currency->id}
                      </td>
                      <td class="partial_refund_fields current-edit" style="display:none;"></td>
                    </tr>
                    <tr id="total_shipping">
                      <td class="text-right">{l s='Shipping'}</td>
                      <td class="amount text-right nowrap">
                        {displayPrice price=$order_shipping_price currency=$currency->id}
                      </td>
                      <td class="partial_refund_fields current-edit" style="display:none;"></td>
                    </tr>
                    {if ($order->getTaxCalculationMethod() == $smarty.const.PS_TAX_EXC)}
                      <tr id="total_taxes">
                        <td class="text-right">{l s='Taxes'}</td>
                        <td class="amount text-right nowrap">{displayPrice price=($order->total_paid_tax_incl-$order->total_paid_tax_excl) currency=$currency->id}</td>
                        <td class="partial_refund_fields current-edit" style="display:none;"></td>
                      </tr>
                    {/if}
                    {assign var=order_total_price value=$order->total_paid_tax_incl}
                    <tr id="total_order">
                      <td class="text-right"><strong>{l s='Total'}</strong></td>
                      <td class="amount text-right nowrap">
                        <strong>{displayPrice price=$order_total_price currency=$currency->id}</strong>
                      </td>
                      <td class="partial_refund_fields current-edit" style="display:none;"></td>
                    </tr>
                    <tr id="total_store_credit" {if !isset($store_credit_used_tax_incl) || $store_credit_used_tax_incl <= 0}style="display: none;"{/if}>
                      <td class="text-right">{l s='Store Credit'}</td>
                      <td class="amount text-right nowrap">
                        -{displayPrice price=$store_credit_used_tax_incl currency=$currency->id}
                      </td>
                      <td class="partial_refund_fields current-edit" style="display:none;"></td>
                    </tr>
                    <tr id="total_outstanding_invoice_amount" {if !isset($store_credit_used_tax_incl) || $store_credit_used_tax_incl <= 0 || !isset($outstanding_invoice_amount_tax_incl)}style="display: none;"{/if}>
                      <td class="text-right"><strong>{l s='Amount Due'}</strong></td>
                      <td class="amount text-right nowrap">
                        <strong>{displayPrice price=$outstanding_invoice_amount_tax_incl currency=$currency->id}</strong>
                      </td>
                      <td class="partial_refund_fields current-edit" style="display:none;"></td>
                    </tr>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div style="display:none;" class="partial_refund_fields">
            <div class="form-horizontal">
              <div class="form-group">
                <div class="col-lg-7">
                  <table class="table" id="credit_totals">
                    <tbody>
                      <tr>
                        <td>{l s='Products'}</td>
                        <td class="text-right" id="credit_products_total_display">0.00</td>
                      </tr>
                      <tr>
                        <td>
                          <span class="label-tooltip" data-toggle="tooltip" title="{l s='Deduct the proportional discount from a percentage voucher.'}">
                            {l s='Cart rule adjustment'}
                          </span>
                        </td>
                        <td class="text-right">
                          <div style="white-space: nowrap;">
                            <div class="input-group" style="width: 95px; display: inline-table;">
                              <input type="text" id="credit_cart_rule_adjustment_rate" class="form-control credit-adjustment-rate-input text-right" value="0" disabled="disabled" />
                              <div class="input-group-addon">%</div>
                            </div>
                            <div class="input-group" style="width: 160px; display: inline-table; margin-left: 5px;">
                              <div class="input-group-addon">- {$currency->prefix}{$currency->suffix}</div>
                              <input type="text" id="credit_cart_rule_adjustment" name="credit_cart_rule_adjustment" class="form-control credit-adjustment-amount-input text-right" value="0" disabled="disabled" />
                            </div>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td>{l s='Shipping'}</td>
                        <td class="text-right">
                          <div class="input-group" style="width: 160px; margin-left: auto;">
                            <div class="input-group-addon">{$currency->prefix}{$currency->suffix}</div>
                            <input type="text" name="partialRefundShippingCost" class="form-control text-right" value="0" disabled="disabled" />
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td>{l s='Subtotal'}</td>
                        <td class="text-right" id="credit_subtotal_display">0.00</td>
                      </tr>
                      <tr>
                        <td>
                          <span class="label-tooltip" data-toggle="tooltip" title="{l s='Deduct cancellation payment fees or handling fees.'}">
                            {l s='Fee adjustment'}
                          </span>
                        </td>
                        <td class="text-right">
                          <div style="white-space: nowrap;">
                            <div class="input-group" style="width: 95px; display: inline-table;">
                              <input type="text" id="credit_fee_adjustment_rate" class="form-control credit-adjustment-rate-input text-right" value="0" disabled="disabled" />
                              <div class="input-group-addon">%</div>
                            </div>
                            <div class="input-group" style="width: 160px; display: inline-table; margin-left: 5px;">
                              <div class="input-group-addon">- {$currency->prefix}{$currency->suffix}</div>
                              <input type="text" id="credit_fee_adjustment" name="credit_fee_adjustment" class="form-control credit-adjustment-amount-input text-right" value="0" disabled="disabled" />
                            </div>
                          </div>
                        </td>
                      </tr>
                      <tr class="success">
                        <td><strong>{l s='Credit total'}</strong></td>
                        <td class="text-right"><strong id="credit_total_display">0.00</strong></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="form-group">
                <div class="col-lg-10">
                  <div id="original_payment_refund_confirmation" class="checkbox" style="display:none; margin-bottom: 12px;">
                    <label for="confirm_original_payment_refund">
                      <input type="checkbox" id="confirm_original_payment_refund" name="confirm_original_payment_refund" value="1" disabled="disabled" />
                      <span id="original_payment_refund_confirmation_text"></span>
                    </label>
                  </div>
                  <button type="submit" id="partial_refund_submit" name="partialRefund" class="btn btn-default">
                    <i class="icon-check"></i> {l s='Gutschrift erstellen'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="row">
    <div class="col-lg-12">
      <!-- Sources block -->
      {if (sizeof($sources))}
        <div class="panel">
          <div class="panel-heading">
            <i class="icon-globe"></i>
            {l s='Sources'} <span class="badge">{$sources|@count}</span>
          </div>
          <ul {if sizeof($sources) > 3}style="height: 200px; overflow-y: scroll;"{/if}>
            {foreach from=$sources item=source}
              <li>
                {dateFormat date=$source['date_add'] full=true}<br/>
                <b>{l s='From'}</b>{if $source['http_referer'] != ''}<a href="{$source['http_referer']}">{parse_url($source['http_referer'], $smarty.const.PHP_URL_HOST)|regex_replace:'/^www./':''}</a>{else}-{/if}<br/>
                <b>{l s='To'}</b> <a href="http://{$source['request_uri']}">{$source['request_uri']|truncate:100:'...'}</a><br/>
                {if $source['keywords']}<b>{l s='Keywords'}</b> {$source['keywords']}<br/>{/if}<br/>
              </li>
            {/foreach}
          </ul>
        </div>
      {/if}

      <!-- linked orders block -->
      {if count($order->getBrother()) > 0}
        <div class="panel">
          <div class="panel-heading">
            <i class="icon-cart"></i>
            {l s='Linked orders'}
          </div>
          <div class="table-responsive">
            <table class="table">
              <thead>
              <tr>
                <th>
                  {l s='Order no. '}
                </th>
                <th>
                  {l s='Status'}
                </th>
                <th>
                  {l s='Amount'}
                </th>
                <th></th>
              </tr>
              </thead>
              <tbody>
              {foreach $order->getBrother() as $brother_order}
                <tr>
                  <td>
                    <a href="{$current_index}&amp;vieworder&amp;id_order={$brother_order->id}&amp;token={$smarty.get.token|escape:'html':'UTF-8'}">#{$brother_order->id}</a>
                  </td>
                  <td>
                    {$brother_order->getCurrentOrderState()->name[$current_id_lang]}
                  </td>
                  <td>
                    {displayPrice price=$brother_order->total_paid_tax_incl currency=$currency->id}
                  </td>
                  <td>
                    <a href="{$current_index}&amp;vieworder&amp;id_order={$brother_order->id}&amp;token={$smarty.get.token|escape:'html':'UTF-8'}">
                      <i class="icon-eye-open"></i>
                      {l s='See the order'}
                    </a>
                  </td>
                </tr>
              {/foreach}
              </tbody>
            </table>
          </div>
        </div>
      {/if}
    </div>
  </div>
  <script type="text/javascript">
    var delivery_map, invoice_map;

    $(document).ready(function () {
      $(".textarea-autosize").autosize();

      if (window['google'] && google.maps) {
        var geocoder = new google.maps.Geocoder();

        geocoder.geocode({
          address: '{$addresses.delivery->address1|@addcslashes:'\''},{$addresses.delivery->postcode|@addcslashes:'\''},{$addresses.delivery->city|@addcslashes:'\''}{if isset($addresses.deliveryState->name) && $addresses.delivery->id_state},{$addresses.deliveryState->name|@addcslashes:'\''}{/if},{$addresses.delivery->country|@addcslashes:'\''}'
        }, function (results, status) {
          if (status === google.maps.GeocoderStatus.OK) {
            delivery_map = new google.maps.Map(document.getElementById('map-delivery-canvas'), {
              zoom: 10,
              mapTypeId: google.maps.MapTypeId.ROADMAP,
              center: results[0].geometry.location
            });
            var delivery_marker = new google.maps.Marker({
              map: delivery_map,
              position: results[0].geometry.location,
              url: 'https://maps.google.com?q={$addresses.delivery->address1|urlencode},{$addresses.delivery->postcode|urlencode},{$addresses.delivery->city|urlencode}{if isset($addresses.deliveryState->name) && $addresses.delivery->id_state},{$addresses.deliveryState->name|urlencode}{/if},{$addresses.delivery->country|urlencode}'
            });
            google.maps.event.addListener(delivery_marker, 'click', function () {
              window.open(delivery_marker.url);
            });
          }
        });

        geocoder.geocode({
          address: '{$addresses.invoice->address1|@addcslashes:'\''},{$addresses.invoice->postcode|@addcslashes:'\''},{$addresses.invoice->city|@addcslashes:'\''}{if isset($addresses.deliveryState->name) && $addresses.invoice->id_state},{$addresses.deliveryState->name|@addcslashes:'\''}{/if},{$addresses.invoice->country|@addcslashes:'\''}'
        }, function (results, status) {
          if (status === google.maps.GeocoderStatus.OK) {
            invoice_map = new google.maps.Map(document.getElementById('map-invoice-canvas'), {
              zoom: 10,
              mapTypeId: google.maps.MapTypeId.ROADMAP,
              center: results[0].geometry.location
            });
            invoice_marker = new google.maps.Marker({
              map: invoice_map,
              position: results[0].geometry.location,
              url: 'https://maps.google.com?q={$addresses.invoice->address1|urlencode},{$addresses.invoice->postcode|urlencode},{$addresses.invoice->city|urlencode}{if isset($addresses.deliveryState->name) && $addresses.invoice->id_state},{$addresses.deliveryState->name|urlencode}{/if},{$addresses.invoice->country|urlencode}'
            });
            google.maps.event.addListener(invoice_marker, 'click', function () {
              window.open(invoice_marker.url);
            });
          }
        });
      }

      $('.datepicker').datetimepicker({
        prevText: '',
        nextText: '',
        dateFormat: 'yy-mm-dd',
        // Define a custom regional settings in order to use PrestaShop translation tools
        currentText: '{l s='Now' js=1}',
        closeText: '{l s='Done' js=1}',
        ampm: false,
        amNames: ['AM', 'A'],
        pmNames: ['PM', 'P'],
        timeFormat: 'hh:mm:ss tt',
        timeSuffix: '',
        timeOnlyTitle: '{l s='Choose Time' js=1}',
        timeText: '{l s='Time' js=1}',
        hourText: '{l s='Hour' js=1}',
        minuteText: '{l s='Minute' js=1}'
      });
    });

    // Fix wrong maps center when map is hidden
    $('#tabAddresses').click(function () {
      if (delivery_map) {
        x = delivery_map.getZoom();
        c = delivery_map.getCenter();
        google.maps.event.trigger(delivery_map, 'resize');
        delivery_map.setZoom(x);
        delivery_map.setCenter(c);
      }

      if (invoice_map) {
        x = invoice_map.getZoom();
        c = invoice_map.getCenter();
        google.maps.event.trigger(invoice_map, 'resize');
        invoice_map.setZoom(x);
        invoice_map.setCenter(c);
      }
    });
  </script>
{/block}
