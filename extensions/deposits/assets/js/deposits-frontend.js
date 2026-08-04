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
		var serviceSelect = document.getElementById('slotnova_service');
		if (!serviceSelect) return 0;

		var selectedOpt = serviceSelect.options ? serviceSelect.options[serviceSelect.selectedIndex] : null;
		if (!selectedOpt) {
			selectedOpt = document.querySelector('.slotnova-select-option.selected');
		}

		if (selectedOpt && selectedOpt.getAttribute('data-price')) {
			return parseFloat(selectedOpt.getAttribute('data-price')) || 0;
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
		var valPayable = document.getElementById('slotnova-summary-payable-val');
		var valDue = document.getElementById('slotnova-summary-due-val');
		var rowPayable = document.getElementById('slotnova-summary-row-payable');
		var rowDue = document.getElementById('slotnova-summary-row-due');

		if (payType === 'deposit') {
			if (valPayable) valPayable.textContent = currencySymbol + depositVal.toFixed(2);
			if (valDue) valDue.textContent = currencySymbol + dueVal.toFixed(2);

			if (rowPayable) rowPayable.classList.remove('slotnova-is-hidden');
			if (rowDue) rowDue.classList.remove('slotnova-is-hidden');
		} else {
			if (rowPayable) rowPayable.classList.add('slotnova-is-hidden');
			if (rowDue) rowDue.classList.add('slotnova-is-hidden');
		}
	}

	function checkRevealPaymentBox() {
		var serviceInput = document.getElementById('slotnova_service');
		if (serviceInput && serviceInput.value) {
			box.style.display = 'block';
			box.classList.remove('slotnova-is-hidden');
		}
	}

	document.querySelectorAll('.slotnova-pay-radio').forEach(function(radio) {
		radio.addEventListener('change', function() {
			updateDepositSummary();
		});
	});

	document.querySelectorAll('.slotnova-select-option').forEach(function(opt) {
		opt.addEventListener('click', function() {
			setTimeout(function() {
				checkRevealPaymentBox();
				updateDepositSummary();
			}, 50);
		});
	});

	var serviceInput = document.getElementById('slotnova_service');
	if (serviceInput) {
		var observer = new MutationObserver(function() {
			checkRevealPaymentBox();
			updateDepositSummary();
		});
		observer.observe(serviceInput, { attributes: true, attributeFilter: ['value'] });
	}

	checkRevealPaymentBox();
	updateDepositSummary();
});
