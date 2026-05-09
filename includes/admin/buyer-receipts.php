<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_enqueue_scripts', 'bsync_auction_enqueue_buyer_receipt_assets' );
add_action( 'wp_ajax_bsync_auction_send_buyer_receipt', 'bsync_auction_ajax_send_buyer_receipt' );
add_action( 'wp_ajax_bsync_auction_save_buyer_receipt_payment', 'bsync_auction_ajax_save_buyer_receipt_payment' );

/**
 * Get valid buyer receipt payment statuses.
 *
 * @return array<string,string>
 */
function bsync_auction_get_receipt_payment_statuses() {
    return array(
        'unpaid'         => __( 'Unpaid', 'bsync-auction' ),
        'partially_paid' => __( 'Partially Paid', 'bsync-auction' ),
        'paid'           => __( 'Paid', 'bsync-auction' ),
    );
}

/**
 * Resolve buyer receipt payment data for a buyer and auction context.
 *
 * @param int $buyer_id    Buyer user ID.
 * @param int $auction_id  Auction context (0 for all auctions).
 * @return array<string,mixed>
 */
function bsync_auction_get_buyer_receipt_payment_data( $buyer_id, $auction_id = 0 ) {
    $buyer_id   = absint( $buyer_id );
    $auction_id = absint( $auction_id );

    $default = array(
        'status'       => 'unpaid',
        'pay_cash'     => 0,
        'pay_check'    => 0,
        'pay_card'     => 0,
        'check_number' => '',
    );

    if ( $buyer_id <= 0 ) {
        return $default;
    }

    $all = get_user_meta( $buyer_id, 'bsync_auction_receipt_payment_data', true );
    if ( ! is_array( $all ) ) {
        return $default;
    }

    $key = (string) $auction_id;
    if ( empty( $all[ $key ] ) || ! is_array( $all[ $key ] ) ) {
        return $default;
    }

    $saved    = $all[ $key ];
    $statuses = bsync_auction_get_receipt_payment_statuses();
    $status   = isset( $saved['status'] ) ? sanitize_key( (string) $saved['status'] ) : 'unpaid';

    if ( ! isset( $statuses[ $status ] ) ) {
        $status = 'unpaid';
    }

    return array(
        'status'       => $status,
        'pay_cash'     => empty( $saved['pay_cash'] ) ? 0 : 1,
        'pay_check'    => empty( $saved['pay_check'] ) ? 0 : 1,
        'pay_card'     => empty( $saved['pay_card'] ) ? 0 : 1,
        'check_number' => isset( $saved['check_number'] ) ? sanitize_text_field( (string) $saved['check_number'] ) : '',
    );
}

/**
 * Persist buyer receipt payment data.
 *
 * @param int   $buyer_id   Buyer user ID.
 * @param int   $auction_id Auction context (0 for all auctions).
 * @param array $payment    Payment data payload.
 * @return bool
 */
function bsync_auction_save_buyer_receipt_payment_data( $buyer_id, $auction_id, $payment ) {
    $buyer_id   = absint( $buyer_id );
    $auction_id = absint( $auction_id );

    if ( $buyer_id <= 0 || ! is_array( $payment ) ) {
        return false;
    }

    $statuses = bsync_auction_get_receipt_payment_statuses();
    $status   = isset( $payment['status'] ) ? sanitize_key( (string) $payment['status'] ) : 'unpaid';
    if ( ! isset( $statuses[ $status ] ) ) {
        $status = 'unpaid';
    }

    $all = get_user_meta( $buyer_id, 'bsync_auction_receipt_payment_data', true );
    if ( ! is_array( $all ) ) {
        $all = array();
    }

    $key = (string) $auction_id;
    $new_data = array(
        'status'       => $status,
        'pay_cash'     => empty( $payment['pay_cash'] ) ? 0 : 1,
        'pay_check'    => empty( $payment['pay_check'] ) ? 0 : 1,
        'pay_card'     => empty( $payment['pay_card'] ) ? 0 : 1,
        'check_number' => isset( $payment['check_number'] ) ? sanitize_text_field( (string) $payment['check_number'] ) : '',
    );

    $existing = isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : null;
    if ( $existing === $new_data ) {
        return true;
    }

    $all[ $key ] = $new_data;

    return (bool) update_user_meta( $buyer_id, 'bsync_auction_receipt_payment_data', $all );
}

/**
 * Set HTML mail content type for receipt emails.
 *
 * @return string
 */
function bsync_auction_receipt_mail_content_type() {
    return 'text/html';
}

/**
 * Enqueue scripts only on Buyer Receipts page.
 *
 * @param string $hook Admin hook suffix.
 * @return void
 */
function bsync_auction_enqueue_buyer_receipt_assets( $hook ) {
    if ( 'auctions_page_bsync-auction-buyer-receipts' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'bsync-auction-buyer-receipts',
        BSYNC_AUCTION_PLUGIN_URL . 'assets/js/admin-buyers.js',
        array( 'jquery' ),
        BSYNC_AUCTION_VERSION,
        true
    );

    wp_localize_script(
        'bsync-auction-buyer-receipts',
        'BsyncAuctionBuyerReceipts',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bsync_auction_send_buyer_receipt' ),
            'sending' => __( 'Sending receipt...', 'bsync-auction' ),
            'sent'    => __( 'Receipt emailed successfully.', 'bsync-auction' ),
            'failed'  => __( 'Could not send receipt.', 'bsync-auction' ),
            'saving'  => __( 'Saving payment status...', 'bsync-auction' ),
            'saved'   => __( 'Payment status saved.', 'bsync-auction' ),
            'saveFailed' => __( 'Could not save payment status.', 'bsync-auction' ),
        )
    );
}

/**
 * Render buyer totals and receipt popups.
 *
 * @return void
 */
function bsync_auction_render_buyer_receipts_page() {
    if ( ! current_user_can( BSYNC_AUCTION_MANAGE_CAP ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'bsync-auction' ) );
    }

    $auction_filter = absint( $_GET['auction_id'] ?? 0 );

    $auctions = get_posts(
        array(
            'post_type'      => BSYNC_AUCTION_AUCTION_CPT,
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $buyers = bsync_auction_collect_buyer_receipt_data( $auction_filter );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Buyer Totals & Receipts', 'bsync-auction' ) . '</h1>';
    echo '<p>' . esc_html__( 'Review buyer totals, open receipt popups, choose payment method(s), print, and send email receipts.', 'bsync-auction' ) . '</p>';

    echo '<form method="get" action="' . esc_url( admin_url( 'edit.php' ) ) . '" style="margin:16px 0;">';
    echo '<input type="hidden" name="post_type" value="' . esc_attr( BSYNC_AUCTION_AUCTION_CPT ) . '" />';
    echo '<input type="hidden" name="page" value="bsync-auction-buyer-receipts" />';
    echo '<label for="auction_id"><strong>' . esc_html__( 'Filter by Auction:', 'bsync-auction' ) . '</strong></label> ';
    echo '<select id="auction_id" name="auction_id">';
    echo '<option value="0">' . esc_html__( 'All Auctions', 'bsync-auction' ) . '</option>';
    foreach ( $auctions as $auction ) {
        echo '<option value="' . esc_attr( $auction->ID ) . '" ' . selected( $auction_filter, $auction->ID, false ) . '>' . esc_html( $auction->post_title ) . '</option>';
    }
    echo '</select> ';
    submit_button( __( 'Filter', 'bsync-auction' ), 'secondary', '', false );
    echo '</form>';

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Buyer Name', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Buyer Number', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Paid Status', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Total', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Items', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Action', 'bsync-auction' ) . '</th>';
    echo '</tr></thead><tbody>';

    if ( empty( $buyers ) ) {
        echo '<tr><td colspan="6">' . esc_html__( 'No purchased items with buyer assignments found.', 'bsync-auction' ) . '</td></tr>';
    }

    $status_labels = bsync_auction_get_receipt_payment_statuses();

    foreach ( $buyers as $buyer_id => $data ) {
        $modal_id = 'bsync-auction-receipt-modal-' . (int) $buyer_id;
        $status_key = isset( $data['payment']['status'] ) ? (string) $data['payment']['status'] : 'unpaid';
        $status_label = isset( $status_labels[ $status_key ] ) ? $status_labels[ $status_key ] : $status_labels['unpaid'];

        echo '<tr>';
        echo '<td>' . esc_html( $data['display_name'] ) . '</td>';
        echo '<td>' . esc_html( $data['buyer_number'] ) . '</td>';
        echo '<td><span class="bsync-auction-paid-status" data-buyer-id="' . esc_attr( (string) $buyer_id ) . '">' . esc_html( $status_label ) . '</span></td>';
        echo '<td>' . esc_html( bsync_auction_format_money( $data['total'] ) ) . '</td>';
        echo '<td>' . esc_html( (string) count( $data['items'] ) ) . '</td>';
        echo '<td><button type="button" class="button button-primary bsync-auction-open-receipt" data-modal-id="' . esc_attr( $modal_id ) . '">' . esc_html__( 'Open Receipt', 'bsync-auction' ) . '</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    foreach ( $buyers as $buyer_id => $data ) {
        bsync_auction_render_buyer_receipt_modal( (int) $buyer_id, $data, $auction_filter );
    }

    echo '</div>';
}

/**
 * Collect buyer totals and item lists.
 *
 * @param int $auction_filter Auction ID filter or 0.
 * @return array<int,array<string,mixed>>
 */
function bsync_auction_collect_buyer_receipt_data( $auction_filter = 0 ) {
    $query = array(
        'post_type'      => BSYNC_AUCTION_ITEM_CPT,
        'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
        'posts_per_page' => 2000,
        'meta_query'     => array(
            array(
                'key'     => 'bsync_auction_buyer_id',
                'value'   => 0,
                'type'    => 'NUMERIC',
                'compare' => '>',
            ),
        ),
    );

    if ( $auction_filter > 0 ) {
        $query['meta_query'][] = array(
            'key'   => 'bsync_auction_id',
            'value' => $auction_filter,
        );
    }

    $items  = get_posts( $query );
    $buyers = array();

    foreach ( $items as $item ) {
        $item_id   = (int) $item->ID;
        $buyer_id  = (int) get_post_meta( $item_id, 'bsync_auction_buyer_id', true );
        $sold      = (float) get_post_meta( $item_id, 'bsync_auction_sold_price_internal', true );
        $status    = (string) get_post_meta( $item_id, 'bsync_auction_item_status', true );

        if ( $buyer_id <= 0 || $sold <= 0 || 'withdrawn' === $status ) {
            continue;
        }

        if ( ! isset( $buyers[ $buyer_id ] ) ) {
            $buyer = get_user_by( 'id', $buyer_id );
            if ( ! ( $buyer instanceof WP_User ) ) {
                continue;
            }

            $buyers[ $buyer_id ] = array(
                'display_name' => $buyer->display_name,
                'email'        => $buyer->user_email,
                'buyer_number' => bsync_auction_get_buyer_number_for_user( $buyer_id ),
                'phone'        => (string) get_user_meta( $buyer_id, 'bsync_member_main_phone', true ),
                'payment'      => bsync_auction_get_buyer_receipt_payment_data( $buyer_id, $auction_filter ),
                'total'        => 0,
                'items'        => array(),
            );
        }

        $buyers[ $buyer_id ]['total'] += $sold;
        $buyers[ $buyer_id ]['items'][] = array(
            'item_id'      => $item_id,
            'item_number'  => (string) get_post_meta( $item_id, 'bsync_auction_item_number', true ),
            'title'        => get_the_title( $item_id ),
            'sold_price'   => $sold,
            'auction_name' => get_the_title( (int) get_post_meta( $item_id, 'bsync_auction_id', true ) ),
        );
    }

    uasort(
        $buyers,
        static function( $a, $b ) {
            return strcmp( (string) $a['display_name'], (string) $b['display_name'] );
        }
    );

    return $buyers;
}

/**
 * Render a modal for a buyer receipt.
 *
 * @param int   $buyer_id Buyer user ID.
 * @param array $data     Buyer receipt data.
 * @param int   $auction_filter Active filter.
 * @return void
 */
function bsync_auction_render_buyer_receipt_modal( $buyer_id, $data, $auction_filter ) {
    $modal_id = 'bsync-auction-receipt-modal-' . $buyer_id;
    $item_ids = array();
    $payment_statuses = bsync_auction_get_receipt_payment_statuses();
    $payment = isset( $data['payment'] ) && is_array( $data['payment'] )
        ? $data['payment']
        : bsync_auction_get_buyer_receipt_payment_data( $buyer_id, $auction_filter );
    $payment_status = isset( $payment['status'] ) ? (string) $payment['status'] : 'unpaid';

    if ( ! isset( $payment_statuses[ $payment_status ] ) ) {
        $payment_status = 'unpaid';
    }

    echo '<div id="' . esc_attr( $modal_id ) . '" class="bsync-auction-receipt-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:100000;">';
    echo '<div style="max-width:950px;margin:35px auto;background:#fff;border-radius:8px;padding:20px;max-height:86vh;overflow:auto;position:relative;">';
    echo '<button type="button" class="button-link bsync-auction-close-modal" style="position:absolute;right:14px;top:10px;font-size:20px;line-height:1;">&times;</button>';
    echo '<h2>' . esc_html__( 'Buyer Receipt', 'bsync-auction' ) . '</h2>';

    echo '<div class="bsync-auction-receipt-printable">';
    echo '<p><strong>' . esc_html__( 'Buyer:', 'bsync-auction' ) . '</strong> ' . esc_html( $data['display_name'] ) . '</p>';
    echo '<p><strong>' . esc_html__( 'Buyer Number:', 'bsync-auction' ) . '</strong> ' . esc_html( $data['buyer_number'] ) . '</p>';
    echo '<p><strong>' . esc_html__( 'Email:', 'bsync-auction' ) . '</strong> ' . esc_html( $data['email'] ) . '</p>';
    if ( '' !== $data['phone'] ) {
        echo '<p><strong>' . esc_html__( 'Phone:', 'bsync-auction' ) . '</strong> ' . esc_html( $data['phone'] ) . '</p>';
    }

    echo '<h3>' . esc_html__( 'Purchased Items', 'bsync-auction' ) . '</h3>';
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>' . esc_html__( 'Item #', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Title', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Auction', 'bsync-auction' ) . '</th>';
    echo '<th>' . esc_html__( 'Price', 'bsync-auction' ) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ( $data['items'] as $item ) {
        $item_ids[] = (int) $item['item_id'];
        echo '<tr>';
        echo '<td>' . esc_html( (string) $item['item_number'] ) . '</td>';
        echo '<td>' . esc_html( (string) $item['title'] ) . '</td>';
        echo '<td>' . esc_html( (string) $item['auction_name'] ) . '</td>';
        echo '<td>' . esc_html( bsync_auction_format_money( $item['sold_price'] ) ) . '</td>';
        echo '</tr>';
    }

    echo '<tr>';
    echo '<td colspan="3"><strong>' . esc_html__( 'Total', 'bsync-auction' ) . '</strong></td>';
    echo '<td><strong>' . esc_html( bsync_auction_format_money( $data['total'] ) ) . '</strong></td>';
    echo '</tr>';
    echo '</tbody></table>';

    echo '<h3 style="margin-top:18px;">' . esc_html__( 'Payment', 'bsync-auction' ) . '</h3>';
    echo '<p><strong>' . esc_html__( 'Paid Status:', 'bsync-auction' ) . '</strong> ';
    foreach ( $payment_statuses as $status_key => $status_label ) {
        echo '<label style="margin-right:12px;"><input type="radio" name="bsync_auction_payment_status_' . esc_attr( (string) $buyer_id ) . '" class="bsync-auction-payment-status" value="' . esc_attr( $status_key ) . '" ' . checked( $payment_status, $status_key, false ) . ' /> ' . esc_html( $status_label ) . '</label>';
    }
    echo '</p>';
    echo '<p><label><input type="checkbox" class="bsync-auction-pay-cash" ' . checked( ! empty( $payment['pay_cash'] ), true, false ) . ' /> ' . esc_html__( 'Cash', 'bsync-auction' ) . '</label> ';
    echo '<label><input type="checkbox" class="bsync-auction-pay-check" ' . checked( ! empty( $payment['pay_check'] ), true, false ) . ' /> ' . esc_html__( 'Check', 'bsync-auction' ) . '</label> ';
    echo '<label><input type="checkbox" class="bsync-auction-pay-card" ' . checked( ! empty( $payment['pay_card'] ), true, false ) . ' /> ' . esc_html__( 'Credit Card', 'bsync-auction' ) . '</label></p>';
    echo '<p><label>' . esc_html__( 'Check Number:', 'bsync-auction' ) . ' <input type="text" class="regular-text bsync-auction-check-number" value="' . esc_attr( (string) $payment['check_number'] ) . '" /></label></p>';

    echo '</div>';

    echo '<p style="margin-top:16px;">';
    echo '<button type="button" class="button button-secondary bsync-auction-save-payment" data-buyer-id="' . esc_attr( $buyer_id ) . '" data-auction-id="' . esc_attr( $auction_filter ) . '">' . esc_html__( 'Save Payment Status', 'bsync-auction' ) . '</button> ';
    echo '<button type="button" class="button bsync-auction-print-receipt">' . esc_html__( 'Print Receipt', 'bsync-auction' ) . '</button> ';
    echo '<button type="button" class="button button-primary bsync-auction-email-receipt" data-buyer-id="' . esc_attr( $buyer_id ) . '" data-item-ids="' . esc_attr( implode( ',', $item_ids ) ) . '" data-auction-id="' . esc_attr( $auction_filter ) . '">' . esc_html__( 'Send Receipt Email', 'bsync-auction' ) . '</button> ';
    echo '<span class="bsync-auction-email-status" aria-live="polite"></span>';
    echo '</p>';

    echo '</div>';
    echo '</div>';
}

/**
 * Build receipt HTML for email.
 *
 * @param WP_User $buyer Buyer user.
 * @param array   $items Item rows.
 * @param array   $payment Payment fields.
 * @return string
 */
function bsync_auction_build_receipt_email_html( $buyer, $items, $payment ) {
    $total = 0;

    ob_start();
    ?>
    <h2><?php esc_html_e( 'Auction Receipt', 'bsync-auction' ); ?></h2>
    <p><strong><?php esc_html_e( 'Buyer:', 'bsync-auction' ); ?></strong> <?php echo esc_html( $buyer->display_name ); ?></p>
    <p><strong><?php esc_html_e( 'Buyer Number:', 'bsync-auction' ); ?></strong> <?php echo esc_html( bsync_auction_get_buyer_number_for_user( $buyer->ID ) ); ?></p>
    <table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;">
        <thead>
            <tr>
                <th align="left"><?php esc_html_e( 'Item #', 'bsync-auction' ); ?></th>
                <th align="left"><?php esc_html_e( 'Title', 'bsync-auction' ); ?></th>
                <th align="left"><?php esc_html_e( 'Price', 'bsync-auction' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $items as $item ) : ?>
                <?php $total += (float) $item['sold_price']; ?>
                <tr>
                    <td><?php echo esc_html( (string) $item['item_number'] ); ?></td>
                    <td><?php echo esc_html( (string) $item['title'] ); ?></td>
                    <td><?php echo esc_html( bsync_auction_format_money( (float) $item['sold_price'] ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="2"><strong><?php esc_html_e( 'Total', 'bsync-auction' ); ?></strong></td>
                <td><strong><?php echo esc_html( bsync_auction_format_money( $total ) ); ?></strong></td>
            </tr>
        </tbody>
    </table>
    <p><strong><?php esc_html_e( 'Payment Methods:', 'bsync-auction' ); ?></strong> <?php echo esc_html( implode( ', ', $payment['methods'] ) ); ?></p>
    <?php if ( '' !== $payment['check_number'] ) : ?>
        <p><strong><?php esc_html_e( 'Check Number:', 'bsync-auction' ); ?></strong> <?php echo esc_html( $payment['check_number'] ); ?></p>
    <?php endif; ?>
    <?php

    return (string) ob_get_clean();
}

/**
 * Send receipt email for a buyer.
 *
 * @return void
 */
function bsync_auction_ajax_send_buyer_receipt() {
    if ( ! current_user_can( BSYNC_AUCTION_MANAGE_CAP ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bsync-auction' ) ), 403 );
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'bsync_auction_send_buyer_receipt' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'bsync-auction' ) ), 403 );
    }

    $buyer_id    = absint( $_POST['buyer_id'] ?? 0 );
    $item_ids_raw = sanitize_text_field( wp_unslash( $_POST['item_ids'] ?? '' ) );
    $item_ids    = array_filter( array_map( 'absint', explode( ',', $item_ids_raw ) ) );

    $buyer = get_user_by( 'id', $buyer_id );
    if ( ! ( $buyer instanceof WP_User ) || empty( $buyer->user_email ) ) {
        wp_send_json_error( array( 'message' => __( 'Buyer email is missing or invalid.', 'bsync-auction' ) ), 400 );
    }

    if ( empty( $item_ids ) ) {
        wp_send_json_error( array( 'message' => __( 'No items selected for receipt.', 'bsync-auction' ) ), 400 );
    }

    $payment_statuses = bsync_auction_get_receipt_payment_statuses();
    $payment_status = sanitize_key( wp_unslash( $_POST['payment_status'] ?? 'unpaid' ) );
    if ( ! isset( $payment_statuses[ $payment_status ] ) ) {
        $payment_status = 'unpaid';
    }

    $pay_cash = empty( $_POST['pay_cash'] ) ? 0 : 1;
    $pay_check = empty( $_POST['pay_check'] ) ? 0 : 1;
    $pay_card = empty( $_POST['pay_card'] ) ? 0 : 1;

    $methods = array();
    if ( $pay_cash ) {
        $methods[] = __( 'Cash', 'bsync-auction' );
    }
    if ( $pay_check ) {
        $methods[] = __( 'Check', 'bsync-auction' );
    }
    if ( $pay_card ) {
        $methods[] = __( 'Credit Card', 'bsync-auction' );
    }

    if ( empty( $methods ) ) {
        $methods[] = __( 'Unspecified', 'bsync-auction' );
    }

    $check_number = sanitize_text_field( wp_unslash( $_POST['check_number'] ?? '' ) );

    $items = array();

    foreach ( $item_ids as $item_id ) {
        if ( BSYNC_AUCTION_ITEM_CPT !== get_post_type( $item_id ) ) {
            continue;
        }

        $item_buyer = (int) get_post_meta( $item_id, 'bsync_auction_buyer_id', true );
        if ( $item_buyer !== $buyer_id ) {
            continue;
        }

        $items[] = array(
            'item_number' => (string) get_post_meta( $item_id, 'bsync_auction_item_number', true ),
            'title'       => get_the_title( $item_id ),
            'sold_price'  => (float) get_post_meta( $item_id, 'bsync_auction_sold_price_internal', true ),
        );
    }

    if ( empty( $items ) ) {
        wp_send_json_error( array( 'message' => __( 'No valid buyer items found for receipt.', 'bsync-auction' ) ), 400 );
    }

    $payment = array(
        'status'       => $payment_status,
        'methods'      => $methods,
        'pay_cash'     => $pay_cash,
        'pay_check'    => $pay_check,
        'pay_card'     => $pay_card,
        'check_number' => $check_number,
    );

    $save_result = bsync_auction_save_buyer_receipt_payment_data(
        $buyer_id,
        absint( $_POST['auction_id'] ?? 0 ),
        $payment
    );

    if ( false === $save_result ) {
        wp_send_json_error( array( 'message' => __( 'Could not save payment data.', 'bsync-auction' ) ), 500 );
    }

    $subject = sprintf( __( 'Auction Receipt - %s', 'bsync-auction' ), get_bloginfo( 'name' ) );
    $body    = bsync_auction_build_receipt_email_html( $buyer, $items, $payment );

    add_filter( 'wp_mail_content_type', 'bsync_auction_receipt_mail_content_type' );

    $sent = wp_mail( $buyer->user_email, $subject, $body );

    remove_filter( 'wp_mail_content_type', 'bsync_auction_receipt_mail_content_type' );

    if ( ! $sent ) {
        wp_send_json_error( array( 'message' => __( 'wp_mail failed to send receipt.', 'bsync-auction' ) ), 500 );
    }

    wp_send_json_success(
        array(
            'message'      => __( 'Receipt emailed.', 'bsync-auction' ),
            'paid_status'  => $payment_status,
            'paid_label'   => $payment_statuses[ $payment_status ],
        )
    );
}

/**
 * Save buyer receipt payment data without emailing.
 *
 * @return void
 */
function bsync_auction_ajax_save_buyer_receipt_payment() {
    if ( ! current_user_can( BSYNC_AUCTION_MANAGE_CAP ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bsync-auction' ) ), 403 );
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'bsync_auction_send_buyer_receipt' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'bsync-auction' ) ), 403 );
    }

    $buyer_id    = absint( $_POST['buyer_id'] ?? 0 );
    $auction_id  = absint( $_POST['auction_id'] ?? 0 );

    if ( $buyer_id <= 0 || ! ( get_user_by( 'id', $buyer_id ) instanceof WP_User ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid buyer.', 'bsync-auction' ) ), 400 );
    }

    $statuses = bsync_auction_get_receipt_payment_statuses();
    $status   = sanitize_key( wp_unslash( $_POST['payment_status'] ?? 'unpaid' ) );

    if ( ! isset( $statuses[ $status ] ) ) {
        $status = 'unpaid';
    }

    $saved = bsync_auction_save_buyer_receipt_payment_data(
        $buyer_id,
        $auction_id,
        array(
            'status'       => $status,
            'pay_cash'     => empty( $_POST['pay_cash'] ) ? 0 : 1,
            'pay_check'    => empty( $_POST['pay_check'] ) ? 0 : 1,
            'pay_card'     => empty( $_POST['pay_card'] ) ? 0 : 1,
            'check_number' => sanitize_text_field( wp_unslash( $_POST['check_number'] ?? '' ) ),
        )
    );

    if ( false === $saved ) {
        wp_send_json_error( array( 'message' => __( 'Could not save payment data.', 'bsync-auction' ) ), 500 );
    }

    wp_send_json_success(
        array(
            'message'     => __( 'Payment status saved.', 'bsync-auction' ),
            'paid_status' => $status,
            'paid_label'  => $statuses[ $status ],
        )
    );
}
