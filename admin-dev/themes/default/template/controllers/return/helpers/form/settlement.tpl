<div class="panel" id="return-settlement">
    <div class="panel-heading"><i class="icon-money"></i> Gutschrift und Rückzahlung</div>
    {assign var='awaiting_quantities' value=(!$return_settlement.can_settle && !$return_settlement.slips && !$return_settlement.errors && !$return_completed && !$return_settlement.payment_unresolved)}
    {foreach $return_settlement.errors as $error}<div class="alert alert-warning">{$error|escape:'html':'UTF-8'}</div>{/foreach}
    {if $return_settlement.payment_unresolved}
        <div class="alert alert-warning">Die Rückzahlung läuft oder muss geprüft werden. Bitte die Zahlung in der Bestellung kontrollieren; keine zweite Auszahlung auslösen.</div>
    {elseif $return_completed}
        <div class="alert alert-success">Die Rücksendung ist abgeschlossen.</div>
    {elseif $awaiting_quantities}
        <div class="alert alert-warning">Bitte zuerst die erhaltenen Mengen prüfen und speichern.</div>
    {/if}
    {if $return_settlement.waiting_package && !$awaiting_quantities}<p class="text-muted">Es fehlen noch angemeldete Artikel. Die Rücksendung bleibt bis zur vollständigen Bearbeitung offen.</p>{/if}

    {if $return_settlement.breakdown && !$awaiting_quantities}
        {assign var='credit' value=$return_settlement.breakdown}
        {assign var='balance' value=$return_settlement.balance}
        <div class="return-financial-summary">
            <table class="table return-summary">
                <tbody>
                    <tr><td>Ursprünglicher Rechnungsbetrag</td><td>{displayPrice price=$balance.invoice_total currency=$return_currency->id}</td></tr>
                    <tr><td>Vom Kunden bezahlt</td><td>{displayPrice price=$balance.paid currency=$return_currency->id}</td></tr>
                    {if $balance.credits > 0}<tr><td>Bisherige Gutschriften</td><td>− {displayPrice price=$balance.credits currency=$return_currency->id}</td></tr>{/if}
                    {if $credit.is_new}<tr><td>Neue Gutschrift</td><td>− {displayPrice price=$return_settlement.credit currency=$return_currency->id}</td></tr>{/if}
                    <tr><td>Rechnungsbetrag nach Gutschriften</td><td>{displayPrice price=($balance.claim - $return_settlement.credit) currency=$return_currency->id}</td></tr>
                    {if $balance.refunded > 0}<tr><td>Bereits zurückgezahlt / als Guthaben gebucht</td><td>{displayPrice price=$balance.refunded currency=$return_currency->id}</td></tr>{/if}
                    {if $balance.reserved > 0 && ($return_settlement.payout > 0 || $return_settlement.remaining > 0)}<tr><td>Rückzahlung ausstehend / ungeklärt</td><td>{displayPrice price=$balance.reserved currency=$return_currency->id}</td></tr>{/if}
                    {if $return_settlement.payout > 0}
                        <tr class="return-total"><td>Jetzt zurückzuzahlen</td><td>{displayPrice price=$return_settlement.payout currency=$return_currency->id}</td></tr>
                    {elseif $return_settlement.remaining > 0}
                        <tr class="return-total"><td>Noch vom Kunden zu bezahlen</td><td>{displayPrice price=$return_settlement.remaining currency=$return_currency->id}</td></tr>
                    {elseif $return_settlement.payment_unresolved || $balance.reserved > 0}
                        <tr class="return-total"><td>Rückzahlung ausstehend / ungeklärt</td><td>{displayPrice price=$balance.reserved currency=$return_currency->id}</td></tr>
                    {elseif $balance.claim - $return_settlement.credit <= 0}
                        <tr class="return-total"><td>Rechnung vollständig gutgeschrieben</td><td>{displayPrice price=0 currency=$return_currency->id}</td></tr>
                    {else}
                        <tr class="return-total"><td>Keine Zahlung mehr offen</td><td>{displayPrice price=0 currency=$return_currency->id}</td></tr>
                    {/if}
                </tbody>
            </table>
        </div>
    {/if}

    {if !$awaiting_quantities}
    <span class="hidden" id="return-payout-amount">{displayPrice price=$return_settlement.payout currency=$return_currency->id}</span>
    <div class="return-actions">
        {if $return_settlement.can_settle && !$return_completed}
            <form method="post" action="{$return_settlement_url|escape:'html':'UTF-8'}" id="return-settlement-form">
                <input type="hidden" name="return_fingerprint" value="{$return_settlement.fingerprint|escape:'html':'UTF-8'}">
                <div class="form-group">
                    <label for="return-refund-method">Rückzahlung</label>
                    <select id="return-refund-method" name="return_refund_method" class="form-control" style="max-width: 420px;" data-original-payment-label="{$return_settlement.original_payment_label|escape:'html':'UTF-8'}">
                        {foreach $return_settlement.methods as $value => $label}<option value="{$value|escape:'html':'UTF-8'}" {if $value == $return_settlement.default_method}selected{/if}>{$label|escape:'html':'UTF-8'}</option>{/foreach}
                    </select>
                </div>
                <div class="btn-toolbar">
                    <button type="submit" name="settleOrderReturn" class="btn btn-primary"><i class="icon-check"></i> <span id="return-submit-label">Gutschrift erstellen</span></button>
                    <a class="btn btn-default" id="return-adjust-link" href="{$return_settlement.adjust_url|escape:'html':'UTF-8'}"><i class="icon-pencil"></i> Beträge in der Bestellung anpassen</a>
                </div>
            </form>
        {else}
            <a class="btn btn-default" id="return-adjust-link" href="{$return_settlement.adjust_url|escape:'html':'UTF-8'}"><i class="icon-pencil"></i> Beträge in der Bestellung anpassen</a>
        {/if}
        <div class="alert alert-warning" id="return-unsaved" style="display:none">Die Mengen wurden geändert. Bitte zuerst speichern, damit Betrag und Rückzahlung neu berechnet werden.</div>
    </div>
    {/if}
</div>
<script>
    $(function () {
        $('.return-quantity-input').on('input change', function () {
            $('#return-settlement-form button').prop('disabled', true);
            $('#return-adjust-link').addClass('disabled').attr('aria-disabled', 'true');
            $('#return-settlement .return-financial-summary, #return-settlement-form, #return-adjust-link').hide();
            $('#return-unsaved').show();
        });
        $('#return-refund-method').on('change', function () {
            var url = new URL($('#return-adjust-link').attr('href'), window.location.href);
            url.searchParams.set('prefill_refund_method', this.value);
            $('#return-adjust-link').attr('href', url.toString());
            var payout = $('#return-payout-amount').text();
            var label = 'Gutschrift erstellen';
            if ({$return_settlement.payout|floatval} > 0 && this.value === 'store_credit') {
                label = payout + ' als Kundenguthaben buchen';
            } else if ({$return_settlement.payout|floatval} > 0 && this.value === 'original_payment') {
                label = payout + ' über ' + ($(this).attr('data-original-payment-label') || 'die ursprüngliche Zahlung') + ' zurückzahlen';
            }
            $('#return-submit-label').text(label);
        }).trigger('change');
        $('#return-settlement-form').on('submit', function () {
            return window.confirm($('#return-submit-label').text() + ' – jetzt verbindlich ausführen?');
        });
    });
</script>
<style>
    .order-return-workspace .col-lg-9, .order-return-workspace .col-lg-3 { min-width: 0; }
    .order-return-workspace #order_return_form .col-lg-offset-3 { margin-left: 0; }
    .order-return-workspace .return-meta { margin-bottom: 16px; }
    .order-return-workspace .return-products td { padding-top: 12px; padding-bottom: 12px; vertical-align: middle; }
    .order-return-workspace .return-products small { display: block; margin-top: 3px; font-size: 11px; }
    .order-return-workspace .return-products .return-line-amount { font-weight: 600; }
    .order-return-workspace .return-financial-summary { width: 440px; max-width: 100%; margin-left: auto; }
    .order-return-workspace .return-summary td { padding: 6px 0; border-bottom: 1px solid #eee; }
    .order-return-workspace .return-summary td:last-child { padding-left: 20px; text-align: right; white-space: nowrap; }
    .order-return-workspace .return-summary .return-total td { padding-top: 10px; font-size: 16px; font-weight: 600; border-bottom: 0; }
    .order-return-workspace .return-actions { clear: both; margin-top: 18px; padding-top: 16px; border-top: 1px solid #e5e5e5; }
    .order-return-workspace .return-link { display: flex; align-items: center; gap: 9px; padding: 10px 0; border-bottom: 1px solid #eee; }
    .order-return-workspace .return-link:last-child { border-bottom: 0; }
    .order-return-workspace .return-link i { width: 16px; color: #777; }
    @media (max-width: 767px) {
        .order-return-workspace .return-actions .btn { width: 100%; white-space: normal; margin-bottom: 8px; }
    }
</style>
