<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_bsync_auction_save_item_row', 'bsync_auction_ajax_save_item_row' );
add_action( 'wp_ajax_bsync_auction_quick_add_item', 'bsync_auction_ajax_quick_add_item' );
add_action( 'wp_ajax_bsync_auction_check_order_number_duplicate', 'bsync_auction_ajax_check_order_number_duplicate' );
add_action( 'wp_ajax_bsync_auction_frontend_add_item', 'bsync_auction_ajax_frontend_add_item' );
add_action( 'wp_ajax_bsync_auction_frontend_add_buyer', 'bsync_auction_ajax_frontend_add_buyer' );

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

/**
 * Build a structured duplicate-user error payload with clerk-facing details and edit link.
 *
 * @param int    $user_id Existing user ID.
 * @param string $field   Which field triggered the dupe: email|phone|member_number.
 * @return array
 */
function bsync_auction_duplicate_user_error( int $user_id, string $field ): array {
    $user        = $user_id > 0 ? get_userdata( $user_id ) : null;
    $display     = $user ? $user->display_name : '';
    $first_name  = $user ? (string) $user->first_name : '';
    $last_name   = $user ? (string) $user->last_name : '';
    $email       = $user ? (string) $user->user_email : '';
    $user_login  = $user ? (string) $user->user_login : '';
    $member_num  = $user ? (string) get_user_meta( $user_id, 'bsync_member_number', true ) : '';
    $phone       = $user ? (string) get_user_meta( $user_id, 'bsync_member_main_phone', true ) : '';
    $address     = $user ? (string) get_user_meta( $user_id, 'bsync_member_address', true ) : '';
    $birthdate   = $user ? (string) get_user_meta( $user_id, 'bsync_member_main_birthdate', true ) : '';
    $edit_url    = $user_id > 0 ? get_edit_user_link( $user_id ) : '';

    switch ( $field ) {
        case 'email':
            $label = __( 'Email already in use', 'bsync-auction' );
            break;
        case 'phone':
            $label = __( 'Phone number already in use', 'bsync-auction' );
            break;
        case 'member_number':
            $label = __( 'Buyer / Member Number is already assigned', 'bsync-auction' );
            break;
        default:
            $label = __( 'Duplicate value', 'bsync-auction' );
    }

    return array(
        'message'   => $label,
        'duplicate' => array(
            'userId'       => $user_id,
            'userLogin'    => $user_login,
            'displayName'  => $display,
            'firstName'    => $first_name,
            'lastName'     => $last_name,
            'email'        => $email,
            'memberNumber' => $member_num,
            'phone'        => $phone,
            'address'      => $address,
            'birthdate'    => $birthdate,
            'editUrl'      => $edit_url,
        ),
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

/**
 * Add buyer/user from frontend clerk form.
 *
 * Supports phone-only registration (email optional).
 *
 * @return void
 */
function bsync_auction_ajax_frontend_add_buyer() {
    if ( ! bsync_auction_can_clerk_auction() ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bsync-auction' ) ), 403 );
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'bsync_auction_frontend_add_buyer' ) ) {
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
            'audit_action' => 'frontend_add_buyer',
        )
    );

    if ( is_wp_error( $auction_id ) ) {
        bsync_auction_ajax_send_scope_error( $auction_id );
    }

    $existing_user_id = absint( $_POST['existing_user_id'] ?? 0 );
    $first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
    $last_name  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
    $email_raw  = sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) );
    $phone_raw  = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
    $address    = sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) );
    $birthdate  = sanitize_text_field( wp_unslash( $_POST['birthdate'] ?? '' ) );

    if ( $existing_user_id > 0 ) {
        $existing_user = get_userdata( $existing_user_id );
        if ( ! ( $existing_user instanceof WP_User ) ) {
            wp_send_json_error( array( 'message' => __( 'Selected existing user no longer exists.', 'bsync-auction' ) ), 404 );
        }

        if ( '' !== trim( $email_raw ) ) {
            $email = sanitize_email( $email_raw );
            if ( '' === $email || ! is_email( $email ) ) {
                wp_send_json_error( array( 'message' => __( 'Email address is invalid.', 'bsync-auction' ) ), 400 );
            }

            $email_owner = (int) email_exists( $email );
            if ( $email_owner > 0 && $email_owner !== $existing_user_id ) {
                wp_send_json_error( bsync_auction_duplicate_user_error( $email_owner, 'email' ), 400 );
            }

            if ( $email !== $existing_user->user_email ) {
                $email_update = wp_update_user(
                    array(
                        'ID'         => $existing_user_id,
                        'user_email' => $email,
                    )
                );
                if ( is_wp_error( $email_update ) ) {
                    wp_send_json_error( array( 'message' => $email_update->get_error_message() ), 400 );
                }
            }
        }

        $requested_number = trim( sanitize_text_field( wp_unslash( $_POST['buyer_number'] ?? '' ) ) );
        $buyer_number     = (string) get_user_meta( $existing_user_id, 'bsync_member_number', true );

        if ( '' !== $requested_number && $requested_number !== $buyer_number ) {
            $number_owner = bsync_auction_get_user_id_by_member_number( $requested_number, $existing_user_id );
            if ( $number_owner > 0 ) {
                wp_send_json_error( bsync_auction_duplicate_user_error( (int) $number_owner, 'member_number' ), 400 );
            }

            $buyer_number = $requested_number;
            update_user_meta( $existing_user_id, 'bsync_member_number', $buyer_number );
        } elseif ( '' === $buyer_number ) {
            $buyer_number = '' !== $requested_number ? $requested_number : (string) bsync_auction_get_next_available_member_number();
            $number_owner = bsync_auction_get_user_id_by_member_number( $buyer_number, $existing_user_id );
            if ( $number_owner > 0 ) {
                wp_send_json_error( bsync_auction_duplicate_user_error( (int) $number_owner, 'member_number' ), 400 );
            }
            update_user_meta( $existing_user_id, 'bsync_member_number', $buyer_number );
        }

        if ( '' !== $first_name || '' !== $last_name ) {
            $display_name = trim( $first_name . ' ' . $last_name );
            if ( '' === $display_name ) {
                $display_name = $existing_user->display_name;
            }

            $update_names = wp_update_user(
                array(
                    'ID'           => $existing_user_id,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'display_name' => $display_name,
                )
            );
            if ( is_wp_error( $update_names ) ) {
                wp_send_json_error( array( 'message' => $update_names->get_error_message() ), 400 );
            }
        }

        $phone_normalized = preg_replace( '/[^0-9+]/', '', (string) $phone_raw );
        if ( '' !== trim( (string) $phone_normalized ) ) {
            $existing_phone_users = get_users(
                array(
                    'number'     => 1,
                    'fields'     => 'ids',
                    'meta_key'   => 'bsync_member_main_phone',
                    'meta_value' => $phone_normalized,
                    'exclude'    => array( $existing_user_id ),
                )
            );

            if ( ! empty( $existing_phone_users ) ) {
                wp_send_json_error( bsync_auction_duplicate_user_error( (int) $existing_phone_users[0], 'phone' ), 400 );
            }

            update_user_meta( $existing_user_id, 'bsync_member_main_phone', $phone_normalized );
        }

        if ( '' !== trim( $address ) ) {
            update_user_meta( $existing_user_id, 'bsync_member_address', $address );
        }

        if ( '' !== trim( $birthdate ) ) {
            update_user_meta( $existing_user_id, 'bsync_member_main_birthdate', $birthdate );
        }

        $registered_auctions = get_user_meta( $existing_user_id, 'bsync_auction_registered_auctions', true );
        if ( ! is_array( $registered_auctions ) ) {
            $registered_auctions = array();
        }

        $already_registered = in_array( (int) $auction_id, array_map( 'absint', $registered_auctions ), true );
        if ( ! $already_registered ) {
            $registered_auctions[] = (int) $auction_id;
            $registered_auctions   = array_values( array_unique( array_map( 'absint', $registered_auctions ) ) );
            update_user_meta( $existing_user_id, 'bsync_auction_registered_auctions', $registered_auctions );
        }

        $fresh_user = get_userdata( $existing_user_id );

        wp_send_json_success(
            array(
                'message'         => $already_registered
                    ? __( 'Existing buyer is already registered for this auction.', 'bsync-auction' )
                    : __( 'Existing buyer registered successfully.', 'bsync-auction' ),
                'existingUser'    => true,
                'userId'          => (int) $existing_user_id,
                'buyerNumber'     => (string) $buyer_number,
                'username'        => $fresh_user instanceof WP_User ? (string) $fresh_user->user_login : (string) $existing_user->user_login,
                'nextBuyerNumber' => (string) bsync_auction_get_next_available_member_number(),
                'displayName'     => $fresh_user instanceof WP_User ? (string) $fresh_user->display_name : (string) $existing_user->display_name,
                'auctionId'       => (int) $auction_id,
                'auctionName'     => get_the_title( (int) $auction_id ),
            )
        );
    }

    if ( '' === $first_name && '' === $last_name ) {
        wp_send_json_error( array( 'message' => __( 'Name is required.', 'bsync-auction' ) ), 400 );
    }

    $email = '';
    if ( '' !== trim( $email_raw ) ) {
        $email = sanitize_email( $email_raw );
        if ( '' === $email || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Email address is invalid.', 'bsync-auction' ) ), 400 );
        }

        if ( email_exists( $email ) ) {
            $dupe_id = (int) email_exists( $email );
            wp_send_json_error( bsync_auction_duplicate_user_error( $dupe_id, 'email' ), 400 );
        }
    }

    $phone_normalized = preg_replace( '/[^0-9+]/', '', (string) $phone_raw );
    if ( '' === $email && '' === trim( (string) $phone_normalized ) ) {
        wp_send_json_error( array( 'message' => __( 'Provide at least an email or phone number.', 'bsync-auction' ) ), 400 );
    }

    if ( '' !== trim( (string) $phone_normalized ) ) {
        $existing_phone_users = get_users(
            array(
                'number'     => 1,
                'fields'     => 'ids',
                'meta_key'   => 'bsync_member_main_phone',
                'meta_value' => $phone_normalized,
            )
        );
        if ( ! empty( $existing_phone_users ) ) {
            wp_send_json_error( bsync_auction_duplicate_user_error( (int) $existing_phone_users[0], 'phone' ), 400 );
        }
    }

    $requested_number = trim( sanitize_text_field( wp_unslash( $_POST['buyer_number'] ?? '' ) ) );
    if ( '' === $requested_number ) {
        $buyer_number = (string) bsync_auction_get_next_available_member_number();
    } else {
        $buyer_number = $requested_number;
    }

    $number_owner = bsync_auction_get_user_id_by_member_number( $buyer_number, 0 );
    if ( $number_owner > 0 ) {
        wp_send_json_error( bsync_auction_duplicate_user_error( (int) $number_owner, 'member_number' ), 400 );
    }

    $display_name = trim( $first_name . ' ' . $last_name );
    if ( '' === $display_name ) {
        $display_name = $first_name ? $first_name : $last_name;
    }

    $username_seed = '' !== $email
        ? current( explode( '@', $email ) )
        : sanitize_title( trim( $first_name . '-' . $last_name ) );

    if ( '' === $username_seed ) {
        $username_seed = 'buyer';
    }

    $username_seed = sanitize_user( str_replace( '+', '_', (string) $username_seed ), true );
    if ( '' === $username_seed ) {
        $username_seed = 'buyer';
    }

    $username = $username_seed;
    $counter  = 1;
    while ( username_exists( $username ) ) {
        $username = $username_seed . $counter;
        $counter++;
    }

    $user_payload = array(
        'user_login'   => $username,
        'user_pass'    => wp_generate_password( 20, true, true ),
        'user_email'   => $email,
        'display_name' => $display_name,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'role'         => get_option( 'default_role', 'subscriber' ),
    );

    $user_id = wp_insert_user( $user_payload );
    if ( is_wp_error( $user_id ) || $user_id <= 0 ) {
        $message = is_wp_error( $user_id ) ? $user_id->get_error_message() : __( 'Unable to create buyer user.', 'bsync-auction' );
        wp_send_json_error( array( 'message' => $message ), 500 );
    }

    update_user_meta( $user_id, 'bsync_member_number', $buyer_number );

    if ( '' !== trim( (string) $phone_normalized ) ) {
        update_user_meta( $user_id, 'bsync_member_main_phone', $phone_normalized );
    }

    if ( '' !== trim( $address ) ) {
        update_user_meta( $user_id, 'bsync_member_address', $address );
    }

    if ( '' !== trim( $birthdate ) ) {
        update_user_meta( $user_id, 'bsync_member_main_birthdate', $birthdate );
    }

    $registered_auctions = get_user_meta( $user_id, 'bsync_auction_registered_auctions', true );
    if ( ! is_array( $registered_auctions ) ) {
        $registered_auctions = array();
    }
    $registered_auctions[] = (int) $auction_id;
    $registered_auctions   = array_values( array_unique( array_map( 'absint', $registered_auctions ) ) );
    update_user_meta( $user_id, 'bsync_auction_registered_auctions', $registered_auctions );

    wp_send_json_success(
        array(
            'message'      => __( 'Buyer registered successfully.', 'bsync-auction' ),
            'userId'       => (int) $user_id,
            'buyerNumber'  => (string) $buyer_number,
            'username'     => (string) $username,
            'nextBuyerNumber' => (string) bsync_auction_get_next_available_member_number(),
            'displayName'  => $display_name,
            'auctionId'    => (int) $auction_id,
            'auctionName'  => get_the_title( (int) $auction_id ),
        )
    );
}
