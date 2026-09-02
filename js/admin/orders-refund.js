/* Refund system script */
var flagRefund = '';
var creditAdjustmentState = {
  cartRule: {
    manual: false,
    source: 'amount'
  },
  fee: {
    manual: false,
    source: 'amount'
  }
};
var originalPaymentRefundConfirmationAmount = '';

$(document).ready(function () {
  $('#desc-order-partial_refund, .order-credit-button').click(function (e) {
    e.preventDefault();
    $('.cancel_product_change_link:visible').trigger('click');
    closeAddProduct();
    $('.order-product-action-button').removeClass('btn-primary').addClass('btn-default');
    $('.order_product_action_fields').hide();
    $('.order_product_action_fields :input').prop('disabled', true);
    $('#order_product_action_form').hide().find(':input').prop('disabled', true);
    if (flagRefund === 'partial') {
      flagRefund = '';
      $('.partial_refund_fields').hide();
      $('.partial_refund_fields :input').prop('disabled', true);
    } else {
      flagRefund = 'partial';
      $('.product_action, .order_action').hide();
      $('.product_action').hide();
      $('.partial_refund_fields').fadeIn();
      $('.partial_refund_fields :input').prop('disabled', false);
      $('#reason_entity_type').val('');
      $('#credit_refund_method').val('');
      updateCreditReasonEntityOptions();
      resetCreditForm();

      scrollToOrderProductsPanel();
    }

  });

  $('#reason_entity_type').change(function () {
    updateCreditReasonEntityOptions();
    applyCreditReasonSuggestion();
  });

  $('#reason_id_entity').change(function () {
    applyCreditReasonSuggestion();
  });

  $('#credit_refund_method').change(function () {
    if (isNoCreditRefundMethod()) {
      creditAdjustmentState.fee.manual = false;
      $('#credit_fee_adjustment').val(formatCreditInput(0));
      $('#credit_fee_adjustment_rate').val(formatCreditRateInput(0));
    }
    applySuggestedCancellationFee();
    refreshSuggestedCreditAdjustments();
    updateCreditTotals();
  });

  $('#confirm_original_payment_refund').change(function () {
    updatePartialRefundSubmitState();
  });

  $('#credit_shipping_adjustment').on('change keyup', function () {
    refreshSuggestedCreditAdjustments();
    updateCreditTotals();
  });

  $(document).on('change keyup', '.credit-product-amount-input', function () {
    refreshSuggestedCreditAdjustments();
    updateCreditTotals();
  });

  $(document).on('change keyup', '.credit-adjustment-amount-input, .credit-adjustment-rate-input', function (event) {
    var adjustmentType = getCreditAdjustmentType($(this));
    if (adjustmentType) {
      creditAdjustmentState[adjustmentType].manual = true;
      creditAdjustmentState[adjustmentType].source = $(this).hasClass('credit-adjustment-rate-input') ? 'rate' : 'amount';
    }
    refreshSuggestedCreditAdjustments(event.type === 'keyup');
    updateCreditTotals(event.type === 'keyup');
  });

  applyCreditUrlPrefill();
});

function scrollToOrderProductsPanel() {
  var $target = $('#start_products');
  if (!$target.length) {
    $target = $('#orderProducts');
  }

  if ($target.length) {
    $('html, body').animate({
      scrollTop: Math.max(0, $target.offset().top - 150)
    }, 250);
  }
}

function parseCreditNumber(value) {
  var number = parseFloat(String(value || '').replace(',', '.'));
  return isNaN(number) ? 0 : number;
}

function roundCreditAmount(amount) {
  return Math.round(Math.max(0, parseFloat(amount) || 0) * 100) / 100;
}

function roundCreditSignedAmount(amount) {
  return Math.round((parseFloat(amount) || 0) * 100) / 100;
}

function roundCreditRefundTotalAmount(amount) {
  var unit = window.orderCreditSuggestions && window.orderCreditSuggestions.round_unit
    ? parseFloat(window.orderCreditSuggestions.round_unit)
    : 0.05;

  if (!unit || isNaN(unit)) {
    unit = 0.05;
  }

  return Math.round(Math.max(0, parseFloat(amount) || 0) / unit) * unit;
}

function formatCreditInput(amount) {
  return roundCreditAmount(amount).toFixed(2);
}

function formatCreditSignedInput(amount) {
  return roundCreditSignedAmount(amount).toFixed(2);
}

function normalizeCreditRate(rate) {
  return Math.min(100, Math.max(0, parseCreditNumber(rate)));
}

function formatCreditRateInput(rate) {
  return normalizeCreditRate(rate).toFixed(2);
}

function formatCreditDisplay(amount) {
  return formatCurrency(roundCreditAmount(amount), window.currency_format, window.currency_sign, window.currency_blank);
}

function formatCreditRefundTotalDisplay(amount) {
  return formatCurrency(roundCreditRefundTotalAmount(amount), window.currency_format, window.currency_sign, window.currency_blank);
}

function getCreditAdjustmentType($input) {
  var id = $input.attr('id') || '';
  if (id.indexOf('cart_rule') !== -1) {
    return 'cartRule';
  }
  if (id.indexOf('fee') !== -1) {
    return 'fee';
  }

  return null;
}

function getCreditAdjustmentSelectors(type) {
  if (type === 'cartRule') {
    return {
      amount: '#credit_cart_rule_adjustment',
      rate: '#credit_cart_rule_adjustment_rate'
    };
  }

  return {
    amount: '#credit_fee_adjustment',
    rate: '#credit_fee_adjustment_rate'
  };
}

function getCreditAdjustmentAmount(type) {
  return parseCreditNumber($(getCreditAdjustmentSelectors(type).amount).val());
}

function getCreditAdjustmentRate(type) {
  return normalizeCreditRate($(getCreditAdjustmentSelectors(type).rate).val());
}

function setCreditAdjustment(type, amount, base, preserveManualSource) {
  var selectors = getCreditAdjustmentSelectors(type);
  var normalizedAmount = Math.min(roundCreditAmount(amount), roundCreditAmount(base));
  var rate = base > 0 ? normalizedAmount / base * 100 : 0;
  var preserveAmount = preserveManualSource && creditAdjustmentState[type].manual && creditAdjustmentState[type].source === 'amount';
  var preserveRate = preserveManualSource && creditAdjustmentState[type].manual && creditAdjustmentState[type].source === 'rate';

  if (!preserveAmount) {
    $(selectors.amount).val(formatCreditInput(normalizedAmount));
  }
  if (!preserveRate) {
    $(selectors.rate).val(formatCreditRateInput(rate));
  }
}

function setCreditAdjustmentFromRate(type, base, preserveManualSource) {
  setCreditAdjustment(type, base * getCreditAdjustmentRate(type) / 100, base, preserveManualSource);
}

function setCreditAdjustmentFromAmount(type, base, preserveManualSource) {
  setCreditAdjustment(type, getCreditAdjustmentAmount(type), base, preserveManualSource);
}

function getDefaultCreditCartRuleRate() {
  if (window.orderCreditSuggestions && typeof window.orderCreditSuggestions.default_cart_rule_rate !== 'undefined') {
    return parseCreditNumber(window.orderCreditSuggestions.default_cart_rule_rate);
  }

  return 0;
}

function getDefaultCreditFeeRate() {
  var refundMethod = getCreditRefundMethod();
  if (refundMethod === 'none') {
    return 0;
  }

  if (
    window.orderCreditSuggestions &&
    window.orderCreditSuggestions.default_fee_rates &&
    typeof window.orderCreditSuggestions.default_fee_rates[refundMethod] !== 'undefined'
  ) {
    return parseCreditNumber(window.orderCreditSuggestions.default_fee_rates[refundMethod]);
  }

  if (window.orderCreditSuggestions && typeof window.orderCreditSuggestions.default_fee_rate !== 'undefined') {
    return parseCreditNumber(window.orderCreditSuggestions.default_fee_rate);
  }

  return 0;
}

function getCreditRefundMethod() {
  return $('#credit_refund_method').val() || 'none';
}

function isNoCreditRefundMethod() {
  return getCreditRefundMethod() === 'none';
}

function isOriginalPaymentCreditRefundMethod() {
  return getCreditRefundMethod() === 'original_payment';
}

function isServiceCaseCreditReason() {
  return $('#reason_entity_type').val() === 'service_case';
}

function updateOriginalPaymentRefundConfirmation(creditTotal) {
  var requiresConfirmation = isOriginalPaymentCreditRefundMethod();
  var $confirmation = $('#original_payment_refund_confirmation');
  var $checkbox = $('#confirm_original_payment_refund');
  var amountDisplay = formatCreditRefundTotalDisplay(creditTotal);
  var confirmationTemplate = window.originalPaymentRefundConfirmationTemplate
    || 'I confirm that %s will be refunded via Payrexx to the original payment method.';

  var confirmationParts = confirmationTemplate.split('%s');
  var $confirmationText = $('#original_payment_refund_confirmation_text');
  $confirmationText.empty();
  $confirmationText.append(document.createTextNode(confirmationParts[0] || ''));
  $confirmationText.append($('<strong>').text(amountDisplay));
  $confirmationText.append(document.createTextNode(confirmationParts.slice(1).join(amountDisplay)));

  $confirmation.toggle(requiresConfirmation);
  $checkbox.prop('disabled', !requiresConfirmation);

  if (!requiresConfirmation || originalPaymentRefundConfirmationAmount !== amountDisplay) {
    $checkbox.prop('checked', false);
  }

  originalPaymentRefundConfirmationAmount = requiresConfirmation ? amountDisplay : '';
  updatePartialRefundSubmitState();
}

function updatePartialRefundSubmitState() {
  var requiresConfirmation = isOriginalPaymentCreditRefundMethod();
  var $label = $('#partial_refund_submit_label');
  if ($label.length) {
    if (requiresConfirmation) {
      $label.text($label.data('original-payment'));
    } else if (getCreditRefundMethod() === 'store_credit') {
      $label.text($label.data('store-credit'));
    } else {
      $label.text($label.data('none'));
    }
  }
  $('#partial_refund_submit').prop(
    'disabled',
    requiresConfirmation && !$('#confirm_original_payment_refund').is(':checked')
  );
}

function updateCreditFeeAdjustmentState() {
  var disableFee = isNoCreditRefundMethod() || isServiceCaseCreditReason();
  if (disableFee) {
    creditAdjustmentState.fee.manual = false;
    setCreditAdjustment('fee', 0, 0);
  }
  $('#credit_fee_adjustment, #credit_fee_adjustment_rate').prop('disabled', disableFee);
}

function updateCreditCartRuleAdjustmentState() {
  var disableCartRule = isServiceCaseCreditReason();
  if (disableCartRule) {
    creditAdjustmentState.cartRule.manual = false;
    setCreditAdjustment('cartRule', 0, 0);
  }
  $('#credit_cart_rule_adjustment, #credit_cart_rule_adjustment_rate').prop('disabled', disableCartRule);
}

function resetCreditForm() {
  creditAdjustmentState.cartRule.manual = false;
  creditAdjustmentState.cartRule.source = 'amount';
  creditAdjustmentState.fee.manual = false;
  creditAdjustmentState.fee.source = 'amount';
  $('.credit-product-amount-input').val('').prop('disabled', false).closest('td').removeClass('text-muted');
  $('.credit-product-quantity-input').val('0');
  $('#credit_shipping_adjustment').val('0').prop('disabled', false);
  $('.service-case-credit-status-group').hide().find(':input').prop('disabled', true);
  $('#confirm_original_payment_refund').prop('checked', false);
  setCreditAdjustment('cartRule', 0, 0);
  setCreditAdjustment('fee', 0, 0);
  updateCreditCartRuleAdjustmentState();
  updateCreditFeeAdjustmentState();
  updateCreditTotals();
}

function updateCreditReasonEntityOptions() {
  var reason = $('#reason_entity_type').val();
  var $entity = $('#reason_id_entity');
  var showEntity = !!reason && reason !== 'manual';

  $('.credit-reason-entity-group').toggle(showEntity);
  $entity.prop('required', showEntity);
  $entity.find('option').each(function () {
    var isPlaceholder = $(this).val() === '';
    var optionReason = $(this).data('reason-type');
    $(this).toggle(isPlaceholder || (showEntity && optionReason === reason));
  });

  if (showEntity) {
    var $selectedOption = $entity.find('option:selected');
    if (!$selectedOption.length || $selectedOption.val() === '0' || $selectedOption.data('reason-type') !== reason) {
      $entity.val('');
    }
  } else {
    $entity.val('');
  }
}

function applyCreditReasonSuggestion() {
  resetCreditForm();

  var reason = $('#reason_entity_type').val();
  var idEntity = $('#reason_id_entity').val();
  if (reason === 'service_case') {
    var serviceCaseSuggestion = window.orderCreditSuggestions &&
      window.orderCreditSuggestions[reason] &&
      window.orderCreditSuggestions[reason][idEntity]
      ? window.orderCreditSuggestions[reason][idEntity]
      : null;

    applyServiceCaseCreditScope(serviceCaseSuggestion);
    return;
  }

  if (
    !window.orderCreditSuggestions ||
    !window.orderCreditSuggestions[reason] ||
    !window.orderCreditSuggestions[reason][idEntity]
  ) {
    return;
  }

  var suggestion = window.orderCreditSuggestions[reason][idEntity];
  $.each(suggestion.products || {}, function (idOrderDetail, productSuggestion) {
    var $input = $('.credit-product-amount-input[data-id-order-detail="' + idOrderDetail + '"]');
    if ($input.length) {
      $input.val(formatCreditInput(productSuggestion.amount));
    }
    $('.credit-product-quantity-input[data-id-order-detail="' + idOrderDetail + '"]').val(parseInt(productSuggestion.quantity || 0, 10));
  });

  var shippingRefund = suggestion.shipping && typeof suggestion.shipping.amount !== 'undefined'
    ? roundCreditAmount(suggestion.shipping.amount)
    : 0;
  var shippingCharge = suggestion.shipping_charge_adjustment && typeof suggestion.shipping_charge_adjustment.amount !== 'undefined'
    ? roundCreditAmount(suggestion.shipping_charge_adjustment.amount)
    : 0;
  $('#credit_shipping_adjustment').val(formatCreditSignedInput(shippingRefund - shippingCharge));

  var totals = getCreditEnteredTotals();
  if (suggestion.cart_rule_adjustment && typeof suggestion.cart_rule_adjustment.amount !== 'undefined') {
    setCreditAdjustment('cartRule', suggestion.cart_rule_adjustment.amount, totals.products);
  } else {
    refreshSuggestedCreditAdjustments();
    totals = getCreditEnteredTotals();
  }

  var cartRuleAdjustment = Math.min(totals.products, roundCreditAmount(getCreditAdjustmentAmount('cartRule')));
  var feeBase = Math.max(0, totals.products + totals.shippingAdjustment - cartRuleAdjustment);
  if (suggestion.fee_adjustment && typeof suggestion.fee_adjustment.amount !== 'undefined') {
    setCreditAdjustment('fee', suggestion.fee_adjustment.amount, feeBase);
  } else {
    refreshSuggestedCreditAdjustments();
  }

  updateCreditTotals();
}

function applyServiceCaseCreditScope(suggestion) {
  var products = suggestion && suggestion.products ? suggestion.products : {};
  var hasSuggestion = !!suggestion;

  $('.service-case-credit-status-group').toggle(hasSuggestion);
  $('#service_case_status').prop('disabled', !hasSuggestion);
  if (hasSuggestion && suggestion.status) {
    $('#service_case_status').val(suggestion.status);
  }

  $('.credit-product-amount-input').each(function () {
    var $input = $(this);
    var idOrderDetail = String($input.data('id-order-detail'));
    var isAllowed = hasSuggestion && !!products[idOrderDetail];
    $input.prop('disabled', !isAllowed);
    if (!isAllowed) {
      $input.val('');
    }
    $input.closest('td').toggleClass('text-muted', !isAllowed);
  });

  $('.credit-product-quantity-input').val('0');
  $('#credit_shipping_adjustment').val('0').prop('disabled', true);
  setCreditAdjustment('cartRule', 0, 0);
  setCreditAdjustment('fee', 0, 0);
  updateCreditCartRuleAdjustmentState();
  updateCreditFeeAdjustmentState();
  updateCreditTotals();
}

function getCreditEnteredTotals() {
  var productsTotal = 0;

  $('.credit-product-amount-input').each(function () {
    var $input = $(this);
    if ($input.prop('disabled')) {
      return;
    }

    var amount = roundCreditAmount(parseCreditNumber($input.val()));
    var max = parseCreditNumber($input.data('credit-max'));

    if (max > 0 && amount > max) {
      amount = max;
      $input.val(formatCreditInput(amount));
    }

    productsTotal += amount;
  });

  return {
    products: roundCreditAmount(productsTotal),
    shippingAdjustment: $('#credit_shipping_adjustment').prop('disabled')
      ? 0
      : roundCreditSignedAmount(parseCreditNumber($('#credit_shipping_adjustment').val()))
  };
}

function refreshSuggestedCreditAdjustments(preserveManualSource) {
  var totals = getCreditEnteredTotals();
  updateCreditCartRuleAdjustmentState();

  if (isServiceCaseCreditReason()) {
    setCreditAdjustment('cartRule', 0, 0);
    setCreditAdjustment('fee', 0, 0);
    updateCreditFeeAdjustmentState();
    return;
  }

  if (!creditAdjustmentState.cartRule.manual) {
    if (!applySuggestedCancellationCartRuleAdjustment()) {
      setCreditAdjustment('cartRule', totals.products * normalizeCreditRate(getDefaultCreditCartRuleRate()) / 100, totals.products);
    }
  } else if (creditAdjustmentState.cartRule.source === 'rate') {
    setCreditAdjustmentFromRate('cartRule', totals.products, preserveManualSource);
  } else {
    setCreditAdjustmentFromAmount('cartRule', totals.products, preserveManualSource);
  }

  var cartRuleAdjustment = Math.min(totals.products, roundCreditAmount(getCreditAdjustmentAmount('cartRule')));
  var subtotalAfterCartRule = Math.max(0, totals.products + totals.shippingAdjustment - cartRuleAdjustment);
  updateCreditFeeAdjustmentState();
  if (isNoCreditRefundMethod()) {
    return;
  }

  if (!creditAdjustmentState.fee.manual) {
    if (!applySuggestedCancellationFee()) {
      setCreditAdjustment('fee', subtotalAfterCartRule * normalizeCreditRate(getDefaultCreditFeeRate()) / 100, subtotalAfterCartRule);
    }
  } else if (creditAdjustmentState.fee.source === 'rate') {
    setCreditAdjustmentFromRate('fee', subtotalAfterCartRule, preserveManualSource);
  } else {
    setCreditAdjustmentFromAmount('fee', subtotalAfterCartRule, preserveManualSource);
  }
}

function updateCreditTotals(preserveManualSource) {
  var totals = getCreditEnteredTotals();
  updateCreditCartRuleAdjustmentState();
  if (isServiceCaseCreditReason()) {
    setCreditAdjustment('cartRule', 0, 0);
    setCreditAdjustment('fee', 0, 0);
  }

  var cartRuleAdjustment = Math.min(
    totals.products,
    roundCreditAmount(getCreditAdjustmentAmount('cartRule'))
  );
  setCreditAdjustment('cartRule', cartRuleAdjustment, totals.products, preserveManualSource);

  var subtotalAfterCartRule = Math.max(0, totals.products + totals.shippingAdjustment - cartRuleAdjustment);
  var feeAdjustment = isNoCreditRefundMethod()
    ? 0
    : Math.min(
      subtotalAfterCartRule,
      roundCreditAmount(getCreditAdjustmentAmount('fee'))
    );
  var creditTotal = Math.max(0, subtotalAfterCartRule - feeAdjustment);
  setCreditAdjustment('fee', feeAdjustment, subtotalAfterCartRule, preserveManualSource);
  updateCreditFeeAdjustmentState();

  $('#credit_products_total_display').text(formatCreditDisplay(totals.products));
  $('#credit_subtotal_display').text(formatCreditDisplay(subtotalAfterCartRule));
  $('#credit_total_display').text(formatCreditRefundTotalDisplay(creditTotal));
  updateOriginalPaymentRefundConfirmation(creditTotal);
}

function getActiveCreditSuggestion() {
  var reason = $('#reason_entity_type').val();
  var idEntity = $('#reason_id_entity').val();
  return window.orderCreditSuggestions && window.orderCreditSuggestions[reason]
    ? window.orderCreditSuggestions[reason][idEntity] || null
    : null;
}

function applySuggestedCancellationCartRuleAdjustment() {
  var suggestion = getActiveCreditSuggestion();
  if (
    $('#reason_entity_type').val() !== 'cancellation'
    || !suggestion
    || !suggestion.cart_rule_adjustment
    || typeof suggestion.cart_rule_adjustment.amount === 'undefined'
  ) {
    return false;
  }

  creditAdjustmentState.cartRule.manual = false;
  setCreditAdjustment(
    'cartRule',
    suggestion.cart_rule_adjustment.amount,
    getCreditEnteredTotals().products
  );
  return true;
}

function applySuggestedCancellationFee() {
  var suggestion = getActiveCreditSuggestion();
  if (!suggestion || !suggestion.fee_adjustments) {
    return false;
  }
  var methodSuggestion = suggestion.fee_adjustments[getCreditRefundMethod()];
  if (!methodSuggestion || typeof methodSuggestion.amount === 'undefined') {
    return false;
  }
  creditAdjustmentState.fee.manual = false;
  var totals = getCreditEnteredTotals();
  var cartRule = Math.min(totals.products, roundCreditAmount(getCreditAdjustmentAmount('cartRule')));
  var feeBase = Math.max(0, totals.products + totals.shippingAdjustment - cartRule);
  setCreditAdjustment('fee', methodSuggestion.amount, feeBase);
  return true;
}

function applyCreditUrlPrefill() {
  var prefill = window.orderCreditPrefill || {};
  if (!prefill.reason || !prefill.entity) {
    return;
  }
  $('#desc-order-partial_refund, .order-credit-button').first().trigger('click');
  $('#reason_entity_type').val(prefill.reason);
  updateCreditReasonEntityOptions();
  $('#reason_id_entity').val(String(prefill.entity));
  if (prefill.refundMethod) {
    $('#credit_refund_method').val(prefill.refundMethod);
  }
  applyCreditReasonSuggestion();
  applySuggestedCancellationFee();
  updateCreditTotals();
}

function checkPartialRefundProductAmount(it) {
  var $input = $(it);
  var amount = roundCreditAmount(parseCreditNumber($input.val()));
  var max = parseCreditNumber($input.data('credit-max'));
  if (max > 0 && amount > max) {
    amount = max;
  }
  $input.val(amount > 0 ? formatCreditInput(amount) : '');
  refreshSuggestedCreditAdjustments();
  updateCreditTotals();
}
