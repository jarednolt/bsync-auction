<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class Bsync_Widget_Auction_Header extends Widget_Base {
    public function get_name() {
        return 'bsync_auction_header';
    }

    public function get_title() {
        return __( 'Bsync Auction Header', 'bsync-auction' );
    }

    public function get_icon() {
        return 'eicon-post-title';
    }

    public function get_categories() {
        return array( 'bsync' );
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __( 'Content', 'bsync-auction' ),
            )
        );

        $this->add_control(
            'auction_id',
            array(
                'label'       => __( 'Auction ID', 'bsync-auction' ),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 1,
                'description' => __( 'Leave empty to auto-detect on auction pages.', 'bsync-auction' ),
            )
        );

        $this->add_control(
            'fields_to_show',
            array(
                'label'       => __( 'Fields to Show', 'bsync-auction' ),
                'type'        => Controls_Manager::SELECT2,
                'multiple'    => true,
                'label_block' => true,
                'default'     => array( 'title', 'location', 'starts_at', 'ends_at' ),
                'options'     => array(
                    'title'      => __( 'Title', 'bsync-auction' ),
                    'location'   => __( 'Location', 'bsync-auction' ),
                    'address'    => __( 'Address', 'bsync-auction' ),
                    'auctioneer' => __( 'Auctioneer', 'bsync-auction' ),
                    'starts_at'  => __( 'Starts At', 'bsync-auction' ),
                    'ends_at'    => __( 'Ends At', 'bsync-auction' ),
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings   = $this->get_settings_for_display();
        $auction_id = absint( $settings['auction_id'] ?? 0 );

        if ( ! $auction_id && is_singular( BSYNC_AUCTION_AUCTION_CPT ) ) {
            $auction_id = get_the_ID();
        }

        if ( ! $auction_id ) {
            echo '<p>' . esc_html__( 'Select a valid Auction ID.', 'bsync-auction' ) . '</p>';
            return;
        }

        $auction = get_post( $auction_id );
        if ( ! $auction || BSYNC_AUCTION_AUCTION_CPT !== $auction->post_type ) {
            echo '<p>' . esc_html__( 'Auction not found.', 'bsync-auction' ) . '</p>';
            return;
        }

        $fields = isset( $settings['fields_to_show'] ) && is_array( $settings['fields_to_show'] )
            ? array_values( array_filter( $settings['fields_to_show'] ) )
            : array();

        if ( empty( $fields ) ) {
            $fields = array( 'title' );
        }

        $location  = (string) get_post_meta( $auction_id, 'bsync_auction_location', true );
        $address   = (string) get_post_meta( $auction_id, 'bsync_auction_address', true );
        $starts_at = (string) get_post_meta( $auction_id, 'bsync_auction_starts_at', true );
        $ends_at   = (string) get_post_meta( $auction_id, 'bsync_auction_ends_at', true );
        $auctioneer_id = absint( get_post_meta( $auction_id, 'bsync_auction_auctioneer_id', true ) );
        $auctioneer = $auctioneer_id ? get_user_by( 'id', $auctioneer_id ) : null;

        echo '<div class="bsync-auction-widget-header">';

        if ( in_array( 'title', $fields, true ) ) {
            echo '<h2>' . esc_html( get_the_title( $auction_id ) ) . '</h2>';
        }

        if ( in_array( 'location', $fields, true ) && '' !== $location ) {
            echo '<p><strong>' . esc_html__( 'Location:', 'bsync-auction' ) . '</strong> ' . esc_html( $location ) . '</p>';
        }

        if ( in_array( 'address', $fields, true ) && '' !== $address ) {
            echo '<p><strong>' . esc_html__( 'Address:', 'bsync-auction' ) . '</strong> ' . esc_html( $address ) . '</p>';
        }

        if ( in_array( 'auctioneer', $fields, true ) && $auctioneer instanceof WP_User ) {
            echo '<p><strong>' . esc_html__( 'Auctioneer:', 'bsync-auction' ) . '</strong> ' . esc_html( $auctioneer->display_name ) . '</p>';
        }

        if ( in_array( 'starts_at', $fields, true ) && '' !== $starts_at ) {
            echo '<p><strong>' . esc_html__( 'Starts:', 'bsync-auction' ) . '</strong> ' . esc_html( $starts_at ) . '</p>';
        }

        if ( in_array( 'ends_at', $fields, true ) && '' !== $ends_at ) {
            echo '<p><strong>' . esc_html__( 'Ends:', 'bsync-auction' ) . '</strong> ' . esc_html( $ends_at ) . '</p>';
        }

        echo '</div>';
    }
}
