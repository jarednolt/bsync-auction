(function($) {
    'use strict';

    function setRowMessage($row, message, isError) {
        var $status = $row.find('.bsync-auction-save-status');
        $status.text(message);
        $status.css('color', isError ? '#b91c1c' : '#166534');
    }

    $(document).on('click', '.bsync-auction-save-row', function() {
        var $button = $(this);
        var $row = $button.closest('tr');
        var itemId = $row.data('item-id');

        if (!itemId) {
            setRowMessage($row, BsyncAuctionGrid.failed, true);
            return;
        }

        var payload = {
            action: 'bsync_auction_save_item_row',
            nonce: BsyncAuctionGrid.nonce,
            item_id: itemId,
            order_number: $row.find('[data-field="order_number"]').val(),
            buyer_number: $row.find('[data-field="buyer_number"]').val(),
            status: $row.find('[data-field="status"]').val(),
            opening_bid: $row.find('[data-field="opening_bid"]').val(),
            current_bid: $row.find('[data-field="current_bid"]').val(),
            sold_price: $row.find('[data-field="sold_price"]').val()
        };

        $button.prop('disabled', true);
        setRowMessage($row, BsyncAuctionGrid.saving, false);

        $.post(BsyncAuctionGrid.ajaxUrl, payload)
            .done(function(response) {
                if (response && response.success) {
                    setRowMessage($row, BsyncAuctionGrid.saved, false);

                    if (response.data) {
                        if (typeof response.data.orderNumber !== 'undefined') {
                            $row.find('[data-field="order_number"]').val(response.data.orderNumber);
                        }

                        if (typeof response.data.buyerNumber !== 'undefined') {
                            $row.find('[data-field="buyer_number"]').val(response.data.buyerNumber);
                        }

                        if (response.data.buyerDisplayName) {
                            var $linkedUser = $row.find('.bsync-auction-linked-user');
                            var template = $linkedUser.data('template') || 'Linked user: %s';
                            $linkedUser.text(template.replace('%s', response.data.buyerDisplayName));
                        }
                    }
                } else {
                    var message = response && response.data && response.data.message ? response.data.message : BsyncAuctionGrid.failed;
                    setRowMessage($row, message, true);
                }
            })
            .fail(function(xhr) {
                var message = BsyncAuctionGrid.failed;
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }
                setRowMessage($row, message, true);
            })
            .always(function() {
                $button.prop('disabled', false);
            });
    });
})(jQuery);
