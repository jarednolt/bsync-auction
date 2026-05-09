<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'bsync_auction_register_post_types' );
add_filter( 'use_block_editor_for_post_type', 'bsync_auction_disable_block_editor', 10, 2 );

function bsync_auction_register_post_types() {
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
            'capability_type'    => 'post',
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
            'capability_type'    => 'post',
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
