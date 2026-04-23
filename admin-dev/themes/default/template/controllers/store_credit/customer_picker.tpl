{*
* Copyright (C) 2025-2026 thirty bees
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.md.
* It is also available through the world-wide-web at this URL:
* https://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@thirtybees.com so we can send you a copy immediately.
*
* @author    thirty bees <contact@thirtybees.com>
* @copyright 2025-2026 thirty bees
* @license   Open Software License (OSL 3.0)
*}

<div class="store-credit-customer-picker">
    <div class="row">
        <div class="col-lg-6">
            <div class="input-group">
                <input
                    type="text"
                    id="store_credit_customer_search"
                    class="form-control"
                    autocomplete="off"
                    placeholder="{l s='Type customer name or email'}"
                />
                <span class="input-group-addon">
                    <i class="icon-search"></i>
                </span>
            </div>
        </div>
    </div>
    <p id="store_credit_customer_selected" class="help-block" style="margin-top: 6px;"></p>
    <div class="row" style="margin-top: 8px;">
        <div class="col-lg-12">
            <div id="store_credit_customer_results"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
    (function () {
        var adminCustomersUrl = '{$storeCreditCustomerPickerAdminCustomersUrl|escape:'javascript':'UTF-8'}';
        var selectedCustomerText = '{l s='Selected customer' js=1}';
        var chooseText = '{l s='Choose' js=1}';
        var detailsText = '{l s='Details' js=1}';
        var noCustomersText = '{l s='No customers found' js=1}';
        var selectedId = {$storeCreditCustomerPickerIdCustomer|intval};
        var initialCustomerLabel = '{$storeCreditCustomerPickerInitialCustomerLabel|escape:'javascript':'UTF-8'}';
        var $idCustomer = $('#id_customer');
        var $search = $('#store_credit_customer_search');
        var $selected = $('#store_credit_customer_selected');
        var $results = $('#store_credit_customer_results');
        var timer = null;

        function escapeHtml(value) {
            return $('<div/>').text(value === null ? '' : String(value)).html();
        }

        function showSelected(label) {
            if (!selectedId || !label) {
                $selected.text('');
                return;
            }
            $selected.html('<strong>' + escapeHtml(selectedCustomerText) + ':</strong> ' + escapeHtml(label));
        }

        function renderCustomers(customers) {
            var html = '';
            $.each(customers, function() {
                var name = (this.firstname || '') + ' ' + (this.lastname || '');
                name = $.trim(name);
                var email = this.email || '';
                var birthday = this.birthday || '';
                var idCustomer = parseInt(this.id_customer, 10) || 0;
                var detailsUrl = adminCustomersUrl + '&id_customer=' + idCustomer + '&viewcustomer=1';

                html += '<div class="customerCard col-lg-4">';
                html += '<div class="panel">';
                html += '<div class="panel-heading">' + escapeHtml(name) + '<span class="pull-right">#' + idCustomer + '</span></div>';
                html += '<span class="customer-email">' + escapeHtml(email) + '</span><br/>';
                html += '<span class="text-muted">' + ((birthday && birthday !== '0000-00-00') ? escapeHtml(birthday) : '') + '</span><br/>';
                html += '<div class="panel-footer">';
                html += '<a href="' + detailsUrl + '" target="_blank" class="btn btn-default"><i class="icon-search"></i> ' + escapeHtml(detailsText) + '</a>';
                html += '<button type="button" data-customer="' + idCustomer + '" class="setup-store-credit-customer btn btn-default pull-right"><i class="icon-arrow-right"></i> ' + escapeHtml(chooseText) + '</button>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            $results.html(html).show();
        }

        function searchCustomers() {
            var query = $.trim($search.val());
            if (query.length < 2) {
                $results.empty().hide();
                return;
            }

            $.ajax({
                type: 'POST',
                url: adminCustomersUrl,
                async: true,
                dataType: 'json',
                data: {
                    ajax: '1',
                    tab: 'AdminCustomers',
                    action: 'searchCustomers',
                    customer_search: query
                },
                success: function (res) {
                    if (res && res.found && $.isArray(res.customers) && res.customers.length) {
                        renderCustomers(res.customers);
                    } else {
                        $results.html('<div class="alert alert-warning">' + escapeHtml(noCustomersText) + '</div>').show();
                    }
                }
            });
        }

        $search.off('input.storeCreditCustomerPicker').on('input.storeCreditCustomerPicker', function () {
            if (timer !== null) {
                clearTimeout(timer);
            }
            timer = setTimeout(searchCustomers, 300);
        });

        $results.off('click.storeCreditCustomerPicker').on('click.storeCreditCustomerPicker', '.setup-store-credit-customer', function (event) {
            event.preventDefault();
            var $card = $(this).closest('.customerCard');
            selectedId = parseInt($(this).data('customer'), 10) || 0;

            var name = $.trim($card.find('.panel-heading').clone().children().remove().end().text());
            var email = $.trim($card.find('.customer-email').first().text());
            var label = name + (email ? ' (' + email + ')' : '');

            $idCustomer.val(selectedId);
            showSelected(label);

            $results.find('.customerCard').removeClass('selected-customer');
            if ($card.find('.panel-heading .icon-ok').length === 0) {
                $card.find('.panel-heading').prepend('<i class="icon-ok text-success"></i> ');
            }
            $card.addClass('selected-customer');
            $search.val('');
            $results.empty().hide();
        });

        if (selectedId > 0) {
            $idCustomer.val(selectedId);
            showSelected(initialCustomerLabel);
        }
    })();
</script>
