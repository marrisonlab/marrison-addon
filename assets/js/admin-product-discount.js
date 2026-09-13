jQuery(function($) {
	var $button = $('#marrison-product-discount-run-batch');
	var $progress = $('#marrison-product-discount-progress');
	var $fill = $('#marrison-product-discount-progress-fill');
	var $text = $('#marrison-product-discount-progress-text');
	var $status = $('#marrison-product-discount-status');
	var isRunning = false;
	var total = 0;

	$button.on('click', function(e) {
		e.preventDefault();

		if (isRunning) {
			return;
		}

		if (!window.confirm(marrisonProductDiscount.confirmMessage)) {
			return;
		}

		startBatch();
	});

	function startBatch() {
		isRunning = true;
		total = 0;
		$button.prop('disabled', true);
		$progress.show();
		updateProgress(0, 0);
		$status.text(marrisonProductDiscount.startingText).removeClass('error');

		$.ajax({
			url: marrisonProductDiscount.ajaxUrl,
			type: 'POST',
			data: {
				action: 'marrison_product_discount_batch_start',
				nonce: marrisonProductDiscount.nonce
			}
		}).done(function(response) {
			if (!response || !response.success) {
				finishWithError(response);
				return;
			}

			total = parseInt(response.data.total, 10) || 0;

			if (response.data.done || total === 0) {
				updateProgress(100, 0);
				finish(marrisonProductDiscount.emptyText);
				return;
			}

			processPage(1);
		}).fail(function(response) {
			finishWithError(response);
		});
	}

	function processPage(page) {
		if (!isRunning) {
			return;
		}

		$.ajax({
			url: marrisonProductDiscount.ajaxUrl,
			type: 'POST',
			data: {
				action: 'marrison_product_discount_batch_step',
				nonce: marrisonProductDiscount.nonce,
				page: page
			}
		}).done(function(response) {
			if (!response || !response.success) {
				finishWithError(response);
				return;
			}

			var processed = parseInt(response.data.processed, 10) || 0;
			var responseTotal = parseInt(response.data.total, 10) || total;
			total = responseTotal;
			updateProgress(processed, total);

			if (response.data.done) {
				finish(marrisonProductDiscount.doneText);
				return;
			}

			processPage(parseInt(response.data.nextPage, 10) || page + 1);
		}).fail(function(response) {
			finishWithError(response);
		});
	}

	function updateProgress(processed, totalItems) {
		var percentage = totalItems > 0 ? Math.min(100, Math.round((processed / totalItems) * 100)) : processed;
		$fill.css('width', percentage + '%');
		$text.text(totalItems > 0 ? percentage + '% (' + processed + '/' + totalItems + ')' : percentage + '%');
	}

	function finish(message) {
		isRunning = false;
		$button.prop('disabled', false);
		$status.text(message).removeClass('error');
		$fill.css('width', '100%');
	}

	function finishWithError(response) {
		var message = marrisonProductDiscount.errorText;

		if (response && response.responseJSON && response.responseJSON.data && response.responseJSON.data.message) {
			message = response.responseJSON.data.message;
		} else if (response && response.data && response.data.message) {
			message = response.data.message;
		}

		isRunning = false;
		$button.prop('disabled', false);
		$status.text(message).addClass('error');
	}
});
