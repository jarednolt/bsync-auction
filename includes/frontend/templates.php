<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'template_include', 'bsync_auction_template_include', 20 );

function bsync_auction_template_include( $template ) {
    if ( is_post_type_archive( BSYNC_AUCTION_AUCTION_CPT ) ) {
        $candidate = BSYNC_AUCTION_PLUGIN_DIR . 'templates/archive-bsync_auction.php';
        if ( file_exists( $candidate ) ) {
            return $candidate;
        }
    }

    if ( is_singular( BSYNC_AUCTION_AUCTION_CPT ) ) {
        $candidate = BSYNC_AUCTION_PLUGIN_DIR . 'templates/single-bsync_auction.php';
        if ( file_exists( $candidate ) ) {
            return $candidate;
        }
    }

    if ( is_post_type_archive( BSYNC_AUCTION_ITEM_CPT ) ) {
        $candidate = BSYNC_AUCTION_PLUGIN_DIR . 'templates/archive-bsync_auction_item.php';
        if ( file_exists( $candidate ) ) {
            return $candidate;
        }
    }

    if ( is_singular( BSYNC_AUCTION_ITEM_CPT ) ) {
        $candidate = BSYNC_AUCTION_PLUGIN_DIR . 'templates/single-bsync_auction_item.php';
        if ( file_exists( $candidate ) ) {
            return $candidate;
        }
    }

    return $template;
}

function bsync_auction_get_items_for_auction( $auction_id ) {
    return get_posts(
        array(
            'post_type'      => BSYNC_AUCTION_ITEM_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 1000,
            'meta_key'       => 'bsync_auction_order_number',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'   => 'bsync_auction_id',
                    'value' => (int) $auction_id,
                ),
            ),
        )
    );
}

function bsync_auction_get_status_badge( $status ) {
    $status = sanitize_key( (string) $status );

    if ( 'sold' === $status ) {
        return '<span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#0f766e;color:#fff;font-size:12px;">' . esc_html__( 'Sold', 'bsync-auction' ) . '</span>';
    }

    if ( 'pending' === $status ) {
        return '<span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#92400e;color:#fff;font-size:12px;">' . esc_html__( 'Pending', 'bsync-auction' ) . '</span>';
    }

    if ( 'withdrawn' === $status ) {
        return '<span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#64748b;color:#fff;font-size:12px;">' . esc_html__( 'Withdrawn', 'bsync-auction' ) . '</span>';
    }

    return '<span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#1d4ed8;color:#fff;font-size:12px;">' . esc_html__( 'Available', 'bsync-auction' ) . '</span>';
}

function bsync_auction_format_money( $amount ) {
    $value = (float) $amount;
    return '$' . number_format( $value, 2 );
}
