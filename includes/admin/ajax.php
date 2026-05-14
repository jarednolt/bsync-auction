<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_bsync_auction_save_item_row', 'bsync_auction_ajax_save_item_row' );
add_action( 'wp_ajax_bsync_auction_quick_add_item', 'bsync_auction_ajax_quick_add_item' );
add_action( 'wp_ajax_bsync_auction_check_order_number_duplicate', 'bsync_auction_ajax_check_order_number_duplicate' );
add_action( 'wp_ajax_bsync_auction_frontend_add_item', 'bsync_auction_ajax_frontend_add_item' );

/**
 * Convert strict-scope WP_Error into AJAX JSON response.
 *
 * @param WP_Error $error Scope resolver error.
 * @return void
 */
function bsync_auction_ajax_send_scope_error( $error ) {
    $code      = ( $error instanceof WP_Error ) ? (string) $error->get_error_code() : 'forbidden_auction';
    $message   = ( $error instanceof WP_Error ) ? $error->get_error_message() : __( 'Permission denied.', 'bsync-auction' );
    $http_code = ( 'missing_auction' === $code ) ? 400 : 403;

    wp_send_json_error(
        array(
            'message' => $message,
            'code'    => $code,
        ),
        $http_code
    );
}

function bsync_auction_ajax_check_order_number_duplicate() {
    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'bsync_auction_check_order_number_duplicate' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'bsync-auction' ) ), 403 );
    }

    $item_id    = absint( $_POST['item_id'] ?? 0 );
    $auction_id = absint( $_POST['auction_id'] ?? 0 );

    if ( $item_id > 0 ) {
        if ( BSYNC_AUCTION_ITEM_CPT !== get_post_type( $item_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid item.', 'bsync-auction' ) ), 400 );
        }

        if ( ! bsync_auction_can_manage_item( $item_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to update this item.', 'bsync-auction' ) ), 403 );
        }
    } elseif ( ! bsync_auction_can_clerk_auction() ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bsync-auction' ) ), 403 );
    }

    if ( $auction_id <= 0 ) {
        wp_send_json_success(
            array(
                'isDuplicate' => false,
            )
        );
    }

    if ( ! bsync_auction_user_can_access_auction_scope( $auction_id ) ) {
        wp_send_json_error( array( 'message' => __( 'You are not assigned to this auction.', 'bsync-auction' ) ), 403 );
    }

    $order_number = bsync_auction_sanitize_order_number( wp_unslash( $_POST['order_number'] ?? '' ), '' );
    if ( '' === $order_number ) {
        wp_send_json_success(
            array(
                'isDuplicate' => false,
            )
        );
    }

    $is_duplicate = bsync_auction_order_number_exists( $auction_id, $order_number, $item_id );
    if ( ! $is_duplicate ) {
        wp_send_json_success(
            array(
                'isDuplicate' => false,
            )
        );
    }

    $suggested_next = bsync_auction_get_next_available_order_number( $auction_id, $item_id );

    wp_send_json_success(
        array(
            'isDuplicate'   => true,
            'suggestedNext' => $suggested_next,
            'message'       => sprintf(
                /* translators: 1: entered order number, 2: next available whole number */
                __( 'Order number %1$s already exists. Next available whole number: %2$s.', 'bsync-auction' ),
                $order_number,
                $suggested_next
            ),
        )
    );
}

function bsync_auction_ajax_save_item_row() {
    if ( ! bsync_auction_user_can_manage_grid() ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bsync-auction' ) ), 403 );
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'bsync_auction_save_item_row' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'bsync-auction' ) ), 403 );
    }

    $item_id = absint( $_POST['item_id'] ?? 0 );
    if ( $item_id <= 0 || BSYNC_AUCTION_ITEM_CPT !== get_post_type( $item_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid item.', 'bsync-auction' ) ), 400 );
    }

    if ( ! bsync_auction_can_manage_item( $item_id ) ) {
        wp_send_json_error( array( 'message' => __( 'You are not allowed to update this item.', 'bsync-auction' ) ), 403 );
    }

    $order_number = bsync_auction_sanitize_order_number( wp_unslash( $_POST['order_number'] ?? '' ), '' );
    $buyer_number = sanitize_text_field( wp_unslash( $_POST['buyer_number'] ?? '' ) );
    $status       = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'available' ) );
    $opening_bid  = (float) ( $_POST['opening_bid'] ?? 0 );
    $current_bid  = (float) ( $_POST['current_bid'] ?? 0 );
    $sold_price   = (float) ( $_POST['sold_price'] ?? 0 );

    if ( '' === $order_number ) {
        wp_send_json_error( array( 'message' => __( 'Order number must be at least 1.', 'bsync-auction' ) ), 400 );
    }

    $auction_id = (int) get_post_meta( $item_id, 'bsync_auction_id', true );
    if ( bsync_auction_order_number_exists( $auction_id, $order_number, $item_id ) ) {
        $suggested_next = bsync_auction_get_next_available_order_number( $auction_id, $item_id );
        wp_send_json_error(
            array(
                'message'       => sprintf(
                    /* translators: 1: entered order number, 2: next available whole number */
                    __( 'Order number %1$s already exists. Next available whole number: %2$s.', 'bsync-auction' ),
                    $order_number,
                    $suggested_next
                ),
                'suggestedNext' => $suggested_next,
            ),
            400
        );
    }

    if ( $opening_bid < 0 || $current_bid < 0 || $sold_price < 0 ) {
        wp_send_json_error( array( 'message' => __( 'Price fields must be non-negative.', 'bsync-auction' ) ), 400 );
    }

    $statuses = bsync_auction_get_item_statuses();
    if ( ! isset( $statuses[ $status ] ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid status value.', 'bsync-auction' ) ), 400 );
    }

    // Auto-promote to sold when a sold price is entered.
    if ( $sold_price > 0 && 'sold' !== $status && 'withdrawn' !== $status ) {
        $status = 'sold';
    }

    $buyer_id = 0;
    if ( '' !== $buyer_number ) {
        $buyer_id = bsync_auction_resolve_user_by_buyer_number( $buyer_number );
        if ( $buyer_id <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Buyer number was not found.', 'bsync-auction' ) ), 400 );
        }
    }

    update_post_meta( $item_id, 'bsync_auction_order_number', $order_number );
    update_post_meta( $item_id, 'bsync_auction_buyer_id', $buyer_id );
    update_post_meta( $item_id, 'bsync_auction_buyer_number', $buyer_number );
    update_post_meta( $item_id, 'bsync_auction_item_status', $status );
    update_post_meta( $item_id, 'bsync_auction_opening_bid', bsync_auction_money( $opening_bid ) );
    update_post_meta( $item_id, 'bsync_auction_current_bid', bsync_auction_money( $current_bid ) );
    update_post_meta( $item_id, 'bsync_auction_sold_price_internal', bsync_auction_money( $sold_price ) );

    $buyer_display_name = __( 'No Buyer', 'bsync-auction' );
    if ( $buyer_id > 0 ) {
        $buyer_user = get_user_by( 'id', $buyer_id );
        if ( $buyer_user instanceof WP_User ) {
            $buyer_display_name = $buyer_user->display_name;
        }
    }

    wp_send_json_success(
        array(
            'message'            => __( 'Row saved.', 'bsync-auction' ),
            'buyerDisplayName'   => $buyer_display_name,
            'buyerNumber'        => $buyer_number,
            'orderNumber'        => $order_number,
            'status'             => $status,
        )
    );
}

/**
 * Quickly add a new item during live clerking and place it after current flow.
 *
 * @return void
 */
function bsync_auction_ajax_quick_add_item() {
    if ( ! bsync_auction_user_can_manage_grid() ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bsync-auction' ) ), 403 );
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'bsync_auction_quick_add_item' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'bsync-auction' ) ), 403 );
    }

    $auction_id = absint( $_POST['auction_id'] ?? 0 );
    if ( $auction_id <= 0 || BSYNC_AUCTION_AUCTION_CPT !== get_post_type( $auction_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid auction.', 'bsync-auction' ) ), 400 );
    }

    $auction_id = bsync_auction_resolve_strict_auction_context(
        $auction_id,
        0,
        array(
            'audit_action' => 'quick_add_item',
        )
    );

    if ( is_wp_error( $auction_id ) ) {
        bsync_auction_ajax_send_scope_error( $auction_id );
    }

    $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
    if ( '' === $title ) {
        wp_send_json_error( array( 'message' => __( 'Item title is required.', 'bsync-auction' ) ), 400 );
    }

    $order_number = bsync_auction_sanitize_order_number( wp_unslash( $_POST['order_number'] ?? '' ), '' );
    if ( '' === $order_number || bsync_auction_order_number_exists( $auction_id, $order_number ) ) {
        $order_number = bsync_auction_get_next_available_order_number( $auction_id );
    }

    $statuses = bsync_auction_get_item_statuses();
    $status   = sanitize_key( wp_unslash( $_POST['status'] ?? 'available' ) );
    if ( ! isset( $statuses[ $status ] ) ) {
        $status = 'available';
    }

    $opening_bid = (float) ( $_POST['opening_bid'] ?? 0 );
    $current_bid = (float) ( $_POST['current_bid'] ?? 0 );
    $sold_price  = (float) ( $_POST['sold_price'] ?? 0 );

    if ( $opening_bid < 0 || $current_bid < 0 || $sold_price < 0 ) {
        wp_send_json_error( array( 'message' => __( 'Price fields must be non-negative.', 'bsync-auction' ) ), 400 );
    }

    if ( $sold_price > 0 && 'withdrawn' !== $status ) {
        $status = 'sold';
    }

    $buyer_number = sanitize_text_field( wp_unslash( $_POST['buyer_number'] ?? '' ) );
    $buyer_id     = 0;

    if ( '' !== $buyer_number ) {
        $buyer_id = bsync_auction_resolve_user_by_buyer_number( $buyer_number );
        if ( $buyer_id <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Buyer number was not found.', 'bsync-auction' ) ), 400 );
        }
    }

    $item_id = wp_insert_post(
        array(
            'post_type'   => BSYNC_AUCTION_ITEM_CPT,
            'post_status' => 'publish',
            'post_title'  => $title,
        ),
        true
    );

    if ( is_wp_error( $item_id ) || $item_id <= 0 ) {
        wp_send_json_error( array( 'message' => __( 'Unable to create item.', 'bsync-auction' ) ), 500 );
    }

    $item_number = bsync_auction_generate_next_item_number();

    update_post_meta( $item_id, 'bsync_auction_item_number', $item_number );
    update_post_meta( $item_id, 'bsync_auction_auctioneer_name', '' );
    update_post_meta( $item_id, 'bsync_auction_auctioneer_id', 0 );
    update_post_meta( $item_id, 'bsync_auction_item_status', $status );
    update_post_meta( $item_id, 'bsync_auction_opening_bid', bsync_auction_money( $opening_bid ) );
    update_post_meta( $item_id, 'bsync_auction_current_bid', bsync_auction_money( $current_bid ) );
    update_post_meta( $item_id, 'bsync_auction_sold_price_internal', bsync_auction_money( $sold_price ) );
    update_post_meta( $item_id, 'bsync_auction_buyer_id', $buyer_id );
    update_post_meta( $item_id, 'bsync_auction_buyer_number', $buyer_number );
    update_post_meta( $item_id, 'bsync_auction_order_number', $order_number );
    update_post_meta( $item_id, 'bsync_auction_id', $auction_id );

    if ( ! empty( $_FILES['featured_image'] ) && ! empty( $_FILES['featured_image']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'featured_image', $item_id );
        if ( is_wp_error( $attachment_id ) ) {
            wp_delete_post( $item_id, true );
            wp_send_json_error( array( 'message' => sprintf( __( 'Image upload failed: %s', 'bsync-auction' ), $attachment_id->get_error_message() ) ), 400 );
        }

        set_post_thumbnail( $item_id, (int) $attachment_id );
    }

    $buyer_display_name = __( 'No Buyer', 'bsync-auction' );
    if ( $buyer_id > 0 ) {
        $buyer_user = get_user_by( 'id', $buyer_id );
        if ( $buyer_user instanceof WP_User ) {
            $buyer_display_name = $buyer_user->display_name;
        }
    }

    wp_send_json_success(
        array(
            'message'      => __( 'Item created.', 'bsync-auction' ),
            'itemId'       => (int) $item_id,
            'itemNumber'   => (string) $item_number,
            'orderNumber'  => (string) $order_number,
            'title'        => get_the_title( $item_id ),
            'editUrl'      => get_edit_post_link( $item_id, '' ),
            'auctionId'    => (int) $auction_id,
            'auctionName'  => get_the_title( $auction_id ),
            'buyerNumber'  => $buyer_number,
            'status'       => $status,
            'openingBid'   => bsync_auction_money( $opening_bid ),
            'currentBid'   => bsync_auction_money( $current_bid ),
            'soldPrice'    => bsync_auction_money( $sold_price ),
            'buyerDisplay' => $buyer_display_name,
        )
    );
}

/**
 * Add auction item from frontend scoped form.
 *
 * @return void
 */
function bsync_auction_ajax_frontend_add_item() {
    if ( ! bsync_auction_can_clerk_auction() ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bsync-auction' ) ), 403 );
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'bsync_auction_frontend_add_item' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'bsync-auction' ) ), 403 );
    }

    $auction_id = absint( $_POST['auction_id'] ?? 0 );
    if ( $auction_id <= 0 || BSYNC_AUCTION_AUCTION_CPT !== get_post_type( $auction_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid auction.', 'bsync-auction' ) ), 400 );
    }

    $auction_id = bsync_auction_resolve_strict_auction_context(
        $auction_id,
        0,
        array(
            'audit_action' => 'frontend_add_item',
        )
    );

    if ( is_wp_error( $auction_id ) ) {
        bsync_auction_ajax_send_scope_error( $auction_id );
    }

    $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
    if ( '' === $title ) {
        wp_send_json_error( array( 'message' => __( 'Item title is required.', 'bsync-auction' ) ), 400 );
    }

    $order_number = bsync_auction_sanitize_order_number( wp_unslash( $_POST['order_number'] ?? '' ), '' );
    if ( '' === $order_number || bsync_auction_order_number_exists( $auction_id, $order_number ) ) {
        $order_number = bsync_auction_get_next_available_order_number( $auction_id );
    }

    $statuses = bsync_auction_get_item_statuses();
    $status   = sanitize_key( wp_unslash( $_POST['status'] ?? 'available' ) );
    if ( ! isset( $statuses[ $status ] ) ) {
        $status = 'available';
    }

    $opening_bid = (float) ( $_POST['opening_bid'] ?? 0 );
    $current_bid = (float) ( $_POST['current_bid'] ?? 0 );
    $sold_price  = (float) ( $_POST['sold_price'] ?? 0 );

    if ( $opening_bid < 0 || $current_bid < 0 || $sold_price < 0 ) {
        wp_send_json_error( array( 'message' => __( 'Price fields must be non-negative.', 'bsync-auction' ) ), 400 );
    }

    if ( $sold_price > 0 && 'withdrawn' !== $status ) {
        $status = 'sold';
    }

    $buyer_number = sanitize_text_field( wp_unslash( $_POST['buyer_number'] ?? '' ) );
    $buyer_id     = 0;

    if ( '' !== $buyer_number ) {
        $buyer_id = bsync_auction_resolve_user_by_buyer_number( $buyer_number );
        if ( $buyer_id <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Buyer number was not found.', 'bsync-auction' ) ), 400 );
        }
    }

    $item_id = wp_insert_post(
        array(
            'post_type'   => BSYNC_AUCTION_ITEM_CPT,
            'post_status' => 'publish',
            'post_title'  => $title,
        ),
        true
    );

    if ( is_wp_error( $item_id ) || $item_id <= 0 ) {
        wp_send_json_error( array( 'message' => __( 'Unable to create item.', 'bsync-auction' ) ), 500 );
    }

    $item_number = bsync_auction_generate_next_item_number();

    update_post_meta( $item_id, 'bsync_auction_item_number', $item_number );
    update_post_meta( $item_id, 'bsync_auction_auctioneer_name', '' );
    update_post_meta( $item_id, 'bsync_auction_auctioneer_id', 0 );
    update_post_meta( $item_id, 'bsync_auction_item_status', $status );
    update_post_meta( $item_id, 'bsync_auction_opening_bid', bsync_auction_money( $opening_bid ) );
    update_post_meta( $item_id, 'bsync_auction_current_bid', bsync_auction_money( $current_bid ) );
    update_post_meta( $item_id, 'bsync_auction_sold_price_internal', bsync_auction_money( $sold_price ) );
    update_post_meta( $item_id, 'bsync_auction_buyer_id', $buyer_id );
    update_post_meta( $item_id, 'bsync_auction_buyer_number', $buyer_number );
    update_post_meta( $item_id, 'bsync_auction_order_number', $order_number );
    update_post_meta( $item_id, 'bsync_auction_id', $auction_id );

    if ( ! empty( $_FILES['featured_image'] ) && ! empty( $_FILES['featured_image']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'featured_image', $item_id );
        if ( is_wp_error( $attachment_id ) ) {
            wp_delete_post( $item_id, true );
            wp_send_json_error( array( 'message' => sprintf( __( 'Image upload failed: %s', 'bsync-auction' ), $attachment_id->get_error_message() ) ), 400 );
        }

        set_post_thumbnail( $item_id, (int) $attachment_id );
    }

    wp_send_json_success(
        array(
            'message'     => __( 'Item created successfully.', 'bsync-auction' ),
            'itemId'      => (int) $item_id,
            'itemNumber'  => (string) $item_number,
            'orderNumber' => (string) $order_number,
            'title'       => get_the_title( $item_id ),
            'editUrl'     => get_edit_post_link( $item_id, '' ),
            'auctionId'   => (int) $auction_id,
            'auctionName' => get_the_title( $auction_id ),
        )
    );
}
