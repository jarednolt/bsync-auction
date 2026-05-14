<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'all_admin_notices', 'bsync_auction_render_admin_tabs', 5 );
add_action( 'admin_notices', 'bsync_auction_render_items_list_import_notice' );

/**
 * Render persistent nav tabs on relevant admin screens.
 *
 * @return void
 */
function bsync_auction_render_admin_tabs() {
    if ( ! bsync_auction_can_manage_plugin() ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }

    $is_items_context    = in_array( $screen->id, array( 'edit-' . BSYNC_AUCTION_ITEM_CPT, 'auctions_page_bsync-auction-import-items' ), true );
    $is_auctions_context = ( 'edit-' . BSYNC_AUCTION_AUCTION_CPT === $screen->id );

    if ( ! $is_items_context && ! $is_auctions_context ) {
        return;
    }

    if ( $is_items_context ) {
        $items_url  = admin_url( 'edit.php?post_type=' . BSYNC_AUCTION_ITEM_CPT );
        $import_url = admin_url( 'admin.php?page=bsync-auction-import-items' );

        echo '<div class="bsync-auction-admin-tabs" style="margin:12px 0 8px;">';
        echo '<h2 class="nav-tab-wrapper" style="margin:12px 0 0;">';
        echo '<a href="' . esc_url( $items_url ) . '" class="nav-tab ' . ( 'edit-' . BSYNC_AUCTION_ITEM_CPT === $screen->id ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Items', 'bsync-auction' ) . '</a>';
        echo '<a href="' . esc_url( $import_url ) . '" class="nav-tab ' . ( 'auctions_page_bsync-auction-import-items' === $screen->id ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Import Items', 'bsync-auction' ) . '</a>';
        echo '</h2>';
        echo '<div style="clear:both;"></div>';
        echo '</div>';
    }

    if ( $is_auctions_context ) {
        $auctions_url    = admin_url( 'edit.php?post_type=' . BSYNC_AUCTION_AUCTION_CPT );
        $add_auction_url = admin_url( 'post-new.php?post_type=' . BSYNC_AUCTION_AUCTION_CPT );

        echo '<div class="bsync-auction-admin-tabs" style="margin:12px 0 8px;">';
        echo '<h2 class="nav-tab-wrapper" style="margin:12px 0 0;">';
        echo '<a href="' . esc_url( $auctions_url ) . '" class="nav-tab nav-tab-active">' . esc_html__( 'Auctions', 'bsync-auction' ) . '</a>';
        echo '<a href="' . esc_url( $add_auction_url ) . '" class="nav-tab">' . esc_html__( 'Add Auction', 'bsync-auction' ) . '</a>';
        echo '</h2>';
        echo '<div style="clear:both;"></div>';
        echo '</div>';
    }
}

/**
 * Show an Import Items action notice on the Items list page.
 *
 * @return void
 */
function bsync_auction_render_items_list_import_notice() {
    if ( ! bsync_auction_can_manage_plugin() ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || 'edit-' . BSYNC_AUCTION_ITEM_CPT !== $screen->id ) {
        return;
    }

    $import_url = admin_url( 'admin.php?page=bsync-auction-import-items' );

    $repair_results = array(
        'ran'     => false,
        'updated' => 0,
        'message' => '',
    );

    if ( isset( $_POST['bsync_auction_repair_numbers_nonce'] ) ) {
        $repair_nonce = sanitize_text_field( wp_unslash( $_POST['bsync_auction_repair_numbers_nonce'] ) );
        if ( wp_verify_nonce( $repair_nonce, 'bsync_auction_repair_item_numbers' ) ) {
            $repair_results = bsync_auction_repair_duplicate_item_numbers();
        }
    }

    if ( ! empty( $repair_results['ran'] ) ) {
        echo '<div class="notice notice-success" style="margin-top:12px;"><p>' . esc_html( (string) $repair_results['message'] ) . '</p></div>';
    }

    echo '<div class="notice notice-info" style="margin-top:12px;">';
    echo '<p>' . esc_html__( 'Need to bulk add items?', 'bsync-auction' ) . ' <a class="button button-secondary" href="' . esc_url( $import_url ) . '">' . esc_html__( 'Import Items', 'bsync-auction' ) . '</a></p>';

    echo '<form method="post" style="margin:10px 0 0;">';
    wp_nonce_field( 'bsync_auction_repair_item_numbers', 'bsync_auction_repair_numbers_nonce' );
    submit_button( __( 'Repair Duplicate Item Numbers', 'bsync-auction' ), 'secondary', 'submit', false );
    echo ' <span class="description">' . esc_html__( 'Scans all auction items and reassigns any missing/duplicate fixed item numbers.', 'bsync-auction' ) . '</span>';
    echo '</form>';

    echo '</div>';
}

/**
 * Render CSV import page for auction items.
 *
 * Supports all item fields except fixed item number.
 * Supports featured image via URL or attachment ID columns.
 */
function bsync_auction_render_import_items_page() {
    if ( ! bsync_auction_can_manage_plugin() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'bsync-auction' ) );
    }

    $results = array(
        'imported' => 0,
        'errors'   => array(),
        'warnings' => array(),
    );

    if ( isset( $_POST['bsync_auction_import_nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_POST['bsync_auction_import_nonce'] ) );
        if ( wp_verify_nonce( $nonce, 'bsync_auction_import_items' ) ) {
            $results = bsync_auction_process_item_import();
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Import Auction Items', 'bsync-auction' ) . '</h1>';

    echo '<p>' . esc_html__( 'Upload a CSV with all item fields except Item Number (Fixed). Item numbers are auto-generated.', 'bsync-auction' ) . '</p>';

    echo '<h2>' . esc_html__( 'CSV Columns', 'bsync-auction' ) . '</h2>';
    echo '<p><code>title,auction_id,order_number,opening_bid,current_bid,sold_price,buyer_number,status,featured_image_url,featured_image_id</code></p>';
    echo '<p>' . esc_html__( 'Use either featured_image_url or featured_image_id (attachment ID).', 'bsync-auction' ) . '</p>';

    if ( $results['imported'] > 0 ) {
        echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Imported %d item(s).', 'bsync-auction' ), (int) $results['imported'] ) . '</p></div>';
    }

    if ( ! empty( $results['errors'] ) ) {
        echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Import errors:', 'bsync-auction' ) . '</strong></p><ul>';
        foreach ( $results['errors'] as $error ) {
            echo '<li>' . esc_html( $error ) . '</li>';
        }
        echo '</ul></div>';
    }

    if ( ! empty( $results['warnings'] ) ) {
        echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Import warnings:', 'bsync-auction' ) . '</strong></p><ul>';
        foreach ( $results['warnings'] as $warning ) {
            echo '<li>' . esc_html( $warning ) . '</li>';
        }
        echo '</ul></div>';
    }

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field( 'bsync_auction_import_items', 'bsync_auction_import_nonce' );
    echo '<table class="form-table" role="presentation">';
    echo '<tr>';
    echo '<th scope="row"><label for="bsync_auction_csv_file">' . esc_html__( 'CSV File', 'bsync-auction' ) . '</label></th>';
    echo '<td><input type="file" id="bsync_auction_csv_file" name="bsync_auction_csv_file" accept=".csv,text/csv" required /></td>';
    echo '</tr>';
    echo '</table>';
    submit_button( __( 'Import Items', 'bsync-auction' ) );
    echo '</form>';

    echo '</div>';
}

/**
 * Parse and import auction items from uploaded CSV.
 *
 * @return array<string,mixed>
 */
function bsync_auction_process_item_import() {
    $result = array(
        'imported' => 0,
        'errors'   => array(),
        'warnings' => array(),
    );

    if ( empty( $_FILES['bsync_auction_csv_file']['tmp_name'] ) ) {
        $result['errors'][] = __( 'No CSV file uploaded.', 'bsync-auction' );
        return $result;
    }

    $tmp_path = (string) $_FILES['bsync_auction_csv_file']['tmp_name'];
    $handle   = fopen( $tmp_path, 'r' );

    if ( false === $handle ) {
        $result['errors'][] = __( 'Unable to open uploaded CSV file.', 'bsync-auction' );
        return $result;
    }

    $header = fgetcsv( $handle );
    if ( ! is_array( $header ) || empty( $header ) ) {
        fclose( $handle );
        $result['errors'][] = __( 'CSV header row is missing or invalid.', 'bsync-auction' );
        return $result;
    }

    $keys = array_map( 'sanitize_key', $header );

    $line_number = 1;

    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        $line_number++;

        if ( empty( array_filter( $row, static function( $value ) {
            return '' !== trim( (string) $value );
        } ) ) ) {
            continue;
        }

        $record = array();
        foreach ( $keys as $index => $key ) {
            $record[ $key ] = isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
        }

        $title = sanitize_text_field( $record['title'] ?? '' );
        if ( '' === $title ) {
            $result['errors'][] = sprintf( __( 'Line %d: title is required.', 'bsync-auction' ), $line_number );
            continue;
        }

        $auction_id = absint( $record['auction_id'] ?? 0 );
        if ( $auction_id <= 0 || BSYNC_AUCTION_AUCTION_CPT !== get_post_type( $auction_id ) ) {
            $result['errors'][] = sprintf( __( 'Line %d: valid auction_id is required.', 'bsync-auction' ), $line_number );
            continue;
        }

        // Strict context validation for scoped users importing items.
        $context_result = bsync_auction_resolve_strict_auction_context(
            $auction_id,
            0,
            array( 'audit_action' => 'import_item_line' )
        );
        if ( is_wp_error( $context_result ) ) {
            $result['errors'][] = sprintf( __( 'Line %d: you are not assigned to auction_id %d.', 'bsync-auction' ), $line_number, $auction_id );
            continue;
        }
        $auction_id = (int) $context_result;

        $status   = sanitize_key( $record['status'] ?? 'available' );
        $statuses = bsync_auction_get_item_statuses();
        if ( ! isset( $statuses[ $status ] ) ) {
            $status = 'available';
        }

        $raw_order_number = (string) ( $record['order_number'] ?? '' );
        $order_number     = bsync_auction_sanitize_order_number( $raw_order_number, '' );
        if ( '' === $order_number ) {
            $order_number = bsync_auction_get_next_available_order_number( $auction_id );
            $result['warnings'][] = sprintf(
                __( 'Line %1$d: missing or invalid order number. Assigned next available whole number %2$s.', 'bsync-auction' ),
                $line_number,
                $order_number
            );
        } elseif ( bsync_auction_order_number_exists( $auction_id, $order_number ) ) {
            $replacement  = bsync_auction_get_next_available_order_number( $auction_id );
            $result['warnings'][] = sprintf(
                __( 'Line %1$d: duplicate order number %2$s. Assigned next available whole number %3$s.', 'bsync-auction' ),
                $line_number,
                $order_number,
                $replacement
            );
            $order_number = $replacement;
        }
        $opening_bid  = bsync_auction_money( $record['opening_bid'] ?? 0 );
        $current_bid  = bsync_auction_money( $record['current_bid'] ?? 0 );
        $sold_price   = bsync_auction_money( $record['sold_price'] ?? 0 );

        if ( (float) $sold_price > 0 && 'withdrawn' !== $status ) {
            $status = 'sold';
        }

        $buyer_number = sanitize_text_field( $record['buyer_number'] ?? '' );
        $buyer_id     = 0;

        if ( '' !== $buyer_number ) {
            $buyer_id = bsync_auction_resolve_user_by_buyer_number( $buyer_number );
            if ( $buyer_id <= 0 ) {
                $result['warnings'][] = sprintf( __( 'Line %d: buyer number "%s" not found; item imported with no buyer.', 'bsync-auction' ), $line_number, $buyer_number );
                $buyer_number = '';
            }
        }

        $item_id = wp_insert_post(
            array(
                'post_type'   => BSYNC_AUCTION_ITEM_CPT,
                'post_title'  => $title,
                'post_status' => 'publish',
            ),
            true
        );

        if ( is_wp_error( $item_id ) ) {
            $result['errors'][] = sprintf( __( 'Line %d: failed to create item (%s).', 'bsync-auction' ), $line_number, $item_id->get_error_message() );
            continue;
        }

        // Explicitly assign the next unique fixed item number during import.
        update_post_meta( $item_id, 'bsync_auction_item_number', bsync_auction_generate_next_item_number() );

        update_post_meta( $item_id, 'bsync_auction_id', $auction_id );
        update_post_meta( $item_id, 'bsync_auction_order_number', $order_number );
        update_post_meta( $item_id, 'bsync_auction_opening_bid', $opening_bid );
        update_post_meta( $item_id, 'bsync_auction_current_bid', $current_bid );
        update_post_meta( $item_id, 'bsync_auction_sold_price_internal', $sold_price );
        update_post_meta( $item_id, 'bsync_auction_buyer_id', $buyer_id );
        update_post_meta( $item_id, 'bsync_auction_buyer_number', $buyer_number );
        update_post_meta( $item_id, 'bsync_auction_item_status', $status );

        bsync_auction_import_set_featured_image( $item_id, $record, $line_number, $result['warnings'] );

        $result['imported']++;
    }

    fclose( $handle );

    return $result;
}

/**
 * Set featured image from attachment ID or URL.
 *
 * @param int                $item_id      Post ID.
 * @param array<string,mixed> $record      Row values.
 * @param int                $line_number  CSV line number.
 * @param array<int,string>  $warnings     Warning collector (passed by reference).
 * @return void
 */
function bsync_auction_import_set_featured_image( $item_id, $record, $line_number, &$warnings ) {
    $featured_image_id = absint( $record['featured_image_id'] ?? 0 );
    if ( $featured_image_id > 0 && get_post( $featured_image_id ) ) {
        set_post_thumbnail( $item_id, $featured_image_id );
        return;
    }

    $featured_image_url = esc_url_raw( (string) ( $record['featured_image_url'] ?? '' ) );
    if ( '' === $featured_image_url ) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_sideload_image( $featured_image_url, $item_id, null, 'id' );
    if ( is_wp_error( $attachment_id ) ) {
        $warnings[] = sprintf( __( 'Line %d: featured image could not be downloaded (%s).', 'bsync-auction' ), $line_number, $attachment_id->get_error_message() );
        return;
    }

    set_post_thumbnail( $item_id, (int) $attachment_id );
}

/**
 * Repair missing/duplicate fixed item numbers across all auction items.
 *
 * @return array<string,mixed>
 */
function bsync_auction_repair_duplicate_item_numbers() {
    $items = get_posts(
        array(
            'post_type'      => BSYNC_AUCTION_ITEM_CPT,
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        )
    );

    $seen_numbers = array();
    $updated      = 0;

    foreach ( $items as $item_id ) {
        $item_id = absint( $item_id );
        if ( $item_id <= 0 ) {
            continue;
        }

        $current = trim( (string) get_post_meta( $item_id, 'bsync_auction_item_number', true ) );
        $is_duplicate = '' !== $current && isset( $seen_numbers[ $current ] );

        if ( '' === $current || $is_duplicate ) {
            $new_number = bsync_auction_generate_next_item_number();
            update_post_meta( $item_id, 'bsync_auction_item_number', $new_number );
            $seen_numbers[ $new_number ] = true;
            $updated++;
            continue;
        }

        $seen_numbers[ $current ] = true;
    }

    return array(
        'ran'     => true,
        'updated' => $updated,
        'message' => sprintf(
            /* translators: %d number of updated items */
            __( 'Item number repair complete. Updated %d item(s).', 'bsync-auction' ),
            (int) $updated
        ),
    );
}
