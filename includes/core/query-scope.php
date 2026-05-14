<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get auction IDs current user can access.
 *
 * @param int $user_id Optional user ID.
 * @return array<int,int>|null Null means unrestricted access.
 */
function bsync_auction_get_accessible_auction_ids( $user_id = 0 ) {
    if ( bsync_auction_can_manage_plugin( $user_id ) ) {
        return null;
    }

    $user = bsync_auction_get_permission_user( $user_id );
    if ( ! ( $user instanceof WP_User ) ) {
        return array();
    }

    $assigned = bsync_auction_get_assigned_auction_ids_for_user( $user->ID );

    return array_values( array_unique( array_filter( array_map( 'absint', $assigned ) ) ) );
}

/**
 * Whether the user can access a specific auction by scope.
 *
 * @param int $auction_id Auction post ID.
 * @param int $user_id    Optional user ID.
 * @return bool
 */
function bsync_auction_user_can_access_auction_scope( $auction_id, $user_id = 0 ) {
    $auction_id = absint( $auction_id );
    if ( $auction_id <= 0 ) {
        return false;
    }

    $accessible = bsync_auction_get_accessible_auction_ids( $user_id );
    if ( null === $accessible ) {
        return true;
    }

    return in_array( $auction_id, $accessible, true );
}

/**
 * Apply auction scope to auction item queries.
 *
 * @param array<string,mixed> $query Query args.
 * @param int                 $user_id Optional user ID.
 * @return array<string,mixed>
 */
function bsync_auction_apply_scope_to_item_query_args( $query, $user_id = 0 ) {
    $query = is_array( $query ) ? $query : array();

    $accessible = bsync_auction_get_accessible_auction_ids( $user_id );
    if ( null === $accessible ) {
        return $query;
    }

    if ( empty( $accessible ) ) {
        $query['post__in'] = array( 0 );
        return $query;
    }

    if ( isset( $query['meta_query'] ) && is_array( $query['meta_query'] ) ) {
        $meta_query = $query['meta_query'];
    } else {
        $meta_query = array();
    }

    $meta_query[] = array(
        'key'     => 'bsync_auction_id',
        'value'   => $accessible,
        'compare' => 'IN',
    );

    $query['meta_query'] = $meta_query;

    return $query;
}

/**
 * Filter an auction list by accessible scope.
 *
 * @param array<int,WP_Post> $auctions Auction posts.
 * @param int                $user_id  Optional user ID.
 * @return array<int,WP_Post>
 */
function bsync_auction_filter_auctions_by_scope( $auctions, $user_id = 0 ) {
    if ( ! is_array( $auctions ) ) {
        return array();
    }

    $accessible = bsync_auction_get_accessible_auction_ids( $user_id );
    if ( null === $accessible ) {
        return $auctions;
    }

    $filtered = array();
    foreach ( $auctions as $auction ) {
        if ( $auction instanceof WP_Post && in_array( (int) $auction->ID, $accessible, true ) ) {
            $filtered[] = $auction;
        }
    }

    return $filtered;
}

/**
 * Resolve a strict auction context for scoped users.
 *
 * Global managers may use context 0 (all auctions) where policy allows.
 * Scoped users must pass a non-zero auction ID within their assignments.
 *
 * @param int $requested_auction_id Requested auction ID.
 * @param int $user_id              Optional user ID.
 * @param array<string,mixed> $args Optional behavior args.
 * @return int|WP_Error
 */
function bsync_auction_resolve_strict_auction_context( $requested_auction_id, $user_id = 0, $args = array() ) {
    $requested_auction_id = absint( $requested_auction_id );
    $user_id              = absint( $user_id );
    $args                 = is_array( $args ) ? $args : array();

    $audit_action = isset( $args['audit_action'] ) ? sanitize_key( (string) $args['audit_action'] ) : '';

    if ( bsync_auction_can_manage_plugin( $user_id ) ) {
        return $requested_auction_id;
    }

    if ( $requested_auction_id <= 0 ) {
        if ( $audit_action && function_exists( 'bsync_auction_log_denied_scope_action' ) ) {
            bsync_auction_log_denied_scope_action(
                $audit_action,
                'missing_auction_context',
                0,
                array( 'user_id' => $user_id )
            );
        }

        return new WP_Error( 'missing_auction', __( 'Auction context is required.', 'bsync-auction' ) );
    }

    if ( ! bsync_auction_user_can_access_auction_scope( $requested_auction_id, $user_id ) ) {
        if ( $audit_action && function_exists( 'bsync_auction_log_denied_scope_action' ) ) {
            bsync_auction_log_denied_scope_action(
                $audit_action,
                'forbidden_auction_scope',
                $requested_auction_id,
                array( 'user_id' => $user_id )
            );
        }

        return new WP_Error( 'forbidden_auction', __( 'You are not allowed to access this auction.', 'bsync-auction' ) );
    }

    return $requested_auction_id;
}
