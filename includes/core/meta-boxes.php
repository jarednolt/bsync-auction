<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'add_meta_boxes', 'bsync_auction_register_meta_boxes' );
add_action( 'save_post', 'bsync_auction_save_meta_boxes', 10, 2 );
add_action( 'admin_enqueue_scripts', 'bsync_auction_enqueue_assignment_assets' );
add_action( 'admin_enqueue_scripts', 'bsync_auction_enqueue_item_order_assets' );
add_action( 'wp_ajax_bsync_auction_assignment_add', 'bsync_auction_ajax_assignment_add' );
add_action( 'wp_ajax_bsync_auction_assignment_remove', 'bsync_auction_ajax_assignment_remove' );
add_action( 'restrict_manage_posts', 'bsync_auction_render_bulk_assignment_controls', 10, 2 );
add_filter( 'bulk_actions-edit-bsync_auction', 'bsync_auction_register_bulk_assignment_action' );
add_filter( 'handle_bulk_actions-edit-bsync_auction', 'bsync_auction_handle_bulk_assignment_action', 10, 3 );
add_action( 'admin_notices', 'bsync_auction_render_bulk_assignment_notice' );

function bsync_auction_enqueue_assignment_assets( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || BSYNC_AUCTION_AUCTION_CPT !== $screen->post_type ) {
        return;
    }

    wp_enqueue_script(
        'bsync-auction-admin-assignments',
        BSYNC_AUCTION_PLUGIN_URL . 'assets/js/admin-assignments.js',
        array( 'jquery' ),
        BSYNC_AUCTION_VERSION,
        true
    );

    wp_localize_script(
        'bsync-auction-admin-assignments',
        'BsyncAuctionAssignments',
        array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'bsync_auction_assignments' ),
            'saving'       => __( 'Saving assignment...', 'bsync-auction' ),
            'saved'        => __( 'Assignment saved.', 'bsync-auction' ),
            'removing'     => __( 'Removing assignment...', 'bsync-auction' ),
            'removed'      => __( 'Assignment removed.', 'bsync-auction' ),
            'saveFailed'   => __( 'Could not save assignment.', 'bsync-auction' ),
            'removeFailed' => __( 'Could not remove assignment.', 'bsync-auction' ),
        )
    );
}

function bsync_auction_enqueue_item_order_assets( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || BSYNC_AUCTION_ITEM_CPT !== $screen->post_type ) {
        return;
    }

    wp_enqueue_script(
        'bsync-auction-admin-item-order',
        BSYNC_AUCTION_PLUGIN_URL . 'assets/js/admin-item-order.js',
        array( 'jquery' ),
        BSYNC_AUCTION_VERSION,
        true
    );

    wp_localize_script(
        'bsync-auction-admin-item-order',
        'BsyncAuctionItemOrder',
        array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'bsync_auction_check_order_number_duplicate' ),
            'itemId'       => absint( $_GET['post'] ?? 0 ),
            'useSuggested' => __( 'Use suggested number', 'bsync-auction' ),
            'checkFailed'  => __( 'Could not validate order number right now.', 'bsync-auction' ),
        )
    );
}

function bsync_auction_render_bulk_assignment_controls( $post_type, $which ) {
    if ( BSYNC_AUCTION_AUCTION_CPT !== $post_type || 'top' !== $which ) {
        return;
    }

    if ( ! bsync_auction_can_manage_plugin() ) {
        return;
    }

    $users = get_users(
        array(
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 300,
        )
    );

    echo '<label class="screen-reader-text" for="bsync_bulk_assign_user_id">' . esc_html__( 'Assignment User', 'bsync-auction' ) . '</label>';
    echo '<select name="bsync_bulk_assign_user_id" id="bsync_bulk_assign_user_id">';
    echo '<option value="0">' . esc_html__( 'Assign User...', 'bsync-auction' ) . '</option>';
    foreach ( $users as $user ) {
        echo '<option value="' . esc_attr( (string) $user->ID ) . '">' . esc_html( $user->display_name . ' (' . $user->user_email . ')' ) . '</option>';
    }
    echo '</select>';

    echo '<label class="screen-reader-text" for="bsync_bulk_assign_role">' . esc_html__( 'Assignment Role', 'bsync-auction' ) . '</label>';
    echo '<select name="bsync_bulk_assign_role" id="bsync_bulk_assign_role">';
    foreach ( bsync_auction_get_assignment_roles() as $role_key => $role_label ) {
        echo '<option value="' . esc_attr( $role_key ) . '">' . esc_html( $role_label ) . '</option>';
    }
    echo '</select>';
}

function bsync_auction_register_bulk_assignment_action( $actions ) {
    $actions['bsync_assign_staff'] = __( 'Assign Staff to Selected Auctions', 'bsync-auction' );

    return $actions;
}

function bsync_auction_handle_bulk_assignment_action( $redirect_to, $action, $post_ids ) {
    if ( 'bsync_assign_staff' !== $action ) {
        return $redirect_to;
    }

    if ( ! bsync_auction_can_manage_plugin() ) {
        return add_query_arg( 'bsync_bulk_assign_denied', '1', $redirect_to );
    }

    $user_id = absint( $_REQUEST['bsync_bulk_assign_user_id'] ?? 0 );
    $role    = sanitize_key( wp_unslash( $_REQUEST['bsync_bulk_assign_role'] ?? 'staff' ) );

    if ( $user_id <= 0 || ! ( get_user_by( 'id', $user_id ) instanceof WP_User ) ) {
        return add_query_arg( 'bsync_bulk_assign_invalid_user', '1', $redirect_to );
    }

    $count_success = 0;
    $count_denied  = 0;
    $count_failed  = 0;

    foreach ( (array) $post_ids as $post_id ) {
        $auction_id = absint( $post_id );

        if ( $auction_id <= 0 || BSYNC_AUCTION_AUCTION_CPT !== get_post_type( $auction_id ) ) {
            $count_failed++;
            continue;
        }

        if ( ! bsync_auction_can_manage_auction( $auction_id ) ) {
            $count_denied++;
            continue;
        }

        if ( bsync_auction_assign_user_to_auction( $auction_id, $user_id, $role ) ) {
            $count_success++;
        } else {
            $count_failed++;
        }
    }

    $redirect_to = add_query_arg( 'bsync_bulk_assign_success', (string) $count_success, $redirect_to );
    $redirect_to = add_query_arg( 'bsync_bulk_assign_denied_count', (string) $count_denied, $redirect_to );
    $redirect_to = add_query_arg( 'bsync_bulk_assign_failed', (string) $count_failed, $redirect_to );

    return $redirect_to;
}

function bsync_auction_render_bulk_assignment_notice() {
    if ( ! isset( $_REQUEST['bsync_bulk_assign_success'] ) && ! isset( $_REQUEST['bsync_bulk_assign_invalid_user'] ) && ! isset( $_REQUEST['bsync_bulk_assign_denied'] ) ) {
        return;
    }

    if ( ! bsync_auction_can_manage_plugin() ) {
        return;
    }

    if ( isset( $_REQUEST['bsync_bulk_assign_invalid_user'] ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Bulk assignment failed: choose a valid user.', 'bsync-auction' ) . '</p></div>';
        return;
    }

    if ( isset( $_REQUEST['bsync_bulk_assign_denied'] ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Bulk assignment failed: permission denied.', 'bsync-auction' ) . '</p></div>';
        return;
    }

    $success = absint( $_REQUEST['bsync_bulk_assign_success'] ?? 0 );
    $denied  = absint( $_REQUEST['bsync_bulk_assign_denied_count'] ?? 0 );
    $failed  = absint( $_REQUEST['bsync_bulk_assign_failed'] ?? 0 );

    $parts = array(
        sprintf( esc_html__( 'Assignments added: %d', 'bsync-auction' ), $success ),
    );

    if ( $denied > 0 ) {
        $parts[] = sprintf( esc_html__( 'Denied: %d', 'bsync-auction' ), $denied );
    }

    if ( $failed > 0 ) {
        $parts[] = sprintf( esc_html__( 'Failed: %d', 'bsync-auction' ), $failed );
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( implode( ' | ', $parts ) ) . '</p></div>';
}

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
    <?php if ( $post->ID > 0 ) : ?>
        <hr />
        <h4 style="margin:10px 0 6px;"><?php esc_html_e( 'Current Staff Assignments', 'bsync-auction' ); ?></h4>
        <?php echo bsync_auction_get_assignment_summary_html( $post->ID, $auctioneer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <hr />
        <p>
            <button type="button" class="button button-secondary bsync-auction-open-assignments-modal" data-target="bsync-auction-assignments-modal-<?php echo esc_attr( $post->ID ); ?>">
                <?php esc_html_e( 'Manage Staff Assignments', 'bsync-auction' ); ?>
            </button>
        </p>
        <div id="bsync-auction-assignments-modal-<?php echo esc_attr( $post->ID ); ?>" class="bsync-auction-assignments-modal bsync-admin-modal" style="display:none;">
            <div class="bsync-admin-modal-inner" style="max-width:880px;">
                <button type="button" class="button-link bsync-auction-close-assignments-modal bsync-admin-modal-close">&times;</button>
                <h3><?php esc_html_e( 'Auction Staff Assignments', 'bsync-auction' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Assign users to this auction as auctioneers or clerks.', 'bsync-auction' ); ?></p>

                <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin:12px 0;">
                    <div>
                        <label for="bsync-auction-assignment-user-<?php echo esc_attr( $post->ID ); ?>"><strong><?php esc_html_e( 'User', 'bsync-auction' ); ?></strong></label><br />
                        <select id="bsync-auction-assignment-user-<?php echo esc_attr( $post->ID ); ?>" class="bsync-auction-assignment-user">
                            <option value="0"><?php esc_html_e( 'Select User', 'bsync-auction' ); ?></option>
                            <?php foreach ( $users as $user ) : ?>
                                <option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="bsync-auction-assignment-role-<?php echo esc_attr( $post->ID ); ?>"><strong><?php esc_html_e( 'Role', 'bsync-auction' ); ?></strong></label><br />
                        <select id="bsync-auction-assignment-role-<?php echo esc_attr( $post->ID ); ?>" class="bsync-auction-assignment-role">
                            <?php foreach ( bsync_auction_get_assignment_roles() as $role_key => $role_label ) : ?>
                                <option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="button button-primary bsync-auction-assignment-add" data-auction-id="<?php echo esc_attr( $post->ID ); ?>">
                            <?php esc_html_e( 'Add Assignment', 'bsync-auction' ); ?>
                        </button>
                    </div>
                </div>

                <p class="description bsync-auction-assignment-status" aria-live="polite"></p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'User', 'bsync-auction' ); ?></th>
                            <th><?php esc_html_e( 'Role', 'bsync-auction' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'bsync-auction' ); ?></th>
                        </tr>
                    </thead>
                    <tbody class="bsync-auction-assignment-rows" data-auction-id="<?php echo esc_attr( $post->ID ); ?>">
                        <?php echo bsync_auction_get_assignment_rows_html( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else : ?>
        <hr />
        <p class="description"><?php esc_html_e( 'Save this auction first, then you can assign auction staff with the popup manager.', 'bsync-auction' ); ?></p>
    <?php endif; ?>
    <?php
}

function bsync_auction_get_assignment_rows_html( $auction_id ) {
    $auction_id   = absint( $auction_id );
    $assignments  = bsync_auction_get_assignments_for_auction( $auction_id );
    $role_labels  = bsync_auction_get_assignment_roles();
    $rendered     = 0;

    ob_start();

    foreach ( $assignments as $assignment ) {
        $user_id = absint( $assignment['user_id'] ?? 0 );
        $role    = sanitize_key( (string) ( $assignment['assignment_role'] ?? '' ) );
        $user    = get_user_by( 'id', $user_id );

        if ( ! ( $user instanceof WP_User ) ) {
            continue;
        }

        $rendered++;
        $role_label = isset( $role_labels[ $role ] ) ? $role_labels[ $role ] : ucfirst( $role );

        echo '<tr>';
        echo '<td>' . esc_html( $user->display_name . ' (' . $user->user_email . ')' ) . '</td>';
        echo '<td>' . esc_html( $role_label ) . '</td>';
        echo '<td><button type="button" class="button button-secondary bsync-auction-assignment-remove" data-auction-id="' . esc_attr( (string) $auction_id ) . '" data-user-id="' . esc_attr( (string) $user_id ) . '" data-role="' . esc_attr( $role ) . '">' . esc_html__( 'Remove', 'bsync-auction' ) . '</button></td>';
        echo '</tr>';
    }

    if ( 0 === $rendered ) {
        echo '<tr><td colspan="3">' . esc_html__( 'No staff assignments yet.', 'bsync-auction' ) . '</td></tr>';
    }

    return (string) ob_get_clean();
}

function bsync_auction_get_assignment_summary_html( $auction_id, $primary_auctioneer_id = 0 ) {
    $auction_id  = absint( $auction_id );
    $primary_auctioneer_id = absint( $primary_auctioneer_id );
    $assignments = bsync_auction_get_assignments_for_auction( $auction_id );
    $role_labels = bsync_auction_get_assignment_roles();

    ob_start();
    echo '<ul style="margin:0 0 8px 18px;">';

    $has_any = false;

    if ( $primary_auctioneer_id > 0 ) {
        $primary_user = get_user_by( 'id', $primary_auctioneer_id );
        if ( $primary_user instanceof WP_User ) {
            echo '<li><strong>' . esc_html__( 'Primary Auctioneer', 'bsync-auction' ) . ':</strong> ' . esc_html( $primary_user->display_name . ' (' . $primary_user->user_email . ')' ) . '</li>';
            $has_any = true;
        }
    }

    foreach ( $assignments as $assignment ) {
        $user_id = absint( $assignment['user_id'] ?? 0 );
        $role    = sanitize_key( (string) ( $assignment['assignment_role'] ?? '' ) );
        $user    = get_user_by( 'id', $user_id );

        if ( ! ( $user instanceof WP_User ) ) {
            continue;
        }

        $role_label = isset( $role_labels[ $role ] ) ? $role_labels[ $role ] : ucfirst( $role );

        if ( 'auctioneer' === $role && $primary_auctioneer_id > 0 && $user_id === $primary_auctioneer_id ) {
            continue;
        }

        echo '<li><strong>' . esc_html( $role_label ) . ':</strong> ' . esc_html( $user->display_name . ' (' . $user->user_email . ')' ) . '</li>';
        $has_any = true;
    }

    echo '</ul>';

    if ( ! $has_any ) {
        return '<p class="description">' . esc_html__( 'No staff assignments yet.', 'bsync-auction' ) . '</p>';
    }

    return (string) ob_get_clean();
}

function bsync_auction_ajax_assignment_add() {
    if ( ! bsync_auction_can_manage_plugin() ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bsync-auction' ) ), 403 );
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'bsync_auction_assignments' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'bsync-auction' ) ), 403 );
    }

    $auction_id = absint( $_POST['auction_id'] ?? 0 );
    $user_id    = absint( $_POST['user_id'] ?? 0 );
    $role       = sanitize_key( wp_unslash( $_POST['assignment_role'] ?? 'staff' ) );

    if ( $auction_id <= 0 || BSYNC_AUCTION_AUCTION_CPT !== get_post_type( $auction_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid auction.', 'bsync-auction' ) ), 400 );
    }

    if ( ! bsync_auction_can_manage_auction( $auction_id ) ) {
        wp_send_json_error( array( 'message' => __( 'You cannot manage this auction.', 'bsync-auction' ) ), 403 );
    }

    if ( $user_id <= 0 || ! ( get_user_by( 'id', $user_id ) instanceof WP_User ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid user.', 'bsync-auction' ) ), 400 );
    }

    if ( ! bsync_auction_assign_user_to_auction( $auction_id, $user_id, $role ) ) {
        wp_send_json_error( array( 'message' => __( 'Could not save assignment.', 'bsync-auction' ) ), 500 );
    }

    wp_send_json_success(
        array(
            'message'  => __( 'Assignment saved.', 'bsync-auction' ),
            'rowsHtml' => bsync_auction_get_assignment_rows_html( $auction_id ),
        )
    );
}

function bsync_auction_ajax_assignment_remove() {
    if ( ! bsync_auction_can_manage_plugin() ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bsync-auction' ) ), 403 );
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'bsync_auction_assignments' ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'bsync-auction' ) ), 403 );
    }

    $auction_id = absint( $_POST['auction_id'] ?? 0 );
    $user_id    = absint( $_POST['user_id'] ?? 0 );
    $role       = sanitize_key( wp_unslash( $_POST['assignment_role'] ?? '' ) );

    if ( $auction_id <= 0 || BSYNC_AUCTION_AUCTION_CPT !== get_post_type( $auction_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid auction.', 'bsync-auction' ) ), 400 );
    }

    if ( ! bsync_auction_can_manage_auction( $auction_id ) ) {
        wp_send_json_error( array( 'message' => __( 'You cannot manage this auction.', 'bsync-auction' ) ), 403 );
    }

    if ( $user_id <= 0 ) {
        wp_send_json_error( array( 'message' => __( 'Invalid user.', 'bsync-auction' ) ), 400 );
    }

    if ( ! bsync_auction_remove_assignment( $auction_id, $user_id, $role ) ) {
        wp_send_json_error( array( 'message' => __( 'Could not remove assignment.', 'bsync-auction' ) ), 500 );
    }

    wp_send_json_success(
        array(
            'message'  => __( 'Assignment removed.', 'bsync-auction' ),
            'rowsHtml' => bsync_auction_get_assignment_rows_html( $auction_id ),
        )
    );
}

function bsync_auction_render_item_meta_box( $post ) {
    wp_nonce_field( 'bsync_auction_save_meta', 'bsync_auction_meta_nonce' );

    $item_number = get_post_meta( $post->ID, 'bsync_auction_item_number', true );
    $order_num   = get_post_meta( $post->ID, 'bsync_auction_order_number', true );
    $auction_id  = (int) get_post_meta( $post->ID, 'bsync_auction_id', true );

    if ( '' === (string) $item_number ) {
        $item_number = bsync_auction_generate_next_item_number();
    }

    if ( '' === (string) $order_num ) {
        $order_num = bsync_auction_get_next_available_order_number( $auction_id, (int) $post->ID );
    }
    $opening_bid = get_post_meta( $post->ID, 'bsync_auction_opening_bid', true );
    $current_bid = get_post_meta( $post->ID, 'bsync_auction_current_bid', true );
    $sold_price  = get_post_meta( $post->ID, 'bsync_auction_sold_price_internal', true );
    $buyer_id    = (int) get_post_meta( $post->ID, 'bsync_auction_buyer_id', true );
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
            <td>
                <input type="number" id="bsync_auction_order_number" name="bsync_auction_order_number" value="<?php echo esc_attr( $order_num ); ?>" min="1" step="0.01" />
                <p class="description"><?php esc_html_e( 'Must be unique within the selected auction. New items default to the next available whole number; decimals can be used for mid-sale insertions.', 'bsync-auction' ); ?></p>
                <div id="bsync_auction_order_duplicate_alert" class="notice notice-warning inline" style="display:none;margin:8px 0 0;">
                    <p style="margin:8px 0;">
                        <span class="bsync-auction-order-duplicate-text" aria-live="polite"></span>
                        <button type="button" class="button-link bsync-auction-order-apply-suggested" data-suggested="" style="margin-left:4px;"></button>
                    </p>
                </div>
            </td>
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
        $previous_auctioneer_id = (int) get_post_meta( $post_id, 'bsync_auction_auctioneer_id', true );
        $new_auctioneer_id      = absint( $_POST['bsync_auction_auctioneer_id'] ?? 0 );

        update_post_meta( $post_id, 'bsync_auction_location', sanitize_text_field( wp_unslash( $_POST['bsync_auction_location'] ?? '' ) ) );
        update_post_meta( $post_id, 'bsync_auction_address', sanitize_textarea_field( wp_unslash( $_POST['bsync_auction_address'] ?? '' ) ) );
        update_post_meta( $post_id, 'bsync_auction_auctioneer_id', $new_auctioneer_id );
        update_post_meta( $post_id, 'bsync_auction_starts_at', bsync_auction_normalize_datetime( wp_unslash( $_POST['bsync_auction_starts_at'] ?? '' ) ) );
        update_post_meta( $post_id, 'bsync_auction_ends_at', bsync_auction_normalize_datetime( wp_unslash( $_POST['bsync_auction_ends_at'] ?? '' ) ) );

        if ( $previous_auctioneer_id > 0 && $previous_auctioneer_id !== $new_auctioneer_id ) {
            bsync_auction_remove_assignment( $post_id, $previous_auctioneer_id, 'auctioneer' );
        }

        if ( $new_auctioneer_id > 0 ) {
            bsync_auction_assign_user_to_auction( $post_id, $new_auctioneer_id, 'auctioneer' );
        }

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

    $buyer_id   = absint( $_POST['bsync_auction_buyer_id'] ?? 0 );
    $auction_id = absint( $_POST['bsync_auction_id'] ?? 0 );

    $raw_order_number = wp_unslash( $_POST['bsync_auction_order_number'] ?? '' );
    $order_number     = bsync_auction_sanitize_order_number( $raw_order_number, '' );

    if ( '' === $order_number ) {
        $order_number = bsync_auction_get_next_available_order_number( $auction_id, $post_id );
    }

    if ( bsync_auction_order_number_exists( $auction_id, $order_number, $post_id ) ) {
        $order_number = bsync_auction_get_next_available_order_number( $auction_id, $post_id );
    }

    update_post_meta( $post_id, 'bsync_auction_id', $auction_id );
    update_post_meta( $post_id, 'bsync_auction_order_number', $order_number );
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

/**
 * Normalize order number input to >= 1 and string format.
 *
 * @param mixed  $value   Raw input value.
 * @param string $default Default return when input is invalid.
 * @return string
 */
function bsync_auction_sanitize_order_number( $value, $default = '1' ) {
    $value = trim( sanitize_text_field( (string) $value ) );
    if ( '' === $value || ! is_numeric( $value ) ) {
        return (string) $default;
    }

    $numeric = round( (float) $value, 2 );
    if ( $numeric < 1 ) {
        return (string) $default;
    }

    if ( abs( $numeric - round( $numeric ) ) < 0.00001 ) {
        return (string) (int) round( $numeric );
    }

    return rtrim( rtrim( number_format( $numeric, 2, '.', '' ), '0' ), '.' );
}

/**
 * Check if an order number already exists for an auction.
 *
 * @param int         $auction_id       Auction ID.
 * @param string|int|float $order_number Order number to test.
 * @param int         $exclude_post_id  Optional item post ID to exclude.
 * @return bool
 */
function bsync_auction_order_number_exists( $auction_id, $order_number, $exclude_post_id = 0 ) {
    $auction_id    = absint( $auction_id );
    $exclude_post_id = absint( $exclude_post_id );
    $normalized    = bsync_auction_sanitize_order_number( $order_number, '' );

    if ( '' === $normalized ) {
        return false;
    }

    $target = (float) $normalized;

    $query = array(
        'post_type'      => BSYNC_AUCTION_ITEM_CPT,
        'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(),
    );

    if ( $auction_id > 0 ) {
        $query['meta_query'][] = array(
            'key'   => 'bsync_auction_id',
            'value' => $auction_id,
        );
    } else {
        $query['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key'     => 'bsync_auction_id',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'   => 'bsync_auction_id',
                'value' => 0,
            ),
        );
    }

    $item_ids = get_posts( $query );

    foreach ( $item_ids as $item_id ) {
        $item_id = (int) $item_id;

        if ( $exclude_post_id > 0 && $item_id === $exclude_post_id ) {
            continue;
        }

        $existing = bsync_auction_sanitize_order_number( get_post_meta( $item_id, 'bsync_auction_order_number', true ), '' );
        if ( '' === $existing ) {
            continue;
        }

        if ( abs( (float) $existing - $target ) < 0.00001 ) {
            return true;
        }
    }

    return false;
}

/**
 * Get the next available whole-number order position for an auction.
 *
 * @param int $auction_id      Auction ID.
 * @param int $exclude_post_id Optional item post ID to exclude.
 * @return string
 */
function bsync_auction_get_next_available_order_number( $auction_id, $exclude_post_id = 0 ) {
    $auction_id       = absint( $auction_id );
    $exclude_post_id  = absint( $exclude_post_id );
    $candidate        = 1;

    while ( bsync_auction_order_number_exists( $auction_id, (string) $candidate, $exclude_post_id ) ) {
        $candidate++;
    }

    return (string) $candidate;
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
