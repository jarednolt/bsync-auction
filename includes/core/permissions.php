<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'woocommerce_prevent_admin_access', 'bsync_auction_woocommerce_prevent_admin_access', 30 );
add_filter( 'woocommerce_disable_admin_bar', 'bsync_auction_woocommerce_disable_admin_bar', 30 );

/**
 * Resolve a target user for permission helpers.
 *
 * @param int $user_id Optional user ID.
 * @return WP_User|null
 */
function bsync_auction_get_permission_user( $user_id = 0 ) {
    if ( $user_id > 0 ) {
        $user = get_user_by( 'id', (int) $user_id );
        return ( $user instanceof WP_User ) ? $user : null;
    }

    $current = wp_get_current_user();
    return ( $current instanceof WP_User && $current->ID > 0 ) ? $current : null;
}

/**
 * Can the user access core auction plugin administration.
 *
 * @param int $user_id Optional user ID.
 * @return bool
 */
function bsync_auction_can_manage_plugin( $user_id = 0 ) {
    $user = bsync_auction_get_permission_user( $user_id );
    if ( ! ( $user instanceof WP_User ) ) {
        return false;
    }

    return user_can( $user, 'manage_options' ) || user_can( $user, BSYNC_AUCTION_MANAGE_CAP );
}

/**
 * Can the user manage a specific auction.
 *
 * Phase 1 baseline: mirrors plugin-level manage permission. Auction-specific
 * assignment scoping is added in a later ticket.
 *
 * @param int $auction_id Optional auction ID.
 * @param int $user_id    Optional user ID.
 * @return bool
 */
function bsync_auction_can_manage_auction( $auction_id = 0, $user_id = 0 ) {
    if ( bsync_auction_can_manage_plugin( $user_id ) ) {
        return true;
    }

    $auction_id = absint( $auction_id );
    if ( $auction_id <= 0 ) {
        return false;
    }

    $user = bsync_auction_get_permission_user( $user_id );
    if ( ! ( $user instanceof WP_User ) ) {
        return false;
    }

    return bsync_auction_user_is_assigned_to_auction( $auction_id, $user->ID, 'auctioneer' );
}

/**
 * Can the user clerk/edit rows for a specific auction context.
 *
 * @param int $auction_id Optional auction ID.
 * @param int $user_id    Optional user ID.
 * @return bool
 */
function bsync_auction_can_clerk_auction( $auction_id = 0, $user_id = 0 ) {
    $auction_id = absint( $auction_id );

    if ( bsync_auction_can_manage_auction( $auction_id, $user_id ) ) {
        return true;
    }

    $user = bsync_auction_get_permission_user( $user_id );
    if ( ! ( $user instanceof WP_User ) ) {
        return false;
    }

    if ( $auction_id > 0 ) {
        return bsync_auction_user_is_assigned_to_auction( $auction_id, $user->ID, 'clerk' )
            || bsync_auction_user_is_assigned_to_auction( $auction_id, $user->ID, 'auctioneer' );
    }

    $accessible = bsync_auction_get_accessible_auction_ids( $user->ID );
    return is_array( $accessible ) && ! empty( $accessible );
}

/**
 * Can the user manage a specific auction item.
 *
 * @param int $item_id  Item post ID.
 * @param int $user_id  Optional user ID.
 * @return bool
 */
function bsync_auction_can_manage_item( $item_id, $user_id = 0 ) {
    $item_id = absint( $item_id );
    if ( $item_id <= 0 || BSYNC_AUCTION_ITEM_CPT !== get_post_type( $item_id ) ) {
        return false;
    }

    if ( bsync_auction_can_manage_plugin( $user_id ) ) {
        return true;
    }

    $auction_id = (int) get_post_meta( $item_id, 'bsync_auction_id', true );
    return bsync_auction_can_clerk_auction( $auction_id, $user_id );
}

/**
 * Can the user place bids on an item.
 *
 * Phase 1 baseline: any logged-in user can be treated as a bidder. Deeper
 * item/auction lifecycle checks are introduced in the bidding phase.
 *
 * @param int $item_id  Item post ID.
 * @param int $user_id  Optional user ID.
 * @return bool
 */
function bsync_auction_can_bid_item( $item_id, $user_id = 0 ) {
    $item_id = absint( $item_id );
    if ( $item_id <= 0 || BSYNC_AUCTION_ITEM_CPT !== get_post_type( $item_id ) ) {
        return false;
    }

    $user = bsync_auction_get_permission_user( $user_id );
    return ( $user instanceof WP_User ) && $user->ID > 0;
}

/**
 * Keep WooCommerce from blocking wp-admin for auction operators.
 *
 * @param bool $prevent_access Existing WooCommerce decision.
 * @return bool
 */
function bsync_auction_woocommerce_prevent_admin_access( $prevent_access ) {
    if ( bsync_auction_can_clerk_auction() ) {
        return false;
    }

    return $prevent_access;
}

/**
 * Keep admin bar enabled for auction operators under WooCommerce rules.
 *
 * @param bool $disable_admin_bar Existing WooCommerce decision.
 * @return bool
 */
function bsync_auction_woocommerce_disable_admin_bar( $disable_admin_bar ) {
    if ( bsync_auction_can_clerk_auction() ) {
        return false;
    }

    return $disable_admin_bar;
}
