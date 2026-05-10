<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get assignment table name.
 *
 * @return string
 */
function bsync_auction_get_assignments_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'bsync_auction_assignments';
}

/**
 * Get valid assignment roles.
 *
 * @return array<string,string>
 */
function bsync_auction_get_assignment_roles() {
    return array(
        'auctioneer' => __( 'Auctioneer', 'bsync-auction' ),
        'manager'    => __( 'Manager', 'bsync-auction' ),
        'clerk'      => __( 'Clerk', 'bsync-auction' ),
        'staff'      => __( 'Staff', 'bsync-auction' ),
    );
}

/**
 * Get assignments for an auction.
 *
 * @param int    $auction_id Auction post ID.
 * @param string $role       Optional role key.
 * @return array<int,array<string,mixed>>
 */
function bsync_auction_get_assignments_for_auction( $auction_id, $role = '' ) {
    global $wpdb;

    $auction_id = absint( $auction_id );
    if ( $auction_id <= 0 ) {
        return array();
    }

    $table = bsync_auction_get_assignments_table_name();
    $role  = sanitize_key( (string) $role );

    if ( '' !== $role ) {
        $query = $wpdb->prepare(
            "SELECT id, auction_id, user_id, assignment_role, created_at, updated_at
            FROM {$table}
            WHERE auction_id = %d AND assignment_role = %s
            ORDER BY assignment_role ASC, user_id ASC",
            $auction_id,
            $role
        );
    } else {
        $query = $wpdb->prepare(
            "SELECT id, auction_id, user_id, assignment_role, created_at, updated_at
            FROM {$table}
            WHERE auction_id = %d
            ORDER BY assignment_role ASC, user_id ASC",
            $auction_id
        );
    }

    $rows = $wpdb->get_results( $query, ARRAY_A );

    return is_array( $rows ) ? $rows : array();
}

/**
 * Get all assigned auction IDs for a user.
 *
 * @param int    $user_id User ID.
 * @param string $role    Optional role key.
 * @return array<int,int>
 */
function bsync_auction_get_assigned_auction_ids_for_user( $user_id, $role = '' ) {
    global $wpdb;

    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return array();
    }

    $table = bsync_auction_get_assignments_table_name();
    $role  = sanitize_key( (string) $role );

    if ( '' !== $role ) {
        $query = $wpdb->prepare(
            "SELECT DISTINCT auction_id
            FROM {$table}
            WHERE user_id = %d AND assignment_role = %s
            ORDER BY auction_id ASC",
            $user_id,
            $role
        );
    } else {
        $query = $wpdb->prepare(
            "SELECT DISTINCT auction_id
            FROM {$table}
            WHERE user_id = %d
            ORDER BY auction_id ASC",
            $user_id
        );
    }

    $ids = $wpdb->get_col( $query );
    if ( ! is_array( $ids ) ) {
        return array();
    }

    return array_values( array_filter( array_map( 'absint', $ids ) ) );
}

/**
 * Assign a user to an auction role.
 *
 * @param int    $auction_id       Auction post ID.
 * @param int    $user_id          User ID.
 * @param string $assignment_role  Assignment role key.
 * @return bool
 */
function bsync_auction_assign_user_to_auction( $auction_id, $user_id, $assignment_role = 'staff' ) {
    global $wpdb;

    $auction_id = absint( $auction_id );
    $user_id    = absint( $user_id );

    if ( $auction_id <= 0 || $user_id <= 0 ) {
        return false;
    }

    if ( BSYNC_AUCTION_AUCTION_CPT !== get_post_type( $auction_id ) ) {
        return false;
    }

    if ( ! ( get_user_by( 'id', $user_id ) instanceof WP_User ) ) {
        return false;
    }

    $roles = bsync_auction_get_assignment_roles();
    $role  = sanitize_key( (string) $assignment_role );
    if ( ! isset( $roles[ $role ] ) ) {
        $role = 'staff';
    }

    $table = bsync_auction_get_assignments_table_name();
    $now   = current_time( 'mysql', true );

    $result = $wpdb->replace(
        $table,
        array(
            'auction_id'      => $auction_id,
            'user_id'         => $user_id,
            'assignment_role' => $role,
            'created_at'      => $now,
            'updated_at'      => $now,
        ),
        array( '%d', '%d', '%s', '%s', '%s' )
    );

    return false !== $result;
}

/**
 * Remove an assignment.
 *
 * @param int    $auction_id Auction post ID.
 * @param int    $user_id    User ID.
 * @param string $role       Optional role key; removes all roles when empty.
 * @return bool
 */
function bsync_auction_remove_assignment( $auction_id, $user_id, $role = '' ) {
    global $wpdb;

    $auction_id = absint( $auction_id );
    $user_id    = absint( $user_id );
    $role       = sanitize_key( (string) $role );

    if ( $auction_id <= 0 || $user_id <= 0 ) {
        return false;
    }

    $table = bsync_auction_get_assignments_table_name();

    if ( '' !== $role ) {
        $result = $wpdb->delete(
            $table,
            array(
                'auction_id'      => $auction_id,
                'user_id'         => $user_id,
                'assignment_role' => $role,
            ),
            array( '%d', '%d', '%s' )
        );
    } else {
        $result = $wpdb->delete(
            $table,
            array(
                'auction_id' => $auction_id,
                'user_id'    => $user_id,
            ),
            array( '%d', '%d' )
        );
    }

    return false !== $result;
}

/**
 * Check whether a user has assignment on an auction.
 *
 * @param int    $auction_id Auction post ID.
 * @param int    $user_id    User ID.
 * @param string $role       Optional role key.
 * @return bool
 */
function bsync_auction_user_is_assigned_to_auction( $auction_id, $user_id, $role = '' ) {
    $auction_id = absint( $auction_id );
    $user_id    = absint( $user_id );

    if ( $auction_id <= 0 || $user_id <= 0 ) {
        return false;
    }

    $auction_ids = bsync_auction_get_assigned_auction_ids_for_user( $user_id, $role );

    return in_array( $auction_id, $auction_ids, true );
}
