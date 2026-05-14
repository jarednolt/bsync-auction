<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Whether denied-action auditing is enabled.
 *
 * Default is off and can be enabled via filter.
 *
 * @return bool
 */
function bsync_auction_is_denied_scope_audit_enabled() {
    return (bool) apply_filters( 'bsync_auction_enable_denied_scope_audit_log', false );
}

/**
 * Record a denied scope action in error_log for support/debugging.
 *
 * @param string              $action     Logical action name.
 * @param string              $reason     Denial reason key.
 * @param int                 $auction_id Target auction ID.
 * @param array<string,mixed> $extra      Optional extra context.
 * @return bool
 */
function bsync_auction_log_denied_scope_action( $action, $reason, $auction_id = 0, $extra = array() ) {
    if ( ! bsync_auction_is_denied_scope_audit_enabled() ) {
        return false;
    }

    $user = wp_get_current_user();
    $ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

    $payload = array(
        'timestamp'  => current_time( 'mysql', true ),
        'action'     => sanitize_key( (string) $action ),
        'reason'     => sanitize_key( (string) $reason ),
        'auction_id' => absint( $auction_id ),
        'user_id'    => ( $user instanceof WP_User ) ? (int) $user->ID : 0,
        'username'   => ( $user instanceof WP_User ) ? sanitize_user( (string) $user->user_login, true ) : '',
        'request'    => isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '',
        'method'     => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '',
        'ip'         => $ip,
    );

    if ( is_array( $extra ) && ! empty( $extra ) ) {
        $payload['extra'] = $extra;
    }

    $payload = apply_filters( 'bsync_auction_denied_scope_audit_payload', $payload, $action, $reason, $auction_id, $extra );

    if ( ! is_array( $payload ) ) {
        return false;
    }

    error_log( 'Bsync Auction denied scope action: ' . wp_json_encode( $payload ) );

    do_action( 'bsync_auction_denied_scope_audited', $payload );

    return true;
}
