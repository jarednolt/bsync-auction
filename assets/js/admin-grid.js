(function($) {
    'use strict';

    var $addItemModal = null;
    var $addItemForm = null;
    var addItemAnchorRow = null;

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
            + '<td><input type="number" class="small-text bsync-auction-field" data-field="order_number" min="1" step="0.01" value="' + escapeHtml(item.orderNumber) + '" /></td>'
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
                + '<td><button type="button" class="button button-primary bsync-auction-save-row">Save Row</button> <span class="bsync-auction-save-status" aria-live="polite"></span>'
                + '<div class="bsync-auction-duplicate-alert" style="display:none;">'
                + '<span class="bsync-auction-duplicate-text" aria-live="polite"></span> '
                + '<button type="button" class="button-link bsync-auction-apply-suggested" data-suggested=""></button>'
                + '</div></td>'
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

    function clearDuplicateAlert($row) {
        var $alert = $row.find('.bsync-auction-duplicate-alert');
        $alert.hide();
        $alert.find('.bsync-auction-duplicate-text').text('');
        $alert.find('.bsync-auction-apply-suggested').text('').attr('data-suggested', '');
    }

    function showDuplicateAlert($row, message, suggestedNext) {
        var $alert = $row.find('.bsync-auction-duplicate-alert');
        if ($alert.length < 1) {
            return;
        }

        $alert.find('.bsync-auction-duplicate-text').text(message || '');

        var $apply = $alert.find('.bsync-auction-apply-suggested');
        if (suggestedNext) {
            $apply.text(BsyncAuctionGrid.useSuggested || 'Use suggested number')
                .attr('data-suggested', String(suggestedNext))
                .show();
        } else {
            $apply.text('').attr('data-suggested', '').hide();
        }

        $alert.show();
    }

    function checkRowOrderDuplicate($row) {
        var itemId = parseInt($row.data('item-id'), 10) || 0;
        var auctionId = parseInt($row.data('auction-id'), 10) || 0;
        var orderNumber = String($row.find('[data-field="order_number"]').val() || '');

        if (itemId < 1 || auctionId < 1 || !orderNumber) {
            clearDuplicateAlert($row);
            return;
        }

        $.post(BsyncAuctionGrid.ajaxUrl, {
            action: 'bsync_auction_check_order_number_duplicate',
            nonce: BsyncAuctionGrid.checkNonce,
            item_id: itemId,
            auction_id: auctionId,
            order_number: orderNumber
        }).done(function(response) {
            if (!response || !response.success || !response.data || !response.data.isDuplicate) {
                clearDuplicateAlert($row);
                return;
            }

            showDuplicateAlert($row, response.data.message || BsyncAuctionGrid.failed, response.data.suggestedNext || '');
        }).fail(function() {
            clearDuplicateAlert($row);
        });
    }

    function setAddItemMessage(message, isError) {
        if (!$addItemModal || $addItemModal.length < 1) {
            return;
        }

        var $status = $addItemModal.find('.bsync-auction-add-item-status');
        $status.text(message || '');
        $status.css('color', isError ? '#b91c1c' : '#166534');
    }

    function openAddItemModal(options) {
        options = options || {};

        if (!$addItemModal || $addItemModal.length < 1) {
            return;
        }

        addItemAnchorRow = options.anchorRow || null;

        $addItemForm.trigger('reset');
        setAddItemMessage('', false);

        if (options.auctionId) {
            $addItemForm.find('[name="auction_id"]').val(String(options.auctionId));
        }

        if (typeof options.orderNumber !== 'undefined' && options.orderNumber !== null && options.orderNumber !== '') {
            $addItemForm.find('[name="order_number"]').val(String(options.orderNumber));
        }

        $addItemModal.show();
        $addItemForm.find('[name="title"]').trigger('focus');
    }

    function closeAddItemModal() {
        if (!$addItemModal || $addItemModal.length < 1) {
            return;
        }

        $addItemModal.hide();
        addItemAnchorRow = null;
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
                    clearDuplicateAlert($row);
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
                    if (response && response.data && response.data.suggestedNext) {
                        showDuplicateAlert($row, message, response.data.suggestedNext);
                    }
                    setRowMessage($row, message, true);
                }
            })
            .fail(function(xhr) {
                var message = BsyncAuctionGrid.failed;
                var suggestedNext = '';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                    suggestedNext = xhr.responseJSON.data.suggestedNext || '';
                }
                if (suggestedNext) {
                    showDuplicateAlert($row, message, suggestedNext);
                }
                setRowMessage($row, message, true);
            })
            .always(function() {
                $button.prop('disabled', false);
            });
    });

    $(function() {
        $addItemModal = $('#bsync-auction-add-item-modal');
        $addItemForm = $('#bsync-auction-add-item-form');

        if ($addItemModal.length < 1 || $addItemForm.length < 1) {
            return;
        }

        $(document).on('click', '.bsync-auction-open-add-item', function() {
            var auctionId = parseInt($(this).data('auction-id'), 10) || 0;
            openAddItemModal({ auctionId: auctionId > 0 ? auctionId : '' });
        });

        $(document).on('click', '.bsync-auction-quick-add', function() {
            var $row = $(this).closest('tr');
            var auctionId = parseInt($row.data('auction-id'), 10) || 0;

            if (auctionId < 1) {
                setRowMessage($row, BsyncAuctionGrid.quickAddMissingCtx || BsyncAuctionGrid.failed, true);
                return;
            }

            var currentOrder = parseFloat($row.find('[data-field="order_number"]').val()) || 0;
            var suggestedOrder = currentOrder > 0 ? (Math.round((currentOrder + 0.01) * 100) / 100) : '';

            openAddItemModal({
                auctionId: auctionId,
                orderNumber: suggestedOrder,
                anchorRow: $row
            });
        });

        $(document).on('click', '.bsync-auction-close-add-item', function() {
            closeAddItemModal();
        });

        $(document).on('change blur', '[data-field="order_number"]', function() {
            var $row = $(this).closest('tr');
            checkRowOrderDuplicate($row);
        });

        $(document).on('click', '.bsync-auction-apply-suggested', function() {
            var suggested = String($(this).attr('data-suggested') || '');
            if (!suggested) {
                return;
            }

            var $row = $(this).closest('tr');
            $row.find('[data-field="order_number"]').val(suggested).trigger('change');
            clearDuplicateAlert($row);
            setRowMessage($row, '', false);
        });

        $(document).on('click', '#bsync-auction-add-item-modal', function(e) {
            if ($(e.target).is('#bsync-auction-add-item-modal')) {
                closeAddItemModal();
            }
        });

        $addItemForm.on('submit', function(e) {
            e.preventDefault();

            var $submit = $addItemForm.find('.bsync-auction-submit-add-item');
            var formData = new FormData($addItemForm.get(0));
            var chosenAuction = parseInt($addItemForm.find('[name="auction_id"]').val(), 10) || 0;

            formData.append('action', 'bsync_auction_quick_add_item');
            formData.append('nonce', BsyncAuctionGrid.quickAddNonce);

            $submit.prop('disabled', true);
            setAddItemMessage(BsyncAuctionGrid.quickAddSaving || BsyncAuctionGrid.saving, false);

            $.ajax({
                url: BsyncAuctionGrid.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
                .done(function(response) {
                    if (!response || !response.success || !response.data) {
                        var errMessage = response && response.data && response.data.message ? response.data.message : BsyncAuctionGrid.failed;
                        setAddItemMessage(errMessage, true);
                        return;
                    }

                    var activeFilter = parseInt($('#auction_id').val(), 10) || 0;
                    var inCurrentView = (activeFilter === 0 || activeFilter === chosenAuction);

                    if (inCurrentView) {
                        var $newRow = $(buildRowHtml(response.data));
                        if (addItemAnchorRow && addItemAnchorRow.length) {
                            addItemAnchorRow.after($newRow);
                            setRowMessage(addItemAnchorRow, BsyncAuctionGrid.quickAddAdded || BsyncAuctionGrid.saved, false);
                        } else {
                            $('.widefat.striped tbody').append($newRow);
                        }
                    }

                    closeAddItemModal();
                })
                .fail(function(xhr) {
                    var message = BsyncAuctionGrid.failed;
                    if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                    setAddItemMessage(message, true);
                })
                .always(function() {
                    $submit.prop('disabled', false);
                });
        });
    });
})(jQuery);
