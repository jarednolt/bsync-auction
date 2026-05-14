<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'bsync_auction_register_post_types' );
add_filter( 'use_block_editor_for_post_type', 'bsync_auction_disable_block_editor', 10, 2 );
add_filter( 'manage_edit-' . BSYNC_AUCTION_ITEM_CPT . '_columns', 'bsync_auction_item_admin_columns' );
add_action( 'manage_' . BSYNC_AUCTION_ITEM_CPT . '_posts_custom_column', 'bsync_auction_render_item_admin_column', 10, 2 );
add_filter( 'manage_edit-' . BSYNC_AUCTION_ITEM_CPT . '_sortable_columns', 'bsync_auction_item_admin_sortable_columns' );
add_action( 'pre_get_posts', 'bsync_auction_item_admin_sorting_query' );
add_action( 'pre_get_posts', 'bsync_auction_apply_admin_scope_filters', 20 );
add_action( 'admin_init', 'bsync_auction_block_unscoped_post_edit' );

function bsync_auction_register_post_types() {
    $cpt_caps = array(
        'edit_post'              => BSYNC_AUCTION_MANAGE_CAP,
        'read_post'              => 'read',
        'delete_post'            => BSYNC_AUCTION_MANAGE_CAP,
        'edit_posts'             => BSYNC_AUCTION_MANAGE_CAP,
        'edit_others_posts'      => BSYNC_AUCTION_MANAGE_CAP,
        'publish_posts'          => BSYNC_AUCTION_MANAGE_CAP,
        'read_private_posts'     => BSYNC_AUCTION_MANAGE_CAP,
        'delete_posts'           => BSYNC_AUCTION_MANAGE_CAP,
        'delete_private_posts'   => BSYNC_AUCTION_MANAGE_CAP,
        'delete_published_posts' => BSYNC_AUCTION_MANAGE_CAP,
        'delete_others_posts'    => BSYNC_AUCTION_MANAGE_CAP,
        'edit_private_posts'     => BSYNC_AUCTION_MANAGE_CAP,
        'edit_published_posts'   => BSYNC_AUCTION_MANAGE_CAP,
        'create_posts'           => BSYNC_AUCTION_MANAGE_CAP,
    );

    $auction_labels = array(
        'name'               => __( 'Auctions', 'bsync-auction' ),
        'singular_name'      => __( 'Auction', 'bsync-auction' ),
        'add_new'            => __( 'Add Auction', 'bsync-auction' ),
        'add_new_item'       => __( 'Add New Auction', 'bsync-auction' ),
        'edit_item'          => __( 'Edit Auction', 'bsync-auction' ),
        'new_item'           => __( 'New Auction', 'bsync-auction' ),
        'view_item'          => __( 'View Auction', 'bsync-auction' ),
        'search_items'       => __( 'Search Auctions', 'bsync-auction' ),
        'not_found'          => __( 'No auctions found', 'bsync-auction' ),
        'not_found_in_trash' => __( 'No auctions found in Trash', 'bsync-auction' ),
        'menu_name'          => __( 'Auctions', 'bsync-auction' ),
    );

    register_post_type(
        BSYNC_AUCTION_AUCTION_CPT,
        array(
            'labels'             => $auction_labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'show_in_rest'       => true,
            'has_archive'        => 'auctions',
            'rewrite'            => array( 'slug' => 'auctions', 'with_front' => false ),
            'supports'           => array( 'title', 'thumbnail' ),
            'capabilities'       => $cpt_caps,
        )
    );

    $item_labels = array(
        'name'               => __( 'Auction Items', 'bsync-auction' ),
        'singular_name'      => __( 'Auction Item', 'bsync-auction' ),
        'add_new'            => __( 'Add Auction Item', 'bsync-auction' ),
        'add_new_item'       => __( 'Add New Auction Item', 'bsync-auction' ),
        'edit_item'          => __( 'Edit Auction Item', 'bsync-auction' ),
        'new_item'           => __( 'New Auction Item', 'bsync-auction' ),
        'view_item'          => __( 'View Auction Item', 'bsync-auction' ),
        'search_items'       => __( 'Search Auction Items', 'bsync-auction' ),
        'not_found'          => __( 'No auction items found', 'bsync-auction' ),
        'not_found_in_trash' => __( 'No auction items found in Trash', 'bsync-auction' ),
        'menu_name'          => __( 'Auction Items', 'bsync-auction' ),
    );

    register_post_type(
        BSYNC_AUCTION_ITEM_CPT,
        array(
            'labels'             => $item_labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'show_in_rest'       => true,
            'has_archive'        => 'auction-items',
            'rewrite'            => array( 'slug' => 'auction-items', 'with_front' => false ),
            'supports'           => array( 'title', 'thumbnail' ),
            'capabilities'       => $cpt_caps,
        )
    );
}

function bsync_auction_disable_block_editor( $use_block_editor, $post_type ) {
    if ( BSYNC_AUCTION_AUCTION_CPT === $post_type || BSYNC_AUCTION_ITEM_CPT === $post_type ) {
        return false;
    }

    return $use_block_editor;
}

function bsync_auction_get_item_statuses() {
    return array(
        'draft'     => __( 'Draft', 'bsync-auction' ),
        'available' => __( 'Available', 'bsync-auction' ),
        'sold'      => __( 'Sold', 'bsync-auction' ),
        'pending'   => __( 'Pending', 'bsync-auction' ),
        'withdrawn' => __( 'Withdrawn', 'bsync-auction' ),
    );
}

/**
 * Customize Auction Item admin list columns.
 *
 * @param array<string,string> $columns Existing columns.
 * @return array<string,string>
 */
function bsync_auction_item_admin_columns( $columns ) {
    $columns['bsync_auction_order_number'] = __( 'Order Number', 'bsync-auction' );
    $columns['bsync_auction_item_number']  = __( 'Item Number (Fixed)', 'bsync-auction' );
    $columns['bsync_auction_id']           = __( 'Auction', 'bsync-auction' );

    return $columns;
}

/**
 * Render Auction Item admin list custom column values.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function bsync_auction_render_item_admin_column( $column, $post_id ) {
    if ( BSYNC_AUCTION_ITEM_CPT !== get_post_type( $post_id ) ) {
        return;
    }

    if ( 'bsync_auction_order_number' === $column ) {
        $order_number = (string) get_post_meta( $post_id, 'bsync_auction_order_number', true );
        echo '' !== $order_number ? esc_html( $order_number ) : '&mdash;';
        return;
    }

    if ( 'bsync_auction_item_number' === $column ) {
        $item_number = (string) get_post_meta( $post_id, 'bsync_auction_item_number', true );
        echo '' !== $item_number ? esc_html( $item_number ) : '&mdash;';
        return;
    }

    if ( 'bsync_auction_id' === $column ) {
        $auction_id = (int) get_post_meta( $post_id, 'bsync_auction_id', true );
        if ( $auction_id > 0 ) {
            $title = get_the_title( $auction_id );
            $link  = get_edit_post_link( $auction_id );

            if ( $link ) {
                echo '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>';
                return;
            }

            echo esc_html( $title );
            return;
        }

        echo esc_html__( 'Unassigned', 'bsync-auction' );
    }
}

/**
 * Register sortable custom columns for Auction Items list.
 *
 * @param array<string,string> $sortable Sortable columns.
 * @return array<string,string>
 */
function bsync_auction_item_admin_sortable_columns( $sortable ) {
    $sortable['bsync_auction_order_number'] = 'bsync_auction_order_number';
    $sortable['bsync_auction_id']           = 'bsync_auction_id';

    return $sortable;
}

/**
 * Apply sorting query vars for Auction Items custom sortable columns.
 *
 * @param WP_Query $query Query object.
 * @return void
 */
function bsync_auction_item_admin_sorting_query( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( BSYNC_AUCTION_ITEM_CPT !== $query->get( 'post_type' ) ) {
        return;
    }

    $orderby = $query->get( 'orderby' );

    if ( 'bsync_auction_order_number' === $orderby ) {
        $query->set( 'meta_key', 'bsync_auction_order_number' );
        $query->set( 'orderby', 'meta_value_num' );
        return;
    }

    if ( 'bsync_auction_id' === $orderby ) {
        $query->set( 'meta_key', 'bsync_auction_id' );
        $query->set( 'orderby', 'meta_value_num' );
    }
}

/**
 * Scope auction/item admin lists for non-global users.
 *
 * @param WP_Query $query Query object.
 * @return void
 */
function bsync_auction_apply_admin_scope_filters( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( bsync_auction_can_manage_plugin() ) {
        return;
    }

    $post_type = $query->get( 'post_type' );

    if ( BSYNC_AUCTION_AUCTION_CPT === $post_type ) {
        $accessible = bsync_auction_get_accessible_auction_ids();
        $query->set( 'post__in', ( is_array( $accessible ) && ! empty( $accessible ) ) ? $accessible : array( 0 ) );
        return;
    }

    if ( BSYNC_AUCTION_ITEM_CPT === $post_type ) {
        $scoped = bsync_auction_apply_scope_to_item_query_args( $query->query_vars );
        foreach ( $scoped as $key => $value ) {
            $query->set( $key, $value );
        }
    }
}

/**
 * Block direct edit access to auctions/items outside assigned scope.
 *
 * @return void
 */
function bsync_auction_block_unscoped_post_edit() {
    if ( bsync_auction_can_manage_plugin() ) {
        return;
    }

    if ( ! isset( $GLOBALS['pagenow'] ) || 'post.php' !== $GLOBALS['pagenow'] ) {
        return;
    }

    $post_id = absint( $_GET['post'] ?? $_POST['post_ID'] ?? 0 );
    if ( $post_id <= 0 ) {
        return;
    }

    $post_type = get_post_type( $post_id );

    if ( BSYNC_AUCTION_AUCTION_CPT === $post_type && ! bsync_auction_user_can_access_auction_scope( $post_id ) ) {
        wp_die( esc_html__( 'You are not allowed to access this auction.', 'bsync-auction' ) );
    }

    if ( BSYNC_AUCTION_ITEM_CPT === $post_type && ! bsync_auction_can_manage_item( $post_id ) ) {
        wp_die( esc_html__( 'You are not allowed to access this auction item.', 'bsync-auction' ) );
    }
}
