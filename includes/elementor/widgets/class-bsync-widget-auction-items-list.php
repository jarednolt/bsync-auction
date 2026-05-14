<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class Bsync_Widget_Auction_Items_List extends Widget_Base {
    public function get_name() {
        return 'bsync_auction_items_list';
    }

    public function get_title() {
        return __( 'Bsync Auction Items List', 'bsync-auction' );
    }

    public function get_icon() {
        return 'eicon-post-list';
    }

    public function get_categories() {
        return array( 'bsync' );
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __( 'Query', 'bsync-auction' ),
            )
        );

        $this->add_control(
            'auction_id',
            array(
                'label'       => __( 'Auction ID', 'bsync-auction' ),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 1,
                'description' => __( 'Leave empty to auto-detect from current Auction page.', 'bsync-auction' ),
            )
        );

        $this->add_control(
            'status_filter',
            array(
                'label'   => __( 'Status Filter', 'bsync-auction' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'all',
                'options' => array(
                    'all'       => __( 'All', 'bsync-auction' ),
                    'available' => __( 'Available', 'bsync-auction' ),
                    'sold'      => __( 'Sold', 'bsync-auction' ),
                    'pending'   => __( 'Pending', 'bsync-auction' ),
                    'withdrawn' => __( 'Withdrawn', 'bsync-auction' ),
                    'draft'     => __( 'Draft', 'bsync-auction' ),
                ),
            )
        );

        $this->add_control(
            'items_per_page',
            array(
                'label'   => __( 'Items Per Page', 'bsync-auction' ),
                'type'    => Controls_Manager::NUMBER,
                'default' => 50,
                'min'     => 1,
                'max'     => 1000,
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if ( ! function_exists( 'bsync_auction_get_items_for_auction' ) ) {
            echo '<p>' . esc_html__( 'Auction items are unavailable.', 'bsync-auction' ) . '</p>';
            return;
        }

        $settings   = $this->get_settings_for_display();
        $auction_id = absint( $settings['auction_id'] ?? 0 );

        if ( ! $auction_id && is_singular( BSYNC_AUCTION_AUCTION_CPT ) ) {
            $auction_id = get_the_ID();
        }

        if ( ! $auction_id ) {
            echo '<p>' . esc_html__( 'Select a valid Auction ID.', 'bsync-auction' ) . '</p>';
            return;
        }

        $items = bsync_auction_get_items_for_auction( $auction_id );
        if ( empty( $items ) ) {
            echo '<p>' . esc_html__( 'No auction items found.', 'bsync-auction' ) . '</p>';
            return;
        }

        $status_filter = sanitize_key( (string) ( $settings['status_filter'] ?? 'all' ) );
        $items         = array_values(
            array_filter(
                $items,
                static function( $item ) use ( $status_filter ) {
                    if ( 'all' === $status_filter ) {
                        return true;
                    }

                    $status = sanitize_key( (string) get_post_meta( $item->ID, 'bsync_auction_item_status', true ) );
                    return $status_filter === $status;
                }
            )
        );

        $per_page = absint( $settings['items_per_page'] ?? 50 );
        if ( $per_page < 1 ) {
            $per_page = 50;
        }

        $items = array_slice( $items, 0, $per_page );

        if ( empty( $items ) ) {
            echo '<p>' . esc_html__( 'No items matched your status filter.', 'bsync-auction' ) . '</p>';
            return;
        }

        echo '<div class="bsync-auction-widget-items-list">';
        echo '<table class="wp-block-table"><thead><tr>';
        echo '<th>' . esc_html__( 'Order', 'bsync-auction' ) . '</th>';
        echo '<th>' . esc_html__( 'Item', 'bsync-auction' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'bsync-auction' ) . '</th>';
        echo '<th>' . esc_html__( 'Current Bid', 'bsync-auction' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $items as $item ) {
            $order = (string) get_post_meta( $item->ID, 'bsync_auction_order_number', true );
            $status = sanitize_key( (string) get_post_meta( $item->ID, 'bsync_auction_item_status', true ) );
            $bid = (float) get_post_meta( $item->ID, 'bsync_auction_current_bid', true );
            $url = get_permalink( $item->ID );

            echo '<tr>';
            echo '<td>' . esc_html( $order ) . '</td>';
            echo '<td><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $item ) ) . '</a></td>';
            echo '<td>' . esc_html( ucfirst( $status ) ) . '</td>';
            echo '<td>' . esc_html( bsync_auction_format_money( $bid ) ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}
