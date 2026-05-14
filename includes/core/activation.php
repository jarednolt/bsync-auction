<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'bsync_auction_maybe_run_migrations', 20 );
add_action( 'init', 'bsync_auction_maybe_create_default_add_item_page', 30 );
add_action( 'init', 'bsync_auction_maybe_create_default_add_buyer_page', 30 );
add_action( 'admin_notices', 'bsync_auction_render_add_item_page_notice' );
add_action( 'admin_notices', 'bsync_auction_render_add_buyer_page_notice' );

/**
 * Run after bsync-member finishes its own role/cap sync (init@999).
 * Auction registers its own caps into whatever manager roles Member defined.
 */
add_action( 'bsync_member_sync_operator_roles', 'bsync_auction_register_caps_on_member_sync' );

function bsync_auction_activate_plugin() {
    bsync_auction_register_caps_and_roles();
    bsync_auction_run_migrations();
    bsync_auction_maybe_create_default_add_item_page();
    bsync_auction_maybe_create_default_add_buyer_page();

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
            'bsync_manage_members'   => true,
            'list_users'             => true,
            'edit_users'             => true,
        )
    );

    $auctioneer = get_role( 'bsync_auctioneer' );
    if ( $auctioneer ) {
        if ( ! $auctioneer->has_cap( 'read' ) ) {
            $auctioneer->add_cap( 'read' );
        }

        if ( ! $auctioneer->has_cap( BSYNC_AUCTION_MANAGE_CAP ) ) {
            $auctioneer->add_cap( BSYNC_AUCTION_MANAGE_CAP );
        }

        if ( ! $auctioneer->has_cap( 'bsync_manage_members' ) ) {
            $auctioneer->add_cap( 'bsync_manage_members' );
        }

        if ( ! $auctioneer->has_cap( 'list_users' ) ) {
            $auctioneer->add_cap( 'list_users' );
        }

        if ( ! $auctioneer->has_cap( 'edit_users' ) ) {
            $auctioneer->add_cap( 'edit_users' );
        }
    }

    // Integrate with the existing bsync member manager role when present.
    $member_manager = get_role( 'bsync_member_manager' );
    if ( $member_manager ) {
        $member_manager->add_cap( BSYNC_AUCTION_MANAGE_CAP );
    }

    bsync_auction_sync_member_manager_caps();
}

/**
 * Register auction caps/roles immediately after bsync-member finishes its sync.
 *
 * Fired via the 'bsync_member_sync_operator_roles' action (init@999) so
 * Auction hooks in after Member already created/updated all its roles.
 *
 * @return void
 */
function bsync_auction_register_caps_on_member_sync() {
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $admin->add_cap( BSYNC_AUCTION_MANAGE_CAP );
    }

    // Ensure bsync_auctioneer role exists.
    $auctioneer = get_role( 'bsync_auctioneer' );
    if ( ! $auctioneer ) {
        add_role(
            'bsync_auctioneer',
            __( 'Bsync Auctioneer', 'bsync-auction' ),
            array(
                'read'                    => true,
                BSYNC_AUCTION_MANAGE_CAP => true,
                'bsync_manage_members'   => true,
                'list_users'             => true,
                'edit_users'             => true,
            )
        );
        $auctioneer = get_role( 'bsync_auctioneer' );
    }

    if ( $auctioneer ) {
        $auctioneer->add_cap( 'read' );
        $auctioneer->add_cap( BSYNC_AUCTION_MANAGE_CAP );
        $auctioneer->add_cap( 'bsync_manage_members' );
        $auctioneer->add_cap( 'list_users' );
        $auctioneer->add_cap( 'edit_users' );
    }

    // Any role that can manage members should also manage auctions.
    bsync_auction_sync_member_manager_caps();
}

/**
 * Keep core auction role caps in sync on runtime (without reactivation).
 *
 * @return void
 */
function bsync_auction_sync_core_caps() {
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $admin->add_cap( BSYNC_AUCTION_MANAGE_CAP );
        $admin->add_cap( 'read' );
    }

    $auctioneer = get_role( 'bsync_auctioneer' );
    if ( ! $auctioneer ) {
        add_role(
            'bsync_auctioneer',
            __( 'Bsync Auctioneer', 'bsync-auction' ),
            array(
                'read'                    => true,
                BSYNC_AUCTION_MANAGE_CAP => true,
                'bsync_manage_members'   => true,
                'list_users'             => true,
                'edit_users'             => true,
            )
        );
        $auctioneer = get_role( 'bsync_auctioneer' );
    }

    if ( $auctioneer ) {
        $auctioneer->add_cap( 'read' );
        $auctioneer->add_cap( BSYNC_AUCTION_MANAGE_CAP );
        $auctioneer->add_cap( 'bsync_manage_members' );
        $auctioneer->add_cap( 'list_users' );
        $auctioneer->add_cap( 'edit_users' );
    }

    $member_manager = get_role( 'bsync_member_manager' );
    if ( $member_manager ) {
        $member_manager->add_cap( 'read' );
        $member_manager->add_cap( BSYNC_AUCTION_MANAGE_CAP );
    }
}

/**
 * Sync auction manage capability to all member-manager style roles.
 *
 * Any role that can already manage members should also be able to manage
 * auctions and items, matching bsync-member's admin-manager model.
 *
 * @return void
 */
function bsync_auction_sync_member_manager_caps() {
    global $wp_roles;

    if ( ! ( $wp_roles instanceof WP_Roles ) || empty( $wp_roles->roles ) ) {
        return;
    }

    foreach ( array_keys( $wp_roles->roles ) as $role_slug ) {
        $role = get_role( $role_slug );
        if ( ! $role ) {
            continue;
        }

        if ( ! $role->has_cap( 'bsync_manage_members' ) ) {
            continue;
        }

        if ( ! $role->has_cap( 'read' ) ) {
            $role->add_cap( 'read' );
        }

        if ( ! $role->has_cap( BSYNC_AUCTION_MANAGE_CAP ) ) {
            $role->add_cap( BSYNC_AUCTION_MANAGE_CAP );
        }
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

/**
 * Ensure a default frontend add-item page exists.
 *
 * @return void
 */
function bsync_auction_maybe_create_default_add_item_page() {
    $stored_id = absint( get_option( 'bsync_auction_add_item_page_id', 0 ) );
    if ( $stored_id > 0 ) {
        $existing = get_post( $stored_id );
        if ( $existing instanceof WP_Post && 'page' === $existing->post_type && 'trash' !== $existing->post_status ) {
            return;
        }
    }

    $existing_page = get_page_by_path( 'auction-item-entry' );
    if ( $existing_page instanceof WP_Post ) {
        update_option( 'bsync_auction_add_item_page_id', (int) $existing_page->ID );
        if ( ! get_transient( 'bsync_auction_add_item_page_notice' ) ) {
            set_transient( 'bsync_auction_add_item_page_notice', (int) $existing_page->ID, HOUR_IN_SECONDS );
        }
        return;
    }

    $page_id = wp_insert_post(
        array(
            'post_type'    => 'page',
            'post_title'   => __( 'Auction Item Entry', 'bsync-auction' ),
            'post_name'    => 'auction-item-entry',
            'post_status'  => 'publish',
            'post_content' => '[bsync_auction_add_item_form]',
        ),
        true
    );

    if ( is_wp_error( $page_id ) || $page_id <= 0 ) {
        return;
    }

    update_option( 'bsync_auction_add_item_page_id', (int) $page_id );
    set_transient( 'bsync_auction_add_item_page_notice', (int) $page_id, HOUR_IN_SECONDS );
}

/**
 * Ensure a default frontend add-buyer page exists.
 *
 * @return void
 */
function bsync_auction_maybe_create_default_add_buyer_page() {
    $stored_id = absint( get_option( 'bsync_auction_add_buyer_page_id', 0 ) );
    if ( $stored_id > 0 ) {
        $existing = get_post( $stored_id );
        if ( $existing instanceof WP_Post && 'page' === $existing->post_type && 'trash' !== $existing->post_status ) {
            return;
        }
    }

    $existing_page = get_page_by_path( 'auction-buyer-entry' );
    if ( $existing_page instanceof WP_Post ) {
        update_option( 'bsync_auction_add_buyer_page_id', (int) $existing_page->ID );
        if ( ! get_transient( 'bsync_auction_add_buyer_page_notice' ) ) {
            set_transient( 'bsync_auction_add_buyer_page_notice', (int) $existing_page->ID, HOUR_IN_SECONDS );
        }
        return;
    }

    $page_id = wp_insert_post(
        array(
            'post_type'    => 'page',
            'post_title'   => __( 'Auction Buyer Entry', 'bsync-auction' ),
            'post_name'    => 'auction-buyer-entry',
            'post_status'  => 'publish',
            'post_content' => '[auction_add_buyer_form]',
        ),
        true
    );

    if ( is_wp_error( $page_id ) || $page_id <= 0 ) {
        return;
    }

    update_option( 'bsync_auction_add_buyer_page_id', (int) $page_id );
    set_transient( 'bsync_auction_add_buyer_page_notice', (int) $page_id, HOUR_IN_SECONDS );
}

/**
 * Show one-time admin notice with generated add-item page links.
 *
 * @return void
 */
function bsync_auction_render_add_item_page_notice() {
    if ( ! is_admin() || ! bsync_auction_can_clerk_auction() ) {
        return;
    }

    $page_id = absint( get_transient( 'bsync_auction_add_item_page_notice' ) );
    if ( $page_id <= 0 ) {
        return;
    }

    $page = get_post( $page_id );
    if ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
        delete_transient( 'bsync_auction_add_item_page_notice' );
        return;
    }

    $view_url = get_permalink( $page_id );
    $edit_url = get_edit_post_link( $page_id, '' );

    echo '<div class="notice notice-success is-dismissible"><p>';
    echo '<strong>' . esc_html__( 'Bsync Auction', 'bsync-auction' ) . '</strong> ';
    echo esc_html__( 'created the Auction Item Entry page for scoped item submission.', 'bsync-auction' ) . ' ';
    if ( $view_url ) {
        echo '<a href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View page', 'bsync-auction' ) . '</a>';
    }
    if ( $edit_url ) {
        echo ' | <a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit page', 'bsync-auction' ) . '</a>';
    }
    echo '</p></div>';

    delete_transient( 'bsync_auction_add_item_page_notice' );
}

/**
 * Show one-time admin notice with generated add-buyer page links.
 *
 * @return void
 */
function bsync_auction_render_add_buyer_page_notice() {
    if ( ! is_admin() || ! bsync_auction_can_clerk_auction() ) {
        return;
    }

    $page_id = absint( get_transient( 'bsync_auction_add_buyer_page_notice' ) );
    if ( $page_id <= 0 ) {
        return;
    }

    $page = get_post( $page_id );
    if ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
        delete_transient( 'bsync_auction_add_buyer_page_notice' );
        return;
    }

    $view_url = get_permalink( $page_id );
    $edit_url = get_edit_post_link( $page_id, '' );

    echo '<div class="notice notice-success is-dismissible"><p>';
    echo '<strong>' . esc_html__( 'Bsync Auction', 'bsync-auction' ) . '</strong> ';
    echo esc_html__( 'created the Auction Buyer Entry page for registry check-in.', 'bsync-auction' ) . ' ';
    if ( $view_url ) {
        echo '<a href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View page', 'bsync-auction' ) . '</a>';
    }
    if ( $edit_url ) {
        echo ' | <a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit page', 'bsync-auction' ) . '</a>';
    }
    echo '</p></div>';

    delete_transient( 'bsync_auction_add_buyer_page_notice' );
}
