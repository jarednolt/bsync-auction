<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_enqueue_scripts', 'bsync_auction_enqueue_manager_grid_assets' );

function bsync_auction_user_can_manage_grid() {
    return bsync_auction_can_clerk_auction();
}

function bsync_auction_enqueue_manager_grid_assets( $hook ) {
    if ( 'auctions_page_bsync-auction-manager-grid' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'bsync-auction-admin-grid',
        BSYNC_AUCTION_PLUGIN_URL . 'assets/css/admin-manager-grid.css',
        array( 'bsync-admin-shared' ),
        BSYNC_AUCTION_VERSION
    );

    wp_enqueue_script(
        'bsync-auction-admin-grid',
        BSYNC_AUCTION_PLUGIN_URL . 'assets/js/admin-grid.js',
        array( 'jquery' ),
        BSYNC_AUCTION_VERSION,
        true
    );

    wp_localize_script(
        'bsync-auction-admin-grid',
        'BsyncAuctionGrid',
        array(
            'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( 'bsync_auction_save_item_row' ),
            'quickAddNonce'      => wp_create_nonce( 'bsync_auction_quick_add_item' ),
            'checkNonce'         => wp_create_nonce( 'bsync_auction_check_order_number_duplicate' ),
            'saving'             => __( 'Saving...', 'bsync-auction' ),
            'saved'              => __( 'Saved', 'bsync-auction' ),
            'failed'             => __( 'Save failed', 'bsync-auction' ),
            'useSuggested'       => __( 'Use suggested number', 'bsync-auction' ),
            'quickAddButton'     => __( 'Quick Add Next', 'bsync-auction' ),
            'quickAddTitle'      => __( 'Add Auction Item', 'bsync-auction' ),
            'quickAddSave'       => __( 'Add Item', 'bsync-auction' ),
            'quickAddCancel'     => __( 'Cancel', 'bsync-auction' ),
            'quickAddAdded'      => __( 'New item added after sold row.', 'bsync-auction' ),
            'quickAddMissingCtx' => __( 'This row is not linked to an auction.', 'bsync-auction' ),
            'quickAddSaving'     => __( 'Adding item...', 'bsync-auction' ),
            'noBuyer'            => __( 'No Buyer', 'bsync-auction' ),
            'linkedUserTemplate' => __( 'Linked user: %s', 'bsync-auction' ),
            'statuses'           => bsync_auction_get_item_statuses(),
        )
    );
}

function bsync_auction_render_manager_item_grid_page() {
    if ( ! bsync_auction_user_can_manage_grid() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'bsync-auction' ) );
    }

    $requested_auction_id = absint( $_GET['auction_id'] ?? 0 );

    // Strict context validation for scoped users.
    $auction_filter = 0;
    if ( $requested_auction_id > 0 ) {
        $context_result = bsync_auction_resolve_strict_auction_context(
            $requested_auction_id,
            0,
            array( 'audit_action' => 'view_manager_grid_filter' )
        );
        if ( is_wp_error( $context_result ) ) {
            // Scoped user tried to filter by an auction they don't have access to.
            wp_die(
                esc_html__( 'You do not have permission to view that auction.', 'bsync-auction' ),
                esc_html__( 'Forbidden', 'bsync-auction' ),
                403
            );
        }
        $auction_filter = (int) $context_result;
    }

    $auctions = get_posts(
        array(
            'post_type'      => BSYNC_AUCTION_AUCTION_CPT,
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $auctions = bsync_auction_filter_auctions_by_scope( $auctions );

    $query = array(
        'post_type'      => BSYNC_AUCTION_ITEM_CPT,
        'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
        'posts_per_page' => 500,
        'meta_key'       => 'bsync_auction_order_number',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
    );

    if ( $auction_filter > 0 ) {
        $query['meta_query'] = array(
            array(
                'key'   => 'bsync_auction_id',
                'value' => $auction_filter,
            ),
        );
    }

    $query = bsync_auction_apply_scope_to_item_query_args( $query );

    $items = get_posts( $query );

    usort(
        $items,
        static function( $a, $b ) {
            $a_order = (float) get_post_meta( $a->ID, 'bsync_auction_order_number', true );
            $b_order = (float) get_post_meta( $b->ID, 'bsync_auction_order_number', true );

            if ( $a_order !== $b_order ) {
                return $a_order <=> $b_order;
            }

            $a_item = (int) get_post_meta( $a->ID, 'bsync_auction_item_number', true );
            $b_item = (int) get_post_meta( $b->ID, 'bsync_auction_item_number', true );

            return $a_item <=> $b_item;
        }
    );

    $statuses = bsync_auction_get_item_statuses();

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Manager Item Grid', 'bsync-auction' ) . '</h1>';
    echo '<p>' . esc_html__( 'Edit buyer, status, and price fields inline, then save each row.', 'bsync-auction' ) . '</p>';

    $active_buyer_keys = bsync_auction_get_buyer_number_meta_keys();
    echo '<div class="notice notice-info inline" style="margin:12px 0;padding:10px 14px;">';
    echo '<strong>' . esc_html__( 'Buyer Number Lookup', 'bsync-auction' ) . ':</strong> ';
    echo esc_html__( 'When you enter a buyer number, the plugin searches these user meta keys in order until a match is found:', 'bsync-auction' );
    echo ' <code>' . esc_html( implode( '</code>, <code>', $active_buyer_keys ) ) . '</code>.';
    echo ' ' . esc_html__( 'If no meta match is found, the number is tried as a WordPress user ID, then as a username.', 'bsync-auction' );
    echo ' ' . sprintf(
        /* translators: %s: filter hook name */
        esc_html__( 'To change the lookup keys, add a %s filter in your theme or plugin.', 'bsync-auction' ),
        '<code>bsync_auction_buyer_number_meta_keys</code>'
    );
    echo '</div>';

    echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="margin:16px 0;">';
    echo '<input type="hidden" name="page" value="bsync-auction-manager-grid" />';
    echo '<label for="auction_id"><strong>' . esc_html__( 'Filter by Auction:', 'bsync-auction' ) . '</strong></label> ';
    echo '<select id="auction_id" name="auction_id">';
    echo '<option value="0">' . esc_html__( 'All Auctions', 'bsync-auction' ) . '</option>';
    foreach ( $auctions as $auction ) {
        echo '<option value="' . esc_attr( $auction->ID ) . '" ' . selected( $auction_filter, $auction->ID, false ) . '>' . esc_html( $auction->post_title ) . '</option>';
    }
    echo '</select> ';
    submit_button( __( 'Filter', 'bsync-auction' ), 'secondary', '', false );
    echo ' <button type="button" class="button button-primary bsync-auction-open-add-item" data-auction-id="' . esc_attr( (string) $auction_filter ) . '">' . esc_html__( 'Add Item', 'bsync-auction' ) . '</button>';
    echo '</form>';

    echo '<div id="bsync-auction-add-item-modal" class="bsync-auction-add-item-modal bsync-admin-modal" style="display:none;">';
    echo '<div class="bsync-auction-add-item-modal-inner bsync-admin-modal-inner">';
    echo '<button type="button" class="button-link bsync-auction-close-add-item bsync-admin-modal-close" aria-label="' . esc_attr__( 'Close', 'bsync-auction' ) . '">&times;</button>';
    echo '<h2>' . esc_html__( 'Add Auction Item', 'bsync-auction' ) . '</h2>';
    echo '<form id="bsync-auction-add-item-form" enctype="multipart/form-data">';
    echo '<div class="bsync-auction-add-item-grid">';

    echo '<p><label for="bsync_add_item_title"><strong>' . esc_html__( 'Item Title', 'bsync-auction' ) . '</strong></label><br />';
    echo '<input type="text" id="bsync_add_item_title" name="title" class="regular-text" required /></p>';

    echo '<p><label for="bsync_add_item_auction"><strong>' . esc_html__( 'Auction', 'bsync-auction' ) . '</strong></label><br />';
    echo '<select id="bsync_add_item_auction" name="auction_id" required>';
    echo '<option value="">' . esc_html__( 'Select Auction', 'bsync-auction' ) . '</option>';
    foreach ( $auctions as $auction ) {
        echo '<option value="' . esc_attr( (string) $auction->ID ) . '">' . esc_html( $auction->post_title ) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="bsync_add_item_order"><strong>' . esc_html__( 'Order Number', 'bsync-auction' ) . '</strong></label><br />';
    echo '<input type="number" id="bsync_add_item_order" name="order_number" min="1" step="0.01" placeholder="' . esc_attr__( 'Auto next available', 'bsync-auction' ) . '" /></p>';

    echo '<p><label for="bsync_add_item_status"><strong>' . esc_html__( 'Status', 'bsync-auction' ) . '</strong></label><br />';
    echo '<select id="bsync_add_item_status" name="status">';
    foreach ( $statuses as $status_key => $label ) {
        echo '<option value="' . esc_attr( $status_key ) . '">' . esc_html( $label ) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="bsync_add_item_opening"><strong>' . esc_html__( 'Opening Bid', 'bsync-auction' ) . '</strong></label><br />';
    echo '<input type="number" id="bsync_add_item_opening" name="opening_bid" min="0" step="0.01" value="0.00" /></p>';

    echo '<p><label for="bsync_add_item_current"><strong>' . esc_html__( 'Current Bid', 'bsync-auction' ) . '</strong></label><br />';
    echo '<input type="number" id="bsync_add_item_current" name="current_bid" min="0" step="0.01" value="0.00" /></p>';

    echo '<p><label for="bsync_add_item_sold"><strong>' . esc_html__( 'Sold Price', 'bsync-auction' ) . '</strong></label><br />';
    echo '<input type="number" id="bsync_add_item_sold" name="sold_price" min="0" step="0.01" value="0.00" /></p>';

    echo '<p><label for="bsync_add_item_buyer"><strong>' . esc_html__( 'Buyer Number', 'bsync-auction' ) . '</strong></label><br />';
    echo '<input type="text" id="bsync_add_item_buyer" name="buyer_number" placeholder="' . esc_attr__( 'Optional', 'bsync-auction' ) . '" /></p>';

    echo '<p><label for="bsync_add_item_image"><strong>' . esc_html__( 'Featured Image', 'bsync-auction' ) . '</strong></label><br />';
    echo '<input type="file" id="bsync_add_item_image" name="featured_image" accept="image/*" /></p>';

    echo '</div>';
    echo '<p class="bsync-auction-add-item-actions">';
    echo '<button type="submit" class="button button-primary bsync-auction-submit-add-item">' . esc_html__( 'Add Item', 'bsync-auction' ) . '</button> ';
    echo '<button type="button" class="button bsync-auction-close-add-item">' . esc_html__( 'Cancel', 'bsync-auction' ) . '</button> ';
    echo '<span class="bsync-auction-add-item-status" aria-live="polite"></span>';
    echo '</p>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Item #', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Order', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Item', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Auction', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Buyer #', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Status', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Opening Bid', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Current Bid', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Sold Price', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Action', 'bsync-auction' ) . '</th>';
    echo '</tr></thead><tbody>';

    if ( empty( $items ) ) {
        echo '<tr><td colspan="10">' . esc_html__( 'No auction items found.', 'bsync-auction' ) . '</td></tr>';
    }

    foreach ( $items as $item ) {
        $item_id      = $item->ID;
        $item_number  = get_post_meta( $item_id, 'bsync_auction_item_number', true );
        $order_number = get_post_meta( $item_id, 'bsync_auction_order_number', true );
        $buyer_id     = (int) get_post_meta( $item_id, 'bsync_auction_buyer_id', true );
        $status       = get_post_meta( $item_id, 'bsync_auction_item_status', true );
        $opening_bid  = get_post_meta( $item_id, 'bsync_auction_opening_bid', true );
        $current_bid  = get_post_meta( $item_id, 'bsync_auction_current_bid', true );
        $sold_price   = get_post_meta( $item_id, 'bsync_auction_sold_price_internal', true );
        $auction_id   = (int) get_post_meta( $item_id, 'bsync_auction_id', true );

        if ( $auction_id > 0 && ! bsync_auction_user_can_access_auction_scope( $auction_id ) ) {
            continue;
        }

        $auction_name = $auction_id > 0 ? get_the_title( $auction_id ) : __( 'Unassigned', 'bsync-auction' );

        if ( '' === $status ) {
            $status = 'available';
        }

        echo '<tr data-item-id="' . esc_attr( $item_id ) . '" data-auction-id="' . esc_attr( $auction_id ) . '" data-auction-name="' . esc_attr( (string) $auction_name ) . '">';
        echo '<td>' . esc_html( $item_number ) . '</td>';
        $buyer_user         = $buyer_id > 0 ? get_user_by( 'id', $buyer_id ) : false;
        $buyer_number_value  = $buyer_id > 0 ? bsync_auction_get_buyer_number_for_user( $buyer_id ) : '';
        $buyer_display_label = $buyer_user instanceof WP_User ? $buyer_user->display_name : __( 'No Buyer', 'bsync-auction' );

        echo '<td><input type="number" class="small-text bsync-auction-field" data-field="order_number" min="1" step="0.01" value="' . esc_attr( (string) $order_number ) . '" /></td>';
        echo '<td><a href="' . esc_url( get_edit_post_link( $item_id ) ) . '">' . esc_html( get_the_title( $item_id ) ) . '</a></td>';
        echo '<td>' . esc_html( $auction_name ) . '</td>';

        echo '<td>';
        echo '<input type="text" class="regular-text bsync-auction-field" data-field="buyer_number" value="' . esc_attr( $buyer_number_value ) . '" placeholder="' . esc_attr__( 'Enter buyer number', 'bsync-auction' ) . '" />';
        echo '<br /><small class="bsync-auction-linked-user" data-template="' . esc_attr__( 'Linked user: %s', 'bsync-auction' ) . '">' . sprintf( esc_html__( 'Linked user: %s', 'bsync-auction' ), esc_html( $buyer_display_label ) ) . '</small>';
        echo '</td>';

        echo '<td><select class="bsync-auction-field" data-field="status">';
        foreach ( $statuses as $status_key => $label ) {
            echo '<option value="' . esc_attr( $status_key ) . '" ' . selected( $status, $status_key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td>';

        echo '<td><input type="number" class="small-text bsync-auction-field" data-field="opening_bid" min="0" step="0.01" value="' . esc_attr( $opening_bid ) . '" /></td>';
        echo '<td><input type="number" class="small-text bsync-auction-field" data-field="current_bid" min="0" step="0.01" value="' . esc_attr( $current_bid ) . '" /></td>';
        echo '<td><input type="number" class="small-text bsync-auction-field" data-field="sold_price" min="0" step="0.01" value="' . esc_attr( $sold_price ) . '" /></td>';

        echo '<td>';
        echo '<button type="button" class="button button-primary bsync-auction-save-row">' . esc_html__( 'Save Row', 'bsync-auction' ) . '</button> ';
        echo '<span class="bsync-auction-save-status" aria-live="polite"></span>';
        echo '<div class="bsync-auction-duplicate-alert" style="display:none;">';
        echo '<span class="bsync-auction-duplicate-text" aria-live="polite"></span> ';
        echo '<button type="button" class="button-link bsync-auction-apply-suggested" data-suggested=""></button>';
        echo '</div>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}
