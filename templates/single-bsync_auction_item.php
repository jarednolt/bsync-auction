<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<div class="bsync-auction-wrap" style="max-width:800px;margin:40px auto;padding:0 16px;">
    <?php while ( have_posts() ) : the_post(); ?>
        <?php
        $status      = get_post_meta( get_the_ID(), 'bsync_auction_item_status', true );
        $item_num    = get_post_meta( get_the_ID(), 'bsync_auction_item_number', true );
        $order_num   = get_post_meta( get_the_ID(), 'bsync_auction_order_number', true );
        $opening_bid = get_post_meta( get_the_ID(), 'bsync_auction_opening_bid', true );
        $current_bid = get_post_meta( get_the_ID(), 'bsync_auction_current_bid', true );

        if ( '' === $status ) {
            $status = 'available';
        }
        ?>
        <article>
            <h1><?php the_title(); ?></h1>
            <p><?php echo wp_kses_post( bsync_auction_get_status_badge( $status ) ); ?></p>
            <p><strong><?php esc_html_e( 'Item Number:', 'bsync-auction' ); ?></strong> <?php echo esc_html( (string) $item_num ); ?></p>
            <p><strong><?php esc_html_e( 'Order Number:', 'bsync-auction' ); ?></strong> <?php echo esc_html( (string) $order_num ); ?></p>
            <p><strong><?php esc_html_e( 'Opening Bid:', 'bsync-auction' ); ?></strong> <?php echo esc_html( bsync_auction_format_money( $opening_bid ) ); ?></p>
            <p><strong><?php esc_html_e( 'Current Bid:', 'bsync-auction' ); ?></strong> <?php echo esc_html( bsync_auction_format_money( $current_bid ) ); ?></p>
            <p><a href="<?php echo esc_url( get_post_type_archive_link( BSYNC_AUCTION_ITEM_CPT ) ); ?>"><?php esc_html_e( 'Back to Items', 'bsync-auction' ); ?></a></p>
        </article>
    <?php endwhile; ?>
</div>
<?php
get_footer();
