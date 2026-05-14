<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
    return;
}

add_action( 'elementor/elements/categories_registered', 'bsync_auction_register_elementor_category' );
add_action( 'elementor/widgets/register', 'bsync_auction_register_elementor_widgets' );

/**
 * Ensure a Bsync category exists in Elementor.
 *
 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
 * @return void
 */
function bsync_auction_register_elementor_category( $elements_manager ) {
    $elements_manager->add_category(
        'bsync',
        array(
            'title' => __( 'Bsync', 'bsync-auction' ),
            'icon'  => 'fa fa-plug',
        )
    );
}

/**
 * Register Bsync Auction Elementor widgets.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 * @return void
 */
function bsync_auction_register_elementor_widgets( $widgets_manager ) {
    require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/elementor/widgets/class-bsync-widget-auction-items-list.php';
    require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/elementor/widgets/class-bsync-widget-auction-header.php';
    require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/elementor/widgets/class-bsync-widget-auction-countdown.php';
    require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/elementor/widgets/class-bsync-widget-auction-add-item-form.php';

    if ( class_exists( 'Bsync_Widget_Auction_Items_List' ) ) {
        $widgets_manager->register( new Bsync_Widget_Auction_Items_List() );
    }

    if ( class_exists( 'Bsync_Widget_Auction_Header' ) ) {
        $widgets_manager->register( new Bsync_Widget_Auction_Header() );
    }

    if ( class_exists( 'Bsync_Widget_Auction_Countdown' ) ) {
        $widgets_manager->register( new Bsync_Widget_Auction_Countdown() );
    }

    if ( class_exists( 'Bsync_Widget_Auction_Add_Item_Form' ) ) {
        $widgets_manager->register( new Bsync_Widget_Auction_Add_Item_Form() );
    }
}
