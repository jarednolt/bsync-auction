(function($) {
    'use strict';

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderStatusOptions(selectedStatus) {
        var html = '';
        var statuses = BsyncAuctionGrid.statuses || {};

        Object.keys(statuses).forEach(function(key) {
            var selected = key === selectedStatus ? ' selected="selected"' : '';
            html += '<option value="' + escapeHtml(key) + '"' + selected + '>' + escapeHtml(statuses[key]) + '</option>';
        });

        return html;
    }

    function buildRowHtml(item) {
        var buyerTemplate = BsyncAuctionGrid.linkedUserTemplate || 'Linked user: %s';
        var buyerLabel = (item.buyerDisplay || BsyncAuctionGrid.noBuyer || 'No Buyer');

        return ''
            + '<tr data-item-id="' + escapeHtml(item.itemId) + '" data-auction-id="' + escapeHtml(item.auctionId) + '" data-auction-name="' + escapeHtml(item.auctionName) + '">'
            + '<td>' + escapeHtml(item.itemNumber) + '</td>'
            + '<td><input type="number" class="small-text bsync-auction-field" data-field="order_number" min="1" step="1" value="' + escapeHtml(item.orderNumber) + '" /></td>'
            + '<td><a href="' + escapeHtml(item.editUrl) + '">' + escapeHtml(item.title) + '</a></td>'
            + '<td>' + escapeHtml(item.auctionName) + '</td>'
            + '<td>'
            + '<input type="text" class="regular-text bsync-auction-field" data-field="buyer_number" value="' + escapeHtml(item.buyerNumber || '') + '" placeholder="Enter buyer number" />'
            + '<br /><small class="bsync-auction-linked-user" data-template="' + escapeHtml(buyerTemplate) + '">' + escapeHtml(buyerTemplate.replace('%s', buyerLabel)) + '</small>'
            + '</td>'
            + '<td><select class="bsync-auction-field" data-field="status">' + renderStatusOptions(item.status || 'available') + '</select></td>'
            + '<td><input type="number" class="small-text bsync-auction-field" data-field="opening_bid" min="0" step="0.01" value="' + escapeHtml(item.openingBid || '0.00') + '" /></td>'
            + '<td><input type="number" class="small-text bsync-auction-field" data-field="current_bid" min="0" step="0.01" value="' + escapeHtml(item.currentBid || '0.00') + '" /></td>'
            + '<td><input type="number" class="small-text bsync-auction-field" data-field="sold_price" min="0" step="0.01" value="' + escapeHtml(item.soldPrice || '0.00') + '" /></td>'
            + '<td><button type="button" class="button button-primary bsync-auction-save-row">Save Row</button> <span class="bsync-auction-save-status" aria-live="polite"></span></td>'
            + '</tr>';
    }

    function removeQuickAddButton($row) {
        $row.find('.bsync-auction-quick-add').remove();
    }

    function renderQuickAddButton($row) {
        removeQuickAddButton($row);

        var $actionCell = $row.find('td').last();
        if ($actionCell.length < 1) {
            return;
        }

        var label = BsyncAuctionGrid.quickAddButton || 'Quick Add Next';
        var $button = $('<button type="button" class="button button-secondary bsync-auction-quick-add" style="margin-left:8px;"></button>');
        $button.text(label);
        $actionCell.append($button);
    }

    function maybeShowQuickAdd($row) {
        var status = String($row.find('[data-field="status"]').val() || '').toLowerCase();
        if (status === 'sold') {
            renderQuickAddButton($row);
            return;
        }

        removeQuickAddButton($row);
    }

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

                        if (typeof response.data.status !== 'undefined') {
                            $row.find('[data-field="status"]').val(response.data.status);
                        }

                        if (response.data.buyerDisplayName) {
                            var $linkedUser = $row.find('.bsync-auction-linked-user');
                            var template = $linkedUser.data('template') || 'Linked user: %s';
                            $linkedUser.text(template.replace('%s', response.data.buyerDisplayName));
                        }
                    }

                    maybeShowQuickAdd($row);
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

    $(document).on('click', '.bsync-auction-quick-add', function() {
        var $button = $(this);
        var $row = $button.closest('tr');
        var auctionId = parseInt($row.data('auction-id'), 10) || 0;

        if (auctionId < 1) {
            setRowMessage($row, BsyncAuctionGrid.quickAddMissingCtx || BsyncAuctionGrid.failed, true);
            return;
        }

        var currentOrder = parseInt($row.find('[data-field="order_number"]').val(), 10) || 0;
        var nextOrder = currentOrder > 0 ? currentOrder + 1 : 1;
        var promptText = BsyncAuctionGrid.quickAddPrompt || 'Enter the new item title:';
        var title = window.prompt(promptText, '');

        if (title === null) {
            return;
        }

        title = String(title).trim();
        if (!title) {
            setRowMessage($row, BsyncAuctionGrid.failed, true);
            return;
        }

        $button.prop('disabled', true);

        $.post(BsyncAuctionGrid.ajaxUrl, {
            action: 'bsync_auction_quick_add_item',
            nonce: BsyncAuctionGrid.quickAddNonce,
            auction_id: auctionId,
            order_number: nextOrder,
            title: title
        })
            .done(function(response) {
                if (!response || !response.success || !response.data) {
                    var errorMessage = response && response.data && response.data.message ? response.data.message : BsyncAuctionGrid.failed;
                    setRowMessage($row, errorMessage, true);
                    return;
                }

                var newRowHtml = buildRowHtml(response.data);
                var $newRow = $(newRowHtml);
                $row.after($newRow);
                setRowMessage($row, BsyncAuctionGrid.quickAddAdded || BsyncAuctionGrid.saved, false);
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
