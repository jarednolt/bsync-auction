(function($) {
    'use strict';

    var $orderInput = null;
    var $auctionInput = null;
    var $alert = null;
    var debounceTimer = null;

    function clearAlert() {
        if (!$alert || $alert.length < 1) {
            return;
        }

        $alert.hide();
        $alert.find('.bsync-auction-order-duplicate-text').text('');
        $alert.find('.bsync-auction-order-apply-suggested').text('').attr('data-suggested', '');
    }

    function showAlert(message, suggestedNext) {
        if (!$alert || $alert.length < 1) {
            return;
        }

        $alert.find('.bsync-auction-order-duplicate-text').text(message || '');

        var $apply = $alert.find('.bsync-auction-order-apply-suggested');
        if (suggestedNext) {
            $apply.text(BsyncAuctionItemOrder.useSuggested || 'Use suggested number')
                .attr('data-suggested', String(suggestedNext))
                .show();
        } else {
            $apply.text('').attr('data-suggested', '').hide();
        }

        $alert.show();
    }

    function checkDuplicateNow() {
        if (!$orderInput || !$auctionInput || !$alert) {
            return;
        }

        var orderNumber = String($orderInput.val() || '');
        var auctionId = parseInt($auctionInput.val(), 10) || 0;

        if (!orderNumber || auctionId < 1) {
            clearAlert();
            return;
        }

        $.post(BsyncAuctionItemOrder.ajaxUrl, {
            action: 'bsync_auction_check_order_number_duplicate',
            nonce: BsyncAuctionItemOrder.nonce,
            item_id: parseInt(BsyncAuctionItemOrder.itemId, 10) || 0,
            auction_id: auctionId,
            order_number: orderNumber
        }).done(function(response) {
            if (!response || !response.success || !response.data || !response.data.isDuplicate) {
                clearAlert();
                return;
            }

            showAlert(response.data.message || '', response.data.suggestedNext || '');
        }).fail(function() {
            showAlert(BsyncAuctionItemOrder.checkFailed || 'Could not validate order number right now.', '');
        });
    }

    function queueCheck() {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(checkDuplicateNow, 250);
    }

    $(function() {
        $orderInput = $('#bsync_auction_order_number');
        $auctionInput = $('#bsync_auction_id');
        $alert = $('#bsync_auction_order_duplicate_alert');

        if ($orderInput.length < 1 || $auctionInput.length < 1 || $alert.length < 1) {
            return;
        }

        $orderInput.on('input change blur', queueCheck);
        $auctionInput.on('change', queueCheck);

        $(document).on('click', '.bsync-auction-order-apply-suggested', function() {
            var suggested = String($(this).attr('data-suggested') || '');
            if (!suggested) {
                return;
            }

            $orderInput.val(suggested).trigger('change');
            clearAlert();
        });

        queueCheck();
    });
})(jQuery);
