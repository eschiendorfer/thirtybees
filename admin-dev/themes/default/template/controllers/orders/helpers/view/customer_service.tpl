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
            <div class="message-item-text">
              {$message['message_html']}
            </div>
            {foreach from=$message['attachments'] item=attachment}
              <p class="message-item-text">
                <i class="icon-paperclip"></i>
                <a href="{$link->getAdminLink('AdminCustomerThreads', true, ['showMessageAttachment' => $attachment.id_customer_message_attachment])|escape:'htmlall':'UTF-8'}" target="_blank">
                  {$attachment.full_file_name|escape:'html':'UTF-8'}
                </a>
              </p>
            {/foreach}
          </div>
        {/foreach}
      </div>
    </div>
  {/if}
  <div id="messages" class="well hidden-print">
    <form action="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}&amp;token={$smarty.get.token|escape:'html':'UTF-8'}" method="post" enctype="multipart/form-data" onsubmit="if (getE('visibility').checked == true) return confirm('{l s='Do you want to send this message to the customer?'}');">
      <div id="message" class="form-horizontal">
        <div class="form-group">
          <label class="control-label col-lg-3">{l s='Choose a standard message'}</label>
          <div class="col-lg-9">
            <select class="chosen form-control" name="order_message" id="order_message" onchange="orderOverwriteMessage(this, '{l s='Do you want to overwrite your existing message?'}')">
              <option value="0" selected="selected">-</option>
              {foreach from=$orderMessages item=orderMessage}
                <option value="{$orderMessage['message']|escape:'html':'UTF-8'}">{$orderMessage['name']}</option>
              {/foreach}
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-3">{l s='Display to customer?'}</label>
          <div class="col-lg-9">
            <span class="switch prestashop-switch fixed-width-lg">
              <input type="radio" name="visibility" id="visibility_on" value="0" {if $selected_message_visibility == 0}checked="checked"{/if}/>
              <label for="visibility_on">{l s='Yes'}</label>
              <input type="radio" name="visibility" id="visibility_off" value="1" {if $selected_message_visibility == 1}checked="checked"{/if}/>
              <label for="visibility_off">{l s='No'}</label>
              <a class="slide-button btn"></a>
            </span>
          </div>
        </div>

        <div class="form-group">
          <label for="status_msg" class="control-label col-lg-3">{l s='Status'}</label>
          <div class="col-lg-9">
            <select id="status_msg" name="status_msg">
              <option value="open" {if $selected_message_status == 'open'}selected{/if}>{l s='Open'}</option>
              <option value="pending1" {if $selected_message_status == 'pending1'}selected{/if}>{l s='In progress'}</option>
              <option value="waiting_customer" {if $selected_message_status == 'waiting_customer'}selected{/if}>{l s='Waiting for customer reply'}</option>
              <option value="closed" {if $selected_message_status == 'closed'}selected{/if}>{l s='Closed'}</option>
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
          <label class="control-label col-lg-3">{l s='Attach files'}</label>
          <div class="col-lg-9">
            <input type="file" id="file_attachment" name="file_attachment[]" class="form-control" multiple>
            {foreach from=$pending_attachments item=attachment}
              <input type="hidden" name="customer_message_attachment_ids[]" value="{$attachment->id|intval}">
              <p class="help-block"><i class="icon-paperclip"></i> {$attachment->id|intval}-{$attachment->file_name|escape:'html':'UTF-8'}</p>
            {/foreach}
          </div>
        </div>

        <input type="hidden" name="id_order" value="{$order->id}"/>
        <input type="hidden" name="id_customer" value="{$order->id_customer}"/>
        <button type="submit" id="submitMessage" class="btn btn-primary pull-right" name="submitMessage">
          {l s='Send message'}
        </button>
        <div class="clearfix"></div>
      </div>
    </form>
  </div>
</div>
