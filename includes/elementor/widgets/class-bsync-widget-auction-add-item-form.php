<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;

class Bsync_Widget_Auction_Add_Item_Form extends Widget_Base {
    public function get_name() {
        return 'bsync_auction_add_item_form';
    }

    public function get_title() {
        return __( 'Bsync Auction Add Item Form', 'bsync-auction' );
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return array( 'bsync' );
    }

    protected function render() {
        if ( ! function_exists( 'bsync_auction_render_add_item_form_shortcode' ) ) {
            echo '<p>' . esc_html__( 'Auction add-item form is unavailable.', 'bsync-auction' ) . '</p>';
            return;
        }

        echo do_shortcode( '[bsync_auction_add_item_form]' );
    }
}
