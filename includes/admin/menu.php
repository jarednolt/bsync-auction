<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'bsync_auction_register_admin_menus' );

function bsync_auction_register_admin_menus() {
    $parent_slug = 'edit.php?post_type=' . BSYNC_AUCTION_AUCTION_CPT;
    $plugin_menu_cap = bsync_auction_can_manage_plugin() ? 'read' : BSYNC_AUCTION_MANAGE_CAP;

    add_menu_page(
        __( 'Auctions', 'bsync-auction' ),
        __( 'Auctions', 'bsync-auction' ),
        $plugin_menu_cap,
        $parent_slug,
        '',
        'dashicons-store',
        29
    );

    add_submenu_page(
        $parent_slug,
        __( 'Items', 'bsync-auction' ),
        __( 'Items', 'bsync-auction' ),
        $plugin_menu_cap,
        'edit.php?post_type=' . BSYNC_AUCTION_ITEM_CPT
    );

    add_submenu_page(
        $parent_slug,
        __( 'Manager Item Grid', 'bsync-auction' ),
        __( 'Manager Item Grid', 'bsync-auction' ),
        $plugin_menu_cap,
        'bsync-auction-manager-grid',
        'bsync_auction_render_manager_item_grid_page'
    );

    // Register importer as a hidden page; linked from Items list tabs instead.
    add_submenu_page(
        null,
        __( 'Import Items', 'bsync-auction' ),
        __( 'Import Items', 'bsync-auction' ),
        $plugin_menu_cap,
        'bsync-auction-import-items',
        'bsync_auction_render_import_items_page'
    );

    add_submenu_page(
        $parent_slug,
        __( 'Buyer Receipts', 'bsync-auction' ),
        __( 'Buyer Receipts', 'bsync-auction' ),
        $plugin_menu_cap,
        'bsync-auction-buyer-receipts',
        'bsync_auction_render_buyer_receipts_page'
    );

    // Ensure scoped clerks/managers get a visible entry even without full plugin manage capability.
    if ( bsync_auction_can_clerk_auction() && ! bsync_auction_can_manage_plugin() ) {
        add_menu_page(
            __( 'Auction Item Grid', 'bsync-auction' ),
            __( 'Auction Item Grid', 'bsync-auction' ),
            'read',
            'bsync-auction-manager-grid',
            'bsync_auction_render_manager_item_grid_page',
            'dashicons-list-view',
            30
        );

        add_menu_page(
            __( 'Buyer Receipts', 'bsync-auction' ),
            __( 'Buyer Receipts', 'bsync-auction' ),
            'read',
            'bsync-auction-buyer-receipts',
            'bsync_auction_render_buyer_receipts_page',
            'dashicons-tickets-alt',
            31
        );
    }

    add_submenu_page(
        $parent_slug,
        __( 'How It Works', 'bsync-auction' ),
        __( 'How It Works', 'bsync-auction' ),
        $plugin_menu_cap,
        'bsync-auction-how-it-works',
        'bsync_auction_render_how_it_works_page'
    );

    // Add Auction Item entry point for staff (clerks/auctioneers).
    if ( bsync_auction_can_clerk_auction() ) {
        $add_item_page_id = (int) get_option( 'bsync_auction_add_item_page_id', 0 );
        if ( $add_item_page_id > 0 ) {
            $add_item_url = get_permalink( $add_item_page_id );
            if ( $add_item_url ) {
                add_submenu_page(
                    $parent_slug,
                    __( 'Add Auction Item', 'bsync-auction' ),
                    __( 'Add Auction Item', 'bsync-auction' ),
                    'read',
                    'bsync-auction-add-item',
                    static function() {
                        $page_id = (int) get_option( 'bsync_auction_add_item_page_id', 0 );
                        if ( $page_id > 0 ) {
                            wp_safe_remote_get( get_permalink( $page_id ) );
                            echo '<script>window.location.href = ' . wp_json_encode( get_permalink( $page_id ) ) . ';</script>';
                        }
                    }
                );
            }
        }
    }
}

function bsync_auction_render_how_it_works_page() {
    if ( ! bsync_auction_can_manage_plugin() ) {
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
