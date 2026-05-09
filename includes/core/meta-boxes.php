<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'add_meta_boxes', 'bsync_auction_register_meta_boxes' );
add_action( 'save_post', 'bsync_auction_save_meta_boxes', 10, 2 );

function bsync_auction_register_meta_boxes() {
    add_meta_box(
        'bsync_auction_details',
        __( 'Auction Details', 'bsync-auction' ),
        'bsync_auction_render_auction_meta_box',
        BSYNC_AUCTION_AUCTION_CPT,
        'normal',
        'default'
    );

    add_meta_box(
        'bsync_auction_item_details',
        __( 'Auction Item Details', 'bsync-auction' ),
        'bsync_auction_render_item_meta_box',
        BSYNC_AUCTION_ITEM_CPT,
        'normal',
        'default'
    );
}

function bsync_auction_render_auction_meta_box( $post ) {
    wp_nonce_field( 'bsync_auction_save_meta', 'bsync_auction_meta_nonce' );

    $location     = get_post_meta( $post->ID, 'bsync_auction_location', true );
    $address      = get_post_meta( $post->ID, 'bsync_auction_address', true );
    $auctioneer   = (int) get_post_meta( $post->ID, 'bsync_auction_auctioneer_id', true );
    $starts_at    = get_post_meta( $post->ID, 'bsync_auction_starts_at', true );
    $ends_at      = get_post_meta( $post->ID, 'bsync_auction_ends_at', true );

    $users = get_users(
        array(
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 300,
        )
    );
    ?>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="bsync_auction_location"><?php esc_html_e( 'Location Name', 'bsync-auction' ); ?></label></th>
            <td><input type="text" id="bsync_auction_location" name="bsync_auction_location" class="regular-text" value="<?php echo esc_attr( $location ); ?>" /></td>
        </tr>
        <tr>
            <th><label for="bsync_auction_address"><?php esc_html_e( 'Address', 'bsync-auction' ); ?></label></th>
            <td><textarea id="bsync_auction_address" name="bsync_auction_address" class="large-text" rows="3"><?php echo esc_textarea( $address ); ?></textarea></td>
        </tr>
        <tr>
            <th><label for="bsync_auction_auctioneer_id"><?php esc_html_e( 'Auctioneer', 'bsync-auction' ); ?></label></th>
            <td>
                <select id="bsync_auction_auctioneer_id" name="bsync_auction_auctioneer_id">
                    <option value="0"><?php esc_html_e( 'Select Auctioneer', 'bsync-auction' ); ?></option>
                    <?php foreach ( $users as $user ) : ?>
                        <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $auctioneer, $user->ID ); ?>>
                            <?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="bsync_auction_starts_at"><?php esc_html_e( 'Starts At', 'bsync-auction' ); ?></label></th>
            <td><input type="datetime-local" id="bsync_auction_starts_at" name="bsync_auction_starts_at" value="<?php echo esc_attr( bsync_auction_to_datetime_local( $starts_at ) ); ?>" /></td>
        </tr>
        <tr>
            <th><label for="bsync_auction_ends_at"><?php esc_html_e( 'Ends At', 'bsync-auction' ); ?></label></th>
            <td><input type="datetime-local" id="bsync_auction_ends_at" name="bsync_auction_ends_at" value="<?php echo esc_attr( bsync_auction_to_datetime_local( $ends_at ) ); ?>" /></td>
        </tr>
    </table>
    <?php
}

function bsync_auction_render_item_meta_box( $post ) {
    wp_nonce_field( 'bsync_auction_save_meta', 'bsync_auction_meta_nonce' );

    $item_number = get_post_meta( $post->ID, 'bsync_auction_item_number', true );
    $order_num   = get_post_meta( $post->ID, 'bsync_auction_order_number', true );

    if ( '' === (string) $item_number ) {
        $item_number = bsync_auction_generate_next_item_number();
    }

    if ( '' === (string) $order_num ) {
        $order_num = 1;
    }
    $opening_bid = get_post_meta( $post->ID, 'bsync_auction_opening_bid', true );
    $current_bid = get_post_meta( $post->ID, 'bsync_auction_current_bid', true );
    $sold_price  = get_post_meta( $post->ID, 'bsync_auction_sold_price_internal', true );
    $buyer_id    = (int) get_post_meta( $post->ID, 'bsync_auction_buyer_id', true );
    $auction_id  = (int) get_post_meta( $post->ID, 'bsync_auction_id', true );
    $status      = get_post_meta( $post->ID, 'bsync_auction_item_status', true );

    if ( '' === $status ) {
        $status = 'available';
    }

    $statuses = bsync_auction_get_item_statuses();

    $auctions = get_posts(
        array(
            'post_type'      => BSYNC_AUCTION_AUCTION_CPT,
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $users = get_users(
        array(
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 500,
        )
    );
    ?>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="bsync_auction_id"><?php esc_html_e( 'Auction', 'bsync-auction' ); ?></label></th>
            <td>
                <select id="bsync_auction_id" name="bsync_auction_id">
                    <option value="0"><?php esc_html_e( 'Select Auction', 'bsync-auction' ); ?></option>
                    <?php foreach ( $auctions as $auction ) : ?>
                        <option value="<?php echo esc_attr( $auction->ID ); ?>" <?php selected( $auction_id, $auction->ID ); ?>><?php echo esc_html( $auction->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="bsync_auction_item_number"><?php esc_html_e( 'Item Number (Fixed)', 'bsync-auction' ); ?></label></th>
            <td>
                <input type="text" id="bsync_auction_item_number" name="bsync_auction_item_number" value="<?php echo esc_attr( $item_number ); ?>" readonly="readonly" />
                <p class="description"><?php esc_html_e( 'Assigned automatically and locked to prevent duplicates.', 'bsync-auction' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="bsync_auction_order_number"><?php esc_html_e( 'Order Number', 'bsync-auction' ); ?></label></th>
            <td><input type="number" id="bsync_auction_order_number" name="bsync_auction_order_number" value="<?php echo esc_attr( $order_num ); ?>" /></td>
        </tr>
        <tr>
            <th><label for="bsync_auction_opening_bid"><?php esc_html_e( 'Opening Bid', 'bsync-auction' ); ?></label></th>
            <td><input type="number" step="0.01" min="0" id="bsync_auction_opening_bid" name="bsync_auction_opening_bid" value="<?php echo esc_attr( $opening_bid ); ?>" /></td>
        </tr>
        <tr>
            <th><label for="bsync_auction_current_bid"><?php esc_html_e( 'Current Bid', 'bsync-auction' ); ?></label></th>
            <td><input type="number" step="0.01" min="0" id="bsync_auction_current_bid" name="bsync_auction_current_bid" value="<?php echo esc_attr( $current_bid ); ?>" /></td>
        </tr>
        <tr>
            <th><label for="bsync_auction_sold_price_internal"><?php esc_html_e( 'Sold Price (Internal)', 'bsync-auction' ); ?></label></th>
            <td><input type="number" step="0.01" min="0" id="bsync_auction_sold_price_internal" name="bsync_auction_sold_price_internal" value="<?php echo esc_attr( $sold_price ); ?>" /></td>
        </tr>
        <tr>
            <th><label for="bsync_auction_buyer_id"><?php esc_html_e( 'Buyer', 'bsync-auction' ); ?></label></th>
            <td>
                <select id="bsync_auction_buyer_id" name="bsync_auction_buyer_id">
                    <option value="0"><?php esc_html_e( 'No Buyer Selected', 'bsync-auction' ); ?></option>
                    <?php foreach ( $users as $user ) : ?>
                        <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $buyer_id, $user->ID ); ?>>
                            <?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="bsync_auction_item_status"><?php esc_html_e( 'Status', 'bsync-auction' ); ?></label></th>
            <td>
                <select id="bsync_auction_item_status" name="bsync_auction_item_status">
                    <?php foreach ( $statuses as $status_key => $label ) : ?>
                        <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status, $status_key ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>
    <?php
}

function bsync_auction_save_meta_boxes( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! isset( $_POST['bsync_auction_meta_nonce'] ) ) {
        return;
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['bsync_auction_meta_nonce'] ) );
    if ( ! wp_verify_nonce( $nonce, 'bsync_auction_save_meta' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( BSYNC_AUCTION_AUCTION_CPT === $post->post_type ) {
        update_post_meta( $post_id, 'bsync_auction_location', sanitize_text_field( wp_unslash( $_POST['bsync_auction_location'] ?? '' ) ) );
        update_post_meta( $post_id, 'bsync_auction_address', sanitize_textarea_field( wp_unslash( $_POST['bsync_auction_address'] ?? '' ) ) );
        update_post_meta( $post_id, 'bsync_auction_auctioneer_id', absint( $_POST['bsync_auction_auctioneer_id'] ?? 0 ) );
        update_post_meta( $post_id, 'bsync_auction_starts_at', bsync_auction_normalize_datetime( wp_unslash( $_POST['bsync_auction_starts_at'] ?? '' ) ) );
        update_post_meta( $post_id, 'bsync_auction_ends_at', bsync_auction_normalize_datetime( wp_unslash( $_POST['bsync_auction_ends_at'] ?? '' ) ) );
        return;
    }

    if ( BSYNC_AUCTION_ITEM_CPT !== $post->post_type ) {
        return;
    }

    $existing_item_number = (string) get_post_meta( $post_id, 'bsync_auction_item_number', true );
    if ( '' === $existing_item_number || bsync_auction_item_number_exists( $existing_item_number, $post_id ) ) {
        $existing_item_number = bsync_auction_generate_next_item_number();
        update_post_meta( $post_id, 'bsync_auction_item_number', $existing_item_number );
    }

    $buyer_id = absint( $_POST['bsync_auction_buyer_id'] ?? 0 );

    update_post_meta( $post_id, 'bsync_auction_id', absint( $_POST['bsync_auction_id'] ?? 0 ) );
    update_post_meta( $post_id, 'bsync_auction_order_number', max( 1, (int) ( $_POST['bsync_auction_order_number'] ?? 1 ) ) );
    update_post_meta( $post_id, 'bsync_auction_opening_bid', bsync_auction_money( $_POST['bsync_auction_opening_bid'] ?? 0 ) );
    update_post_meta( $post_id, 'bsync_auction_current_bid', bsync_auction_money( $_POST['bsync_auction_current_bid'] ?? 0 ) );
    update_post_meta( $post_id, 'bsync_auction_sold_price_internal', bsync_auction_money( $_POST['bsync_auction_sold_price_internal'] ?? 0 ) );
    update_post_meta( $post_id, 'bsync_auction_buyer_id', $buyer_id );
    update_post_meta( $post_id, 'bsync_auction_buyer_number', $buyer_id > 0 ? bsync_auction_get_buyer_number_for_user( $buyer_id ) : '' );

    $statuses = bsync_auction_get_item_statuses();
    $status   = sanitize_text_field( wp_unslash( $_POST['bsync_auction_item_status'] ?? 'available' ) );
    if ( ! isset( $statuses[ $status ] ) ) {
        $status = 'available';
    }

    // Auto-promote to sold when a sold price is provided.
    $incoming_sold_price = (float) ( $_POST['bsync_auction_sold_price_internal'] ?? 0 );
    if ( $incoming_sold_price > 0 && 'sold' !== $status && 'withdrawn' !== $status ) {
        $status = 'sold';
    }

    update_post_meta( $post_id, 'bsync_auction_item_status', $status );
}

function bsync_auction_money( $value ) {
    return number_format( (float) $value, 2, '.', '' );
}

function bsync_auction_normalize_datetime( $value ) {
    $value = sanitize_text_field( (string) $value );
    if ( '' === $value ) {
        return '';
    }

    $ts = strtotime( $value );
    if ( false === $ts ) {
        return '';
    }

    return gmdate( 'Y-m-d H:i:s', $ts );
}

function bsync_auction_to_datetime_local( $value ) {
    if ( empty( $value ) ) {
        return '';
    }

    $ts = strtotime( $value );
    if ( false === $ts ) {
        return '';
    }

    return gmdate( 'Y-m-d\\TH:i', $ts );
}

function bsync_auction_generate_next_item_number() {
    global $wpdb;

    $meta_key = 'bsync_auction_item_number';

    $max_number = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT MAX(CAST(pm.meta_value AS UNSIGNED))
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = %s
              AND p.post_type = %s
              AND p.post_status != 'trash'",
            $meta_key,
            BSYNC_AUCTION_ITEM_CPT
        )
    );

    $next = max( 1, (int) $max_number + 1 );

    while ( bsync_auction_item_number_exists( (string) $next ) ) {
        $next++;
    }

    return (string) $next;
}

function bsync_auction_item_number_exists( $item_number, $exclude_post_id = 0 ) {
    $item_number = trim( (string) $item_number );
    if ( '' === $item_number ) {
        return false;
    }

    $args = array(
        'post_type'      => BSYNC_AUCTION_ITEM_CPT,
        'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'   => 'bsync_auction_item_number',
                'value' => $item_number,
            ),
        ),
    );

    if ( $exclude_post_id > 0 ) {
        $args['post__not_in'] = array( (int) $exclude_post_id );
    }

    $posts = get_posts( $args );

    return ! empty( $posts );
}

function bsync_auction_get_buyer_number_meta_keys() {
    $default_keys = array(
        'bsync_member_number',
        'bsync_member_id_number',
        'buyer_number',
        'member_number',
    );

    $keys = apply_filters( 'bsync_auction_buyer_number_meta_keys', $default_keys );
    $keys = is_array( $keys ) ? $keys : $default_keys;

    return array_values( array_unique( array_filter( array_map( 'sanitize_key', $keys ) ) ) );
}

function bsync_auction_get_buyer_number_for_user( $user_id ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return '';
    }

    foreach ( bsync_auction_get_buyer_number_meta_keys() as $meta_key ) {
        $value = trim( (string) get_user_meta( $user_id, $meta_key, true ) );
        if ( '' !== $value ) {
            return $value;
        }
    }

    return (string) $user_id;
}

function bsync_auction_resolve_user_by_buyer_number( $buyer_number ) {
    $buyer_number = trim( sanitize_text_field( (string) $buyer_number ) );
    if ( '' === $buyer_number ) {
        return 0;
    }

    foreach ( bsync_auction_get_buyer_number_meta_keys() as $meta_key ) {
        $users = get_users(
            array(
                'meta_key'   => $meta_key,
                'meta_value' => $buyer_number,
                'number'     => 1,
                'fields'     => 'ids',
            )
        );

        if ( ! empty( $users ) ) {
            return (int) $users[0];
        }
    }

    if ( ctype_digit( $buyer_number ) ) {
        $candidate_id = (int) $buyer_number;
        if ( $candidate_id > 0 && get_user_by( 'id', $candidate_id ) ) {
            return $candidate_id;
        }
    }

    $by_login = get_user_by( 'login', $buyer_number );
    if ( $by_login instanceof WP_User ) {
        return (int) $by_login->ID;
    }

    return 0;
}
