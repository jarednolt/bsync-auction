<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'bsync_auction_maybe_run_migrations', 20 );

function bsync_auction_activate_plugin() {
    bsync_auction_register_caps_and_roles();
    bsync_auction_run_migrations();

    // Ensure post types exist before rewrite flush on activation.
    bsync_auction_register_post_types();
    flush_rewrite_rules();
}

function bsync_auction_deactivate_plugin() {
    flush_rewrite_rules();
}

function bsync_auction_run_migrations() {
    bsync_auction_create_bid_table();
    bsync_auction_create_assignment_table();
}

function bsync_auction_maybe_run_migrations() {
    $target_version = defined( 'BSYNC_AUCTION_SCHEMA_VERSION' ) ? BSYNC_AUCTION_SCHEMA_VERSION : BSYNC_AUCTION_VERSION;
    $stored_version = (string) get_option( 'bsync_auction_schema_version', '' );

    if ( $stored_version === $target_version ) {
        return;
    }

    bsync_auction_run_migrations();
    update_option( 'bsync_auction_schema_version', $target_version );
}

function bsync_auction_register_caps_and_roles() {
    $admin = get_role( 'administrator' );
    if ( $admin && ! $admin->has_cap( BSYNC_AUCTION_MANAGE_CAP ) ) {
        $admin->add_cap( BSYNC_AUCTION_MANAGE_CAP );
    }

    add_role(
        'bsync_auctioneer',
        __( 'Bsync Auctioneer', 'bsync-auction' ),
        array(
            'read'                    => true,
            BSYNC_AUCTION_MANAGE_CAP => true,
        )
    );

    $auctioneer = get_role( 'bsync_auctioneer' );
    if ( $auctioneer && ! $auctioneer->has_cap( BSYNC_AUCTION_MANAGE_CAP ) ) {
        $auctioneer->add_cap( BSYNC_AUCTION_MANAGE_CAP );
    }

    // Integrate with the existing bsync member manager role when present.
    $member_manager = get_role( 'bsync_member_manager' );
    if ( $member_manager ) {
        $member_manager->add_cap( BSYNC_AUCTION_MANAGE_CAP );
    }
}

function bsync_auction_create_bid_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'bsync_auction_bids';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        item_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        bid_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        bid_time datetime NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        PRIMARY KEY (id),
        KEY item_id (item_id),
        KEY user_id (user_id),
        KEY bid_time (bid_time)
    ) {$charset_collate};";

    dbDelta( $sql );
}

function bsync_auction_create_assignment_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'bsync_auction_assignments';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        auction_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        assignment_role varchar(30) NOT NULL DEFAULT 'staff',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY auction_user_role (auction_id, user_id, assignment_role),
        KEY auction_id (auction_id),
        KEY user_auction (user_id, auction_id)
    ) {$charset_collate};";

    dbDelta( $sql );
}
