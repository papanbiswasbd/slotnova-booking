/**
 * SlotNova Deposits Extension - Frontend JavaScript
 *
 * @package SlotNova\Extensions\Deposits
 */

document.addEventListener('DOMContentLoaded', function() {
	'use strict';

	var box = document.getElementById('slotnova-payment-options-box');
	if (!box) return;

	var depositType = box.getAttribute('data-deposit-type');
	var depositAmount = parseFloat(box.getAttribute('data-deposit-amount') || '0');
	var currencySymbol = box.getAttribute('data-currency-symbol') || '$';
	var primaryColor = box.getAttribute('data-primary-color') || '#2271b1';

	function getSelectedServicePrice() {
		var serviceInput = document.getElementById('slotnova_service');
		if (serviceInput) {
			var inputPrice = parseFloat(serviceInput.getAttribute('data-price'));
			if (!isNaN(inputPrice) && inputPrice > 0) {
				return inputPrice;
			}
		}

		var selectedOpt = document.querySelector('.slotnova-select-option.selected, .slotnova-select-option.active');
		if (selectedOpt && selectedOpt.getAttribute('data-price')) {
			var optPrice = parseFloat(selectedOpt.getAttribute('data-price'));
			if (!isNaN(optPrice) && optPrice > 0) return optPrice;
		}

		var summaryPriceEl = document.getElementById('summary-service-price');
		if (summaryPriceEl && summaryPriceEl.getAttribute('data-default-price')) {
			var defPrice = parseFloat(summaryPriceEl.getAttribute('data-default-price'));
			if (!isNaN(defPrice) && defPrice > 0) return defPrice;
		}

		var priceInput = document.getElementById('slotnova_calculated_price');
		if (priceInput && priceInput.value) {
			return parseFloat(priceInput.value) || 0;
		}

		return 0;
	}

	function updateDepositSummary() {
		var servicePrice = getSelectedServicePrice();
		var cardFullBadge = document.getElementById('slotnova-card-full-badge');
		var cardDepositBadge = document.getElementById('slotnova-card-deposit-badge');

		if (cardFullBadge) {
			cardFullBadge.textContent = currencySymbol + servicePrice.toFixed(2);
		}

		var depositVal = 0;
		if (depositType === 'percentage') {
			depositVal = (servicePrice * depositAmount) / 100;
		} else {
			depositVal = Math.min(depositAmount, servicePrice);
		}

		if (cardDepositBadge) {
			cardDepositBadge.textContent = currencySymbol + depositVal.toFixed(2);
		}

		var selectedRadio = document.querySelector('input[name="slotnova_payment_type"]:checked');
		var payType = selectedRadio ? selectedRadio.value : 'full';

		var optFull = document.getElementById('slotnova-opt-full');
		var optDeposit = document.getElementById('slotnova-opt-deposit');

		if (payType === 'deposit') {
			if (optDeposit) {
				optDeposit.style.borderColor = primaryColor;
				optDeposit.classList.add('active');
			}
			if (optFull) {
				optFull.style.borderColor = '#e2e8f0';
				optFull.classList.remove('active');
			}
		} else {
			if (optFull) {
				optFull.style.borderColor = primaryColor;
				optFull.classList.add('active');
			}
			if (optDeposit) {
				optDeposit.style.borderColor = '#e2e8f0';
				optDeposit.classList.remove('active');
			}
		}

		var dueVal = Math.max(0, servicePrice - depositVal);

		// Correct element IDs matching class-slotnova-frontend.php HTML output
		var totalAmountEl = document.getElementById('summary-service-price');
		var valPayable    = document.getElementById('summary-payable-amount');
		var valDue        = document.getElementById('summary-due-amount');
		var rowPayable    = document.getElementById('summary-payable-row');
		var rowDue        = document.getElementById('summary-due-row');

		// Total Amount always shows the full service price
		if (totalAmountEl) {
			totalAmountEl.textContent = servicePrice > 0 ? currencySymbol + servicePrice.toFixed(2) : '-';
		}

		if (payType === 'deposit') {
			// Show deposit breakdown rows below
			if (valPayable) valPayable.textContent = currencySymbol + depositVal.toFixed(2);
			if (valDue)     valDue.textContent     = currencySymbol + dueVal.toFixed(2);

			if (rowPayable) {
				rowPayable.classList.remove('slotnova-is-hidden');
				rowPayable.style.display = 'flex';
			}
			if (rowDue) {
				rowDue.classList.remove('slotnova-is-hidden');
				rowDue.style.display = 'flex';
			}
		} else {
			// Hide breakdown rows for Full Payment
			if (rowPayable) {
				rowPayable.classList.add('slotnova-is-hidden');
				rowPayable.style.display = 'none';
			}
			if (rowDue) {
				rowDue.classList.add('slotnova-is-hidden');
				rowDue.style.display = 'none';
			}
		}
	}

	function checkRevealPaymentBox() {
		var serviceInput = document.getElementById('slotnova_service');
		var employeeInput = document.getElementById('slotnova_employee');
		var dateInput = document.getElementById('slotnova_booking_date');
		var timeInput = document.getElementById('slotnova_booking_time');
		var summaryBox = document.getElementById('slotnova-summary');

		var hasSelection = (serviceInput && serviceInput.value) || (employeeInput && employeeInput.value) || (dateInput && dateInput.value) || (timeInput && timeInput.value) || (summaryBox && summaryBox.style.display === 'block' && !summaryBox.classList.contains('slotnova-is-hidden'));

		if (hasSelection) {
			box.style.display = 'block';
			box.classList.remove('slotnova-is-hidden');
		}
	}

	document.querySelectorAll('.slotnova-pay-radio').forEach(function(radio) {
		radio.addEventListener('change', function() {
			updateDepositSummary();
		});
	});

	document.querySelectorAll('.slotnova-select-option, .slotnova-time-pill').forEach(function(el) {
		el.addEventListener('click', function() {
			setTimeout(function() {
				checkRevealPaymentBox();
				updateDepositSummary();
			}, 50);
		});
	});

	var summaryBox = document.getElementById('slotnova-summary');
	if (summaryBox) {
		var observer = new MutationObserver(function() {
			checkRevealPaymentBox();
			updateDepositSummary();
		});
		observer.observe(summaryBox, { attributes: true, attributeFilter: ['style', 'class'] });
	}

	var serviceInput = document.getElementById('slotnova_service');
	if (serviceInput) {
		var serviceObserver = new MutationObserver(function() {
			checkRevealPaymentBox();
			updateDepositSummary();
		});
		serviceObserver.observe(serviceInput, { attributes: true, attributeFilter: ['value', 'data-price'] });
	}

	checkRevealPaymentBox();
	updateDepositSummary();
});
