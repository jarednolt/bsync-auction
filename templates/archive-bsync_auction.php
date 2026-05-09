<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<div class="bsync-auction-wrap" style="max-width:1000px;margin:40px auto;padding:0 16px;">
    <h1><?php esc_html_e( 'Auctions', 'bsync-auction' ); ?></h1>

    <?php if ( have_posts() ) : ?>
        <div class="bsync-auction-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
            <?php
            while ( have_posts() ) :
                the_post();
                $location  = get_post_meta( get_the_ID(), 'bsync_auction_location', true );
                $address   = get_post_meta( get_the_ID(), 'bsync_auction_address', true );
                $starts_at = get_post_meta( get_the_ID(), 'bsync_auction_starts_at', true );
                $ends_at   = get_post_meta( get_the_ID(), 'bsync_auction_ends_at', true );
                ?>
                <article style="border:1px solid #ddd;border-radius:10px;padding:16px;">
                    <h2 style="margin-top:0;"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php if ( ! empty( $location ) ) : ?>
                        <p><strong><?php esc_html_e( 'Location:', 'bsync-auction' ); ?></strong> <?php echo esc_html( $location ); ?></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $address ) ) : ?>
                        <p><strong><?php esc_html_e( 'Address:', 'bsync-auction' ); ?></strong> <?php echo esc_html( $address ); ?></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $starts_at ) ) : ?>
                        <p><strong><?php esc_html_e( 'Starts:', 'bsync-auction' ); ?></strong> <?php echo esc_html( $starts_at ); ?></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $ends_at ) ) : ?>
                        <p><strong><?php esc_html_e( 'Ends:', 'bsync-auction' ); ?></strong> <?php echo esc_html( $ends_at ); ?></p>
                    <?php endif; ?>
                    <p><a class="button" href="<?php the_permalink(); ?>"><?php esc_html_e( 'View Auction', 'bsync-auction' ); ?></a></p>
                </article>
            <?php endwhile; ?>
        </div>

        <div style="margin-top:24px;">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e( 'No auctions found.', 'bsync-auction' ); ?></p>
    <?php endif; ?>
</div>
<?php
get_footer();
