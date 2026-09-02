(function ($, window, document) {
  'use strict';

  $(function () {
    var workspace = document.querySelector('[data-customer-thread-workspace]');
    if (!workspace) {
      return;
    }

    var threadSettingUrl = workspace.getAttribute('data-thread-setting-url') || '';
    var threadId = parseInt(workspace.getAttribute('data-thread-id') || '0', 10) || 0;
    var updateError = workspace.getAttribute('data-thread-update-error') || 'The thread could not be updated.';
    var entitySettingUrl = workspace.getAttribute('data-entity-setting-url') || '';
    var entitySettingAction = workspace.getAttribute('data-entity-setting-action') || '';
    var entityId = parseInt(workspace.getAttribute('data-entity-id') || '0', 10) || 0;
    var entityUpdateError = workspace.getAttribute('data-entity-update-error') || 'The item could not be updated.';
    var autoScroll = workspace.getAttribute('data-auto-scroll') !== '0';
    var statusButtonClasses = 'btn-default btn-danger btn-warning btn-info btn-success';

    function updateStatusControl(control, value) {
      var option = $(control).find('[data-status-choice]').filter(function () {
        return String($(this).data('status-value')) === String(value);
      }).first();
      if (!option.length) {
        return;
      }

      $(control).find('[data-status-button]')
        .removeClass(statusButtonClasses)
        .addClass(option.data('status-button-class'));
      $(control).find('[data-selected-status-label]').text(option.data('status-label'));
    }

    function hasReplyContent(form) {
      var message = String($(form).find('[name="reply_message"]').val() || '');
      var content = document.createElement('div');
      content.innerHTML = message;
      if (String(content.textContent || content.innerText || '').replace(/\u00a0/g, ' ').trim() !== '') {
        return true;
      }

      var payload = String($(form).find('[data-file-upload-value]').val() || '');
      if (payload !== '') {
        try {
          var uploadData = JSON.parse(payload);
          if (uploadData && $.isArray(uploadData.attachments) && uploadData.attachments.length > 0) {
            return true;
          }
        } catch (error) {
          // The server remains authoritative for malformed upload data.
        }
      }

      return false;
    }

    function updateReplyAction(form) {
      var canSaveStatus = form.getAttribute('data-can-save-status') === '1';
      var hasContent = hasReplyContent(form);
      var hadContent = form.getAttribute('data-has-reply-content') === '1';
      var statusWasSelected = form.getAttribute('data-status-explicit') === '1';
      var control = $(form).find('[data-status-control]').first();
      var input = control.find('[data-status-input]').first();

      if (!statusWasSelected && hasContent !== hadContent) {
        var automaticStatus = hasContent
          ? form.getAttribute('data-default-reply-status')
          : form.getAttribute('data-current-status');
        if (automaticStatus) {
          input.val(automaticStatus);
          updateStatusControl(control, automaticStatus);
        }
      }

      var isReply = hasContent || !canSaveStatus;
      $(form).find('[data-reply-action-label]').text(
        form.getAttribute(isReply ? 'data-reply-label' : 'data-status-save-label')
      );
      $(form).find('[data-reply-action-icon]')
        .toggleClass('icon-mail-reply', isReply)
        .toggleClass('icon-save', !isReply);
      form.setAttribute('data-has-reply-content', hasContent ? '1' : '0');
    }

    $(workspace).on('click', '[data-status-choice]', function (event) {
      event.preventDefault();
      var option = $(this);
      var control = option.closest('[data-status-control]');
      var input = control.find('[data-status-input]').first();
      var value = String(option.data('status-value'));

      control.closest('form').attr('data-status-explicit', '1');
      input.val(value);
      updateStatusControl(control, value);
    });

    $('.customer-thread-reply').each(function () {
      var form = this;
      var preview = form.querySelector('[data-file-upload-preview]');
      $(form).on('input change', '[name="reply_message"], [data-file-upload-value]', function () {
        updateReplyAction(form);
      });
      $(form).on('change', '#order_message', function () {
        window.setTimeout(function () {
          updateReplyAction(form);
        }, 0);
      });
      if (preview && window.MutationObserver) {
        new MutationObserver(function () {
          updateReplyAction(form);
        }).observe(preview, {childList: true, subtree: true});
      }
      updateReplyAction(form);
    });

    function openAttachmentImage(url, name) {
      $('#customer-thread-attachment-title').text(name || '');
      $('#customer-thread-attachment-modal .customer-thread-attachment-image')
        .attr('src', url)
        .attr('alt', name || '');
      $('#customer-thread-attachment-modal').modal('show');
    }

    if (threadSettingUrl && threadId > 0) {
      $('.js-thread-setting').each(function () {
        $(this).data('saved-value', this.value);
      }).on('change', function () {
        var select = $(this);
        var previousValue = select.data('saved-value');

        $.post(threadSettingUrl, {
          ajax: 1,
          action: 'updateThreadSetting',
          id_customer_thread: threadId,
          field: select.data('setting'),
          value: select.val()
        }, function (data) {
          if (data.success) {
            select.data('saved-value', select.val());
            showSuccessMessage(data.text);
          } else {
            select.val(previousValue);
            showErrorMessage(data.text);
          }
        }, 'json').fail(function () {
          select.val(previousValue);
          showErrorMessage(updateError);
        });
      });
    }

    if (entitySettingUrl && entitySettingAction && entityId > 0) {
      $('.js-entity-setting').each(function () {
        $(this).data('saved-value', this.value);
      }).on('change', function () {
        var select = $(this);
        var previousValue = select.data('saved-value');
        select.prop('disabled', true);

        $.post(entitySettingUrl, {
          ajax: 1,
          action: entitySettingAction,
          id_order_service_case: entityId,
          field: select.data('setting'),
          value: select.val()
        }, function (data) {
          if (data.success) {
            select.data('saved-value', select.val());
            showSuccessMessage(data.text);
          } else {
            select.val(previousValue);
            showErrorMessage(data.text);
          }
        }, 'json').fail(function () {
          select.val(previousValue);
          showErrorMessage(entityUpdateError);
        }).always(function () {
          select.prop('disabled', false);
        });
      });
    }

    $('.js-customer-thread-image').on('click', function (event) {
      event.preventDefault();
      var attachment = $(this);
      openAttachmentImage(attachment.attr('href'), attachment.data('attachment-name'));
    });

    var upload = workspace.querySelector('.customer-thread-file-upload');
    if (upload) {
      upload.addEventListener('tb:file-upload:open', function (event) {
        var attachment = event.detail && event.detail.attachment ? event.detail.attachment : null;
        if (!attachment || !attachment.open_url) {
          return;
        }

        if (String(attachment.mime || '').toLowerCase().indexOf('image/') === 0) {
          openAttachmentImage(attachment.open_url, attachment.name);
          return;
        }

        window.open(attachment.open_url, '_blank', 'noopener');
      });
    }

    $('#customer-thread-attachment-modal').on('hidden.bs.modal', function () {
      $(this).find('.customer-thread-attachment-image').attr('src', '').attr('alt', '');
    });

    if (autoScroll && !window.location.hash) {
      var latestMessage = workspace.querySelector('.customer-thread-message-list .customer-thread-message:last-child');
      if (latestMessage) {
        window.requestAnimationFrame(function () {
          window.scrollTo(0, latestMessage.getBoundingClientRect().top + window.pageYOffset - 55);
        });
      }
    }
  });
})(jQuery, window, document);
