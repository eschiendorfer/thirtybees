(function ($) {
    'use strict';

    $(function () {
        $('.js-entity-employee-assignment').each(function () {
            $(this).data('saved-value', this.value);
        }).on('change', function () {
            var select = $(this);
            var previousValue = select.data('saved-value');
            var updateUrl = select.data('update-url') || window.location.href;
            select.prop('disabled', true);

            $.post(updateUrl, {
                ajax: 1,
                action: 'updateEntityAssignment',
                entity_type: select.data('entity-type'),
                id_entity: select.data('id-entity'),
                id_employee: select.val()
            }, function (response) {
                if (response.success) {
                    select.data('saved-value', select.val());
                    if (typeof showSuccessMessage === 'function') {
                        showSuccessMessage(response.text);
                    }
                } else {
                    select.val(previousValue);
                    if (typeof showErrorMessage === 'function') {
                        showErrorMessage(response.text);
                    }
                }
            }, 'json').fail(function () {
                select.val(previousValue);
                if (typeof showErrorMessage === 'function') {
                    showErrorMessage('The assignment could not be updated.');
                }
            }).always(function () {
                select.prop('disabled', false);
            });
        });
    });
}(jQuery));
