(function($) {
    'use strict';

    function getModalPayload($modal, $button) {
        var status = $modal.find('.bsync-auction-payment-status:checked').val() || 'unpaid';

        return {
            action: 'bsync_auction_save_buyer_receipt_payment',
            nonce: BsyncAuctionBuyerReceipts.nonce,
            buyer_id: $button.data('buyer-id'),
            auction_id: $button.data('auction-id'),
            payment_status: status,
            pay_cash: $modal.find('.bsync-auction-pay-cash').is(':checked') ? 1 : 0,
            pay_check: $modal.find('.bsync-auction-pay-check').is(':checked') ? 1 : 0,
            pay_card: $modal.find('.bsync-auction-pay-card').is(':checked') ? 1 : 0,
            check_number: $modal.find('.bsync-auction-check-number').val()
        };
    }

    function updatePaidStatusLabel(buyerId, label) {
        if (!buyerId || !label) {
            return;
        }

        $('.bsync-auction-paid-status[data-buyer-id="' + buyerId + '"]').text(label);
    }

    function setEmailStatus($container, message, isError) {
        var $status = $container.find('.bsync-auction-email-status');
        $status.text(message || '');
        $status.css('color', isError ? '#b91c1c' : '#166534');
    }


    $(document).on('click', '.bsync-auction-open-receipt', function() {
        var modalId = $(this).data('modal-id');
        if (!modalId) {
            return;
        }
        $('#' + modalId).show();
    });

    $(document).on('click', '.bsync-auction-close-modal', function() {
        $(this).closest('.bsync-auction-receipt-modal').hide();
    });

    $(document).on('click', '.bsync-auction-receipt-modal', function(e) {
        if ($(e.target).is('.bsync-auction-receipt-modal')) {
            $(this).hide();
        }
    });

    $(document).on('click', '.bsync-auction-print-receipt', function() {
        var $modal = $(this).closest('.bsync-auction-receipt-modal');
        var html = $modal.find('.bsync-auction-receipt-printable').html();
        var printWindow = window.open('', '_blank', 'width=900,height=700');
        var printTitle = BsyncAuctionBuyerReceipts.printTitle || 'Receipt';
        var cssUrl = BsyncAuctionBuyerReceipts.printCssUrl || '';

        if (!printWindow) {
            return;
        }

        printWindow.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + printTitle + '</title>');
        if (cssUrl) {
            printWindow.document.write('<link rel="stylesheet" href="' + cssUrl + '" media="all">');
        }
        printWindow.document.write('</head><body class="bsync-auction-print-page"><div class="bsync-auction-receipt-printable">' + html + '</div></body></html>');
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    });

    $(document).on('click', '.bsync-auction-email-receipt', function() {
        var $button = $(this);
        var $modal = $button.closest('.bsync-auction-receipt-modal');

        var paymentPayload = getModalPayload($modal, $button);

        var payload = {
            action: 'bsync_auction_send_buyer_receipt',
            nonce: BsyncAuctionBuyerReceipts.nonce,
            buyer_id: $button.data('buyer-id'),
            item_ids: $button.data('item-ids'),
            auction_id: $button.data('auction-id'),
            payment_status: paymentPayload.payment_status,
            pay_cash: paymentPayload.pay_cash,
            pay_check: paymentPayload.pay_check,
            pay_card: paymentPayload.pay_card,
            check_number: paymentPayload.check_number
        };

        setEmailStatus($modal, BsyncAuctionBuyerReceipts.sending, false);
        $button.prop('disabled', true);

        $.post(BsyncAuctionBuyerReceipts.ajaxUrl, payload)
            .done(function(response) {
                if (response && response.success) {
                    var msg = response.data && response.data.message ? response.data.message : BsyncAuctionBuyerReceipts.sent;
                    setEmailStatus($modal, msg, false);
                    if (response.data && response.data.paid_label) {
                        updatePaidStatusLabel($button.data('buyer-id'), response.data.paid_label);
                    }
                } else {
                    var err = response && response.data && response.data.message ? response.data.message : BsyncAuctionBuyerReceipts.failed;
                    setEmailStatus($modal, err, true);
                }
            })
            .fail(function(xhr) {
                var err = BsyncAuctionBuyerReceipts.failed;
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    err = xhr.responseJSON.data.message;
                }
                setEmailStatus($modal, err, true);
            })
            .always(function() {
                $button.prop('disabled', false);
            });
    });

    $(document).on('click', '.bsync-auction-save-payment', function() {
        var $button = $(this);
        var $modal = $button.closest('.bsync-auction-receipt-modal');
        var payload = getModalPayload($modal, $button);

        setEmailStatus($modal, BsyncAuctionBuyerReceipts.saving, false);
        $button.prop('disabled', true);

        $.post(BsyncAuctionBuyerReceipts.ajaxUrl, payload)
            .done(function(response) {
                if (response && response.success) {
                    var msg = response.data && response.data.message ? response.data.message : BsyncAuctionBuyerReceipts.saved;
                    setEmailStatus($modal, msg, false);
                    if (response.data && response.data.paid_label) {
                        updatePaidStatusLabel($button.data('buyer-id'), response.data.paid_label);
                    }
                } else {
                    var err = response && response.data && response.data.message ? response.data.message : BsyncAuctionBuyerReceipts.saveFailed;
                    setEmailStatus($modal, err, true);
                }
            })
            .fail(function(xhr) {
                var err = BsyncAuctionBuyerReceipts.saveFailed;
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    err = xhr.responseJSON.data.message;
                }
                setEmailStatus($modal, err, true);
            })
            .always(function() {
                $button.prop('disabled', false);
            });
    });

})(jQuery);
