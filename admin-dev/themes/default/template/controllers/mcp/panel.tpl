{* MCP administration panel rendered by AdminMcpController. *}
{if $mcp_generated_token}
  <div class="alert alert-warning">
    <strong>{l s='Diesen Token jetzt kopieren.'}</strong> {l s='Er kann später nicht erneut angezeigt werden.'}
    <div class="input-group" style="margin-top: 10px; max-width: 900px;">
      <label for="mcp-generated-token" class="sr-only">{l s='Neu erstellter MCP-Token'}</label>
      <input id="mcp-generated-token" type="text" class="form-control" readonly="readonly" autocomplete="off" spellcheck="false" value="{$mcp_generated_token|escape:'html':'UTF-8'}">
      <span class="input-group-btn">
        <button type="button" class="btn btn-default" onclick="navigator.clipboard.writeText(document.getElementById('mcp-generated-token').value);">
          <i class="icon-copy"></i> {l s='Kopieren'}
        </button>
      </span>
    </div>
  </div>
{/if}

<div class="panel">
  <div class="panel-heading">
    <i class="icon-key"></i> {l s='Mitarbeiter-Token'}
  </div>
  <form method="post" action="{$mcp_action_url|escape:'html':'UTF-8'}" class="form-horizontal">
    <div class="form-group">
      <label for="mcp-id-employee" class="control-label col-lg-3">{l s='Mitarbeiter'}</label>
      <div class="col-lg-6">
        <select id="mcp-id-employee" name="id_employee" class="form-control" required="required">
          {foreach $mcp_employees as $employee}
            <option value="{$employee.id_employee|intval}">{$employee.firstname|escape:'html':'UTF-8'} {$employee.lastname|escape:'html':'UTF-8'}</option>
          {/foreach}
        </select>
      </div>
    </div>
    <div class="form-group">
      <label for="mcp-token-name" class="control-label col-lg-3">{l s='Bezeichnung'}</label>
      <div class="col-lg-6">
        <input id="mcp-token-name" type="text" name="token_name" maxlength="100" required="required" placeholder="{l s='Zum Beispiel: Emanuel – Codex'}">
        <p class="help-block">{l s='Ein Token pro Mitarbeiter genügt.'}</p>
      </div>
    </div>
    <div class="panel-footer">
      <button type="submit" name="submitCreateMcpToken" class="btn btn-default pull-right">
        <i class="process-icon-new"></i> {l s='Token erstellen'}
      </button>
    </div>
  </form>

  <div class="table-responsive-row clearfix">
    <table class="table">
      <thead>
        <tr>
          <th>{l s='Mitarbeiter'}</th>
          <th>{l s='Bezeichnung'}</th>
          <th>{l s='Token'}</th>
          <th>{l s='Erstellt'}</th>
          <th>{l s='Zuletzt verwendet'}</th>
          <th>{l s='Status'}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        {foreach $mcp_tokens as $mcp_token}
          <tr>
            <td>{$mcp_token.firstname|escape:'html':'UTF-8'} {$mcp_token.lastname|escape:'html':'UTF-8'}</td>
            <td>{$mcp_token.name|escape:'html':'UTF-8'}</td>
            <td><code>{$mcp_token.token_prefix|escape:'html':'UTF-8'}...</code></td>
            <td>{$mcp_token.date_add|escape:'html':'UTF-8'}</td>
            <td>{if $mcp_token.last_used_at}{$mcp_token.last_used_at|escape:'html':'UTF-8'}{else}-{/if}</td>
            <td>
              {if $mcp_token.active && $mcp_token.employee_active}
                <span class="label label-success">{l s='Aktiv'}</span>
              {else}
                <span class="label label-default">{l s='Gesperrt'}</span>
              {/if}
            </td>
            <td class="text-right">
              {if $mcp_token.active}
                <form method="post" action="{$mcp_action_url|escape:'html':'UTF-8'}" style="display:inline">
                  <input type="hidden" name="id_mcp_token" value="{$mcp_token.id_mcp_token|intval}">
                  <button type="submit" name="submitRevokeMcpToken" class="btn btn-default" onclick="return confirm('{l s='Diesen MCP-Token wirklich sperren?' js=1}');">
                    <i class="icon-ban-circle"></i> {l s='Sperren'}
                  </button>
                </form>
              {/if}
            </td>
          </tr>
        {foreachelse}
          <tr><td colspan="7" class="text-center">{l s='Noch keine MCP-Token vorhanden.'}</td></tr>
        {/foreach}
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel-heading">
    <i class="icon-list"></i> {l s='Letzte MCP-Aktivitäten'}
    <span class="badge">{l s='letzte 100'}</span>
  </div>
  <div class="table-responsive-row clearfix">
    <table class="table">
      <thead>
        <tr>
          <th>{l s='Datum'}</th>
          <th>{l s='Mitarbeiter'}</th>
          <th>{l s='Token'}</th>
          <th>{l s='Werkzeug'}</th>
          <th>{l s='Ergebnis'}</th>
          <th>{l s='Dauer'}</th>
          <th>{l s='Treffer'}</th>
        </tr>
      </thead>
      <tbody>
        {foreach $mcp_audit_logs as $mcp_log}
          <tr>
            <td>{$mcp_log.date_add|escape:'html':'UTF-8'}</td>
            <td>{$mcp_log.firstname|escape:'html':'UTF-8'} {$mcp_log.lastname|escape:'html':'UTF-8'}</td>
            <td>{if $mcp_log.token_name}{$mcp_log.token_name|escape:'html':'UTF-8'}{else}{l s='Alter Token'}{/if}{if $mcp_log.token_prefix} <code>{$mcp_log.token_prefix|escape:'html':'UTF-8'}...</code>{/if}</td>
            <td><code>{$mcp_log.tool_name|escape:'html':'UTF-8'}</code></td>
            <td>{if $mcp_log.success}<span class="label label-success">{l s='Erfolgreich'}</span>{else}<span class="label label-danger">{l s='Fehler'}</span>{/if}</td>
            <td>{$mcp_log.duration_ms|intval} ms</td>
            <td>{if isset($mcp_log.result_count)}{$mcp_log.result_count|intval}{else}-{/if}</td>
          </tr>
        {foreachelse}
          <tr><td colspan="7" class="text-center">{l s='Noch keine MCP-Werkzeugaufrufe aufgezeichnet.'}</td></tr>
        {/foreach}
      </tbody>
    </table>
  </div>
</div>
