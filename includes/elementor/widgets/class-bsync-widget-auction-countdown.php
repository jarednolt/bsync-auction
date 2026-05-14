<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class Bsync_Widget_Auction_Countdown extends Widget_Base {
    public function get_name() {
        return 'bsync_auction_countdown';
    }

    public function get_title() {
        return __( 'Bsync Auction Countdown', 'bsync-auction' );
    }

    public function get_icon() {
        return 'eicon-countdown';
    }

    public function get_categories() {
        return array( 'bsync' );
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __( 'Countdown', 'bsync-auction' ),
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
            'pre_message',
            array(
                'label'   => __( 'Before Start Message', 'bsync-auction' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Auction starts in:', 'bsync-auction' ),
            )
        );

        $this->add_control(
            'post_message',
            array(
                'label'   => __( 'After Start Message', 'bsync-auction' ),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'This auction has started.', 'bsync-auction' ),
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

        $starts_at = (string) get_post_meta( $auction_id, 'bsync_auction_starts_at', true );
        $start_ts  = '' !== $starts_at ? strtotime( $starts_at ) : false;

        if ( ! $start_ts ) {
            echo '<p>' . esc_html__( 'No start date configured for this auction.', 'bsync-auction' ) . '</p>';
            return;
        }

        $pre_message  = (string) ( $settings['pre_message'] ?? __( 'Auction starts in:', 'bsync-auction' ) );
        $post_message = (string) ( $settings['post_message'] ?? __( 'This auction has started.', 'bsync-auction' ) );

        if ( time() >= $start_ts ) {
            echo '<p>' . esc_html( $post_message ) . '</p>';
            return;
        }

        $node_id = 'bsync-auction-countdown-' . esc_attr( $this->get_id() );

        echo '<div class="bsync-auction-countdown-widget">';
        echo '<p>' . esc_html( $pre_message ) . ' <strong id="' . esc_attr( $node_id ) . '"></strong></p>';
        echo '</div>';

        echo '<script>(function(){';
        echo 'var el=document.getElementById(' . wp_json_encode( $node_id ) . ');';
        echo 'if(!el){return;}';
        echo 'var target=' . wp_json_encode( gmdate( 'c', $start_ts ) ) . ';';
        echo 'var post=' . wp_json_encode( $post_message ) . ';';
        echo 'var tick=function(){';
        echo 'var diff=new Date(target).getTime()-Date.now();';
        echo 'if(diff<=0){el.textContent=post;clearInterval(timer);return;}';
        echo 'var d=Math.floor(diff/86400000);';
        echo 'var h=Math.floor((diff%86400000)/3600000);';
        echo 'var m=Math.floor((diff%3600000)/60000);';
        echo 'var s=Math.floor((diff%60000)/1000);';
        echo 'el.textContent=d+"d "+h+"h "+m+"m "+s+"s";';
        echo '};';
        echo 'tick();';
        echo 'var timer=setInterval(tick,1000);';
        echo '})();</script>';
    }
}
