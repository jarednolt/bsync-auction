<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'bsync_auction_register_admin_menus' );

function bsync_auction_register_admin_menus() {
    $parent_slug = 'edit.php?post_type=' . BSYNC_AUCTION_AUCTION_CPT;

    add_menu_page(
        __( 'Auctions', 'bsync-auction' ),
        __( 'Auctions', 'bsync-auction' ),
        BSYNC_AUCTION_MANAGE_CAP,
        $parent_slug,
        '',
        'dashicons-store',
        29
    );

    add_submenu_page(
        $parent_slug,
        __( 'Items', 'bsync-auction' ),
        __( 'Items', 'bsync-auction' ),
        BSYNC_AUCTION_MANAGE_CAP,
        'edit.php?post_type=' . BSYNC_AUCTION_ITEM_CPT
    );

    add_submenu_page(
        $parent_slug,
        __( 'Manager Item Grid', 'bsync-auction' ),
        __( 'Manager Item Grid', 'bsync-auction' ),
        'bsync_manage_members',
        'bsync-auction-manager-grid',
        'bsync_auction_render_manager_item_grid_page'
    );

    // Register importer as a hidden page; linked from Items list tabs instead.
    add_submenu_page(
        null,
        __( 'Import Items', 'bsync-auction' ),
        __( 'Import Items', 'bsync-auction' ),
        BSYNC_AUCTION_MANAGE_CAP,
        'bsync-auction-import-items',
        'bsync_auction_render_import_items_page'
    );

    add_submenu_page(
        $parent_slug,
        __( 'Buyer Receipts', 'bsync-auction' ),
        __( 'Buyer Receipts', 'bsync-auction' ),
        BSYNC_AUCTION_MANAGE_CAP,
        'bsync-auction-buyer-receipts',
        'bsync_auction_render_buyer_receipts_page'
    );

    // Ensure bsync_member_manager users get a visible entry even if they don't see the Auctions menu.
    if ( current_user_can( 'bsync_manage_members' ) && ! current_user_can( BSYNC_AUCTION_MANAGE_CAP ) ) {
        add_menu_page(
            __( 'Auction Item Grid', 'bsync-auction' ),
            __( 'Auction Item Grid', 'bsync-auction' ),
            'bsync_manage_members',
            'bsync-auction-manager-grid',
            'bsync_auction_render_manager_item_grid_page',
            'dashicons-list-view',
            30
        );
    }

    add_submenu_page(
        $parent_slug,
        __( 'Auctioneers', 'bsync-auction' ),
        __( 'Auctioneers', 'bsync-auction' ),
        BSYNC_AUCTION_MANAGE_CAP,
        'bsync-auction-auctioneers',
        'bsync_auction_render_auctioneers_page'
    );

    add_submenu_page(
        $parent_slug,
        __( 'How It Works', 'bsync-auction' ),
        __( 'How It Works', 'bsync-auction' ),
        BSYNC_AUCTION_MANAGE_CAP,
        'bsync-auction-how-it-works',
        'bsync_auction_render_how_it_works_page'
    );
}

function bsync_auction_render_auctioneers_page() {
    if ( ! current_user_can( BSYNC_AUCTION_MANAGE_CAP ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'bsync-auction' ) );
    }

    if ( isset( $_POST['bsync_auctioneer_nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_POST['bsync_auctioneer_nonce'] ) );
        if ( wp_verify_nonce( $nonce, 'bsync_auctioneer_save' ) ) {
            $user_id = absint( $_POST['user_id'] ?? 0 );
            $action  = sanitize_text_field( wp_unslash( $_POST['auctioneer_action'] ?? '' ) );
            $user    = get_user_by( 'id', $user_id );

            if ( $user instanceof WP_User ) {
                if ( 'grant' === $action ) {
                    $user->add_role( 'bsync_auctioneer' );
                }
                if ( 'revoke' === $action ) {
                    $user->remove_role( 'bsync_auctioneer' );
                }
            }
        }
    }

    $users = get_users(
        array(
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 300,
        )
    );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Auctioneers', 'bsync-auction' ) . '</h1>';
    echo '<p>' . esc_html__( 'Assign or remove the auctioneer role for existing users.', 'bsync-auction' ) . '</p>';
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'User', 'bsync-auction' ) . '</th><th>' . esc_html__( 'Email', 'bsync-auction' ) . '</th><th>' . esc_html__( 'Auctioneer', 'bsync-auction' ) . '</th><th>' . esc_html__( 'Action', 'bsync-auction' ) . '</th></tr></thead><tbody>';

    foreach ( $users as $user ) {
        $is_auctioneer = in_array( 'bsync_auctioneer', (array) $user->roles, true );
        echo '<tr>';
        echo '<td>' . esc_html( $user->display_name ) . '</td>';
        echo '<td>' . esc_html( $user->user_email ) . '</td>';
        echo '<td>' . ( $is_auctioneer ? esc_html__( 'Yes', 'bsync-auction' ) : esc_html__( 'No', 'bsync-auction' ) ) . '</td>';
        echo '<td>';
        echo '<form method="post">';
        wp_nonce_field( 'bsync_auctioneer_save', 'bsync_auctioneer_nonce' );
        echo '<input type="hidden" name="user_id" value="' . esc_attr( $user->ID ) . '" />';
        echo '<input type="hidden" name="auctioneer_action" value="' . ( $is_auctioneer ? 'revoke' : 'grant' ) . '" />';
        submit_button( $is_auctioneer ? __( 'Revoke', 'bsync-auction' ) : __( 'Grant', 'bsync-auction' ), 'secondary', 'submit', false );
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

function bsync_auction_render_how_it_works_page() {
    if ( ! current_user_can( BSYNC_AUCTION_MANAGE_CAP ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'bsync-auction' ) );
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'How It Works', 'bsync-auction' ) . '</h1>';
    echo '<ol>';
    echo '<li>' . esc_html__( 'Create an Auction and set start/end times.', 'bsync-auction' ) . '</li>';
    echo '<li>' . esc_html__( 'Create Auction Items and link them to the auction.', 'bsync-auction' ) . '</li>';
    echo '<li>' . esc_html__( 'Items are visible on the public auction page before the auction starts.', 'bsync-auction' ) . '</li>';
    echo '<li>' . esc_html__( 'Use Manager Item Grid to update buyer, status, and prices quickly.', 'bsync-auction' ) . '</li>';
    echo '</ol>';
    echo '</div>';
}
