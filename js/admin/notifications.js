/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 *  @author    thirty bees <contact@thirtybees.com>
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2017-2024 thirty bees
 *  @copyright 2007-2016 PrestaShop SA
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

/* global window, $, moment, autorefresh_notifications, full_language_code */

var notificationLastIds = {};

$(document).ready(function () {
  // set up notification click handler
  $('.notifs').on('click', function () {
    markNotificationTypeRead($(this).parent().data('type'));
  });

  // The responsive modal shows the complete notification area at once.
  $('#notificationsModal').on('shown.bs.modal', function () {
    $('#notificationsModalContent [data-type]').each(function () {
      markNotificationTypeRead($(this).data('type'));
    });
  });

  // update notifications once, as soon as possible
  setTimeout(updateNotifications, 0);

  // set up refresh interval
  if (autorefresh_notifications) {
    setInterval(updateNotifications, 120000);
  }
});

function markNotificationTypeRead(type) {
  var lastIds = notificationLastIds[type];
  if (!lastIds || Object.keys(lastIds).length === 0) {
    return;
  }

  $.post(
    'ajax.php',
    {
      markNotificationsRead: 1,
      lastIds: lastIds
    },
    function (data) {
      if (data && data.success) {
        $('[id="' + type + '_notif_value"]').html(0);
        $('[id="' + type + '_notif_number_wrapper"]').addClass('hide').hide();
        notificationLastIds[type] = {};
        updateResponsiveNotificationBadge();
      }
    },
    'json'
  );
}

function updateNotifications() {
  $.ajax({
    type: 'POST',
    headers: { 'cache-control': 'no-cache' },
    url: 'ajax.php?rand=' + new Date().getTime(),
    async: true,
    cache: false,
    dataType: 'json',
    data: {
      getNotifications: '1'
    },
    success: function (json) {
      if (json) {
        // Set moment language
        moment.lang(full_language_code);

        for (var i = 0; i < json.length; i++) {
          var record = json[i];
          var type = record.type;
          var total = record.total;
          notificationLastIds[type] = total > 0 ? (record.lastIds || {}) : {};
          if (total > 0) {
            var defaultRenderer = record.renderer;
            var results = record.results;
            var html = '';
            for (var j = 0; j < results.length; j++) {
              var renderer = results[j].renderer || defaultRenderer;
              html += executeFunctionByName(renderer, [results[j], results[j].rendererData || record.rendererData]);
            }
            $('[id="'+type+'_notif_list"]').empty().append(html);
            $('[id="'+type+'_notif_value"]').text(total);
            $('[id="'+type+'_notif_number_wrapper"]').removeClass('hide').show();
          } else {
            $('[id="'+type+'_notif_number_wrapper"]').addClass('hide').hide();
          }
        }
        updateResponsiveNotificationBadge();
      }
    }
  });
}

/* renderers for default notification types */

function renderOrderNotification(notification, translations) {
  var html = '';
  html += "<a id='notification_order"+notification.id+"' href='"+notification.link+"'>";
  html += "<p>" + translations.orderNumber + "&nbsp;<strong>#" + parseInt(notification.id, 10) + "</strong></p>";
  html += "<p class='pull-right'>" + translations.total + "&nbsp;<span class='total badge badge-success'>" + notification.total + "</span></p>";
  html += "<p>" + translations.from + "&nbsp;<strong>" + notification.customerName + "</strong></p>";
  html += "<small class='text-muted'><i class='icon-time'></i>&nbsp;" + moment(notification.ts * 1000).fromNow() + "</small>";
  html += "</a>";
  return html;
}

function renderCustomerNotification(notification, translations) {
  var html = '';
  html += "<a id='notification_customer_"+notification.id+"' href='"+notification.link+"'>";
  html += "<p><span class='notification-customer-name-title'>" + translations.customerName + "</span>&nbsp;<strong>" + notification.customerName + "</strong></p>";
  html += "<small class='text-muted'><i class='icon-time'></i>&nbsp;" + moment(notification.ts * 1000).fromNow() + "</small>";
  html += "</a>";
  return html;
}

function renderCustomerMessageNotification(notification, translations) {
  var html = '';
  html += "<a id='notification_customer_message_"+notification.id+"' href='"+notification.link+"'>";
  html += "<p>" + translations.from + "&nbsp;<strong>" + notification.from + "</strong></p>";
  html += "<small class='text-muted'><i class='icon-time'></i>&nbsp;" + moment(notification.ts * 1000).fromNow() + "</small>";
  html += "</a>";
  return html;
}

function renderCustomerServiceNotification(notification) {
  var title = $('<div>').text(notification.title || '').html();
  var customer = $('<div>').text(notification.customer || '').html();
  var reference = $('<div>').text(notification.reference || '').html();
  var html = '';
  html += "<a href='"+notification.link+"'>";
  html += '<p><strong>' + title + '</strong>' + (reference ? '&nbsp;<span class="text-muted">' + reference + '</span>' : '') + '</p>';
  if (customer) {
    html += '<p>' + customer + '</p>';
  }
  html += "<small class='text-muted'><i class='icon-time'></i>&nbsp;" + moment(notification.ts * 1000).fromNow() + '</small>';
  html += '</a>';
  return html;
}

function renderSystemNotification(notification, translations) {
  var html = '';
  console.log(notification);
  html += "<a id='notification_system_notification_"+notification.id+"' href='"+notification.link+"'>";
  html += "<p><span class='badge "+notification.badgeClass+"'>" + translations[notification.importance] + "</span> &nbsp;<strong>" + notification.title + "</strong></p>";
  html += "<small class='text-muted'><i class='icon-time'></i>&nbsp;" + moment(notification.ts * 1000).fromNow() + "</small>";
  html += "</a>";
  return html;
}

function updateResponsiveNotificationBadge() {
  var hasNotifications = false;
  $('#header_notifs_icon_wrapper > li > .notifs .notifs_badge:not(.hide) span').each(function () {
    if (parseInt($(this).text(), 10) > 0) {
      hasNotifications = true;
    }
  });
  $('.notifications-icon .notifs_badge').toggle(hasNotifications);
}
