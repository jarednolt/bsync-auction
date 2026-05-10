(function($) {
    'use strict';

    function setStatus($modal, message, isError) {
        var $status = $modal.find('.bsync-auction-assignment-status');
        $status.text(message || '');
        $status.css('color', isError ? '#b91c1c' : '#166534');
    }

    function refreshRows($modal, html) {
        if (!html) {
            return;
        }

        $modal.find('.bsync-auction-assignment-rows').html(html);
    }

    $(document).on('click', '.bsync-auction-open-assignments-modal', function() {
        var target = $(this).data('target');
        if (!target) {
            return;
        }

        $('#' + target).show();
    });

    $(document).on('click', '.bsync-auction-close-assignments-modal', function() {
        $(this).closest('.bsync-auction-assignments-modal').hide();
    });

    $(document).on('click', '.bsync-auction-assignments-modal', function(e) {
        if ($(e.target).is('.bsync-auction-assignments-modal')) {
            $(this).hide();
        }
    });

    $(document).on('click', '.bsync-auction-assignment-add', function() {
        var $button = $(this);
        var $modal = $button.closest('.bsync-auction-assignments-modal');
        var auctionId = $button.data('auction-id');
        var userId = $modal.find('.bsync-auction-assignment-user').val();
        var role = $modal.find('.bsync-auction-assignment-role').val();

        if (!auctionId || !userId || Number(userId) <= 0) {
            setStatus($modal, BsyncAuctionAssignments.saveFailed, true);
            return;
        }

        setStatus($modal, BsyncAuctionAssignments.saving, false);
        $button.prop('disabled', true);

        $.post(BsyncAuctionAssignments.ajaxUrl, {
            action: 'bsync_auction_assignment_add',
            nonce: BsyncAuctionAssignments.nonce,
            auction_id: auctionId,
            user_id: userId,
            assignment_role: role
        })
            .done(function(response) {
                if (response && response.success) {
                    refreshRows($modal, response.data && response.data.rowsHtml ? response.data.rowsHtml : '');
                    setStatus($modal, response.data && response.data.message ? response.data.message : BsyncAuctionAssignments.saved, false);
                } else {
                    var err = response && response.data && response.data.message ? response.data.message : BsyncAuctionAssignments.saveFailed;
                    setStatus($modal, err, true);
                }
            })
            .fail(function(xhr) {
                var err = BsyncAuctionAssignments.saveFailed;
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    err = xhr.responseJSON.data.message;
                }
                setStatus($modal, err, true);
            })
            .always(function() {
                $button.prop('disabled', false);
            });
    });

    $(document).on('click', '.bsync-auction-assignment-remove', function() {
        var $button = $(this);
        var $modal = $button.closest('.bsync-auction-assignments-modal');

        setStatus($modal, BsyncAuctionAssignments.removing, false);
        $button.prop('disabled', true);

        $.post(BsyncAuctionAssignments.ajaxUrl, {
            action: 'bsync_auction_assignment_remove',
            nonce: BsyncAuctionAssignments.nonce,
            auction_id: $button.data('auction-id'),
            user_id: $button.data('user-id'),
            assignment_role: $button.data('role')
        })
            .done(function(response) {
                if (response && response.success) {
                    refreshRows($modal, response.data && response.data.rowsHtml ? response.data.rowsHtml : '');
                    setStatus($modal, response.data && response.data.message ? response.data.message : BsyncAuctionAssignments.removed, false);
                } else {
                    var err = response && response.data && response.data.message ? response.data.message : BsyncAuctionAssignments.removeFailed;
                    setStatus($modal, err, true);
                }
            })
            .fail(function(xhr) {
                var err = BsyncAuctionAssignments.removeFailed;
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    err = xhr.responseJSON.data.message;
                }
                setStatus($modal, err, true);
            })
            .always(function() {
                $button.prop('disabled', false);
            });
    });

})(jQuery);
