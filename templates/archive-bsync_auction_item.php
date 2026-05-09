<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<div class="bsync-auction-wrap" style="max-width:1000px;margin:40px auto;padding:0 16px;">
    <h1><?php esc_html_e( 'Auction Items', 'bsync-auction' ); ?></h1>

    <?php if ( have_posts() ) : ?>
        <div class="bsync-auction-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
            <?php while ( have_posts() ) : the_post(); ?>
                <?php
                $status      = get_post_meta( get_the_ID(), 'bsync_auction_item_status', true );
                $item_num    = get_post_meta( get_the_ID(), 'bsync_auction_item_number', true );
                $order_num   = get_post_meta( get_the_ID(), 'bsync_auction_order_number', true );
                $current_bid = get_post_meta( get_the_ID(), 'bsync_auction_current_bid', true );
                if ( '' === $status ) {
                    $status = 'available';
                }
                ?>
                <article style="border:1px solid #ddd;border-radius:10px;padding:16px;">
                    <h2 style="margin-top:0;"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <p><?php echo wp_kses_post( bsync_auction_get_status_badge( $status ) ); ?></p>
                    <p><strong><?php esc_html_e( 'Item #:', 'bsync-auction' ); ?></strong> <?php echo esc_html( (string) $item_num ); ?></p>
                    <p><strong><?php esc_html_e( 'Order:', 'bsync-auction' ); ?></strong> <?php echo esc_html( (string) $order_num ); ?></p>
                    <p><strong><?php esc_html_e( 'Current Bid:', 'bsync-auction' ); ?></strong> <?php echo esc_html( bsync_auction_format_money( $current_bid ) ); ?></p>
                </article>
            <?php endwhile; ?>
        </div>

        <div style="margin-top:24px;">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e( 'No auction items found.', 'bsync-auction' ); ?></p>
    <?php endif; ?>
</div>
<?php
get_footer();
