<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<div class="bsync-auction-wrap" style="max-width:1000px;margin:40px auto;padding:0 16px;">
    <?php while ( have_posts() ) : the_post(); ?>
        <article>
            <h1><?php the_title(); ?></h1>

            <?php
            $location  = get_post_meta( get_the_ID(), 'bsync_auction_location', true );
            $address   = get_post_meta( get_the_ID(), 'bsync_auction_address', true );
            $starts_at = get_post_meta( get_the_ID(), 'bsync_auction_starts_at', true );
            $ends_at   = get_post_meta( get_the_ID(), 'bsync_auction_ends_at', true );
            ?>

            <div style="margin:20px 0;padding:16px;border:1px solid #ddd;border-radius:10px;">
                <?php if ( $location ) : ?>
                    <p><strong><?php esc_html_e( 'Location:', 'bsync-auction' ); ?></strong> <?php echo esc_html( $location ); ?></p>
                <?php endif; ?>
                <?php if ( $address ) : ?>
                    <p><strong><?php esc_html_e( 'Address:', 'bsync-auction' ); ?></strong> <?php echo esc_html( $address ); ?></p>
                <?php endif; ?>
                <?php if ( $starts_at ) : ?>
                    <p><strong><?php esc_html_e( 'Starts:', 'bsync-auction' ); ?></strong> <?php echo esc_html( $starts_at ); ?></p>
                <?php endif; ?>
                <?php if ( $ends_at ) : ?>
                    <p><strong><?php esc_html_e( 'Ends:', 'bsync-auction' ); ?></strong> <?php echo esc_html( $ends_at ); ?></p>
                <?php endif; ?>
            </div>

            <?php
            // Items are visible before auction start so users can browse ahead of time.
            $items = bsync_auction_get_items_for_auction( get_the_ID() );
            ?>

            <h2><?php esc_html_e( 'Items', 'bsync-auction' ); ?></h2>
            <?php if ( ! empty( $items ) ) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Item #', 'bsync-auction' ); ?></th>
                            <th><?php esc_html_e( 'Order', 'bsync-auction' ); ?></th>
                            <th><?php esc_html_e( 'Title', 'bsync-auction' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'bsync-auction' ); ?></th>
                            <th><?php esc_html_e( 'Current Bid', 'bsync-auction' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $items as $item ) : ?>
                            <?php
                            $item_status = get_post_meta( $item->ID, 'bsync_auction_item_status', true );
                            $item_num    = get_post_meta( $item->ID, 'bsync_auction_item_number', true );
                            $order_num   = get_post_meta( $item->ID, 'bsync_auction_order_number', true );
                            $current_bid = get_post_meta( $item->ID, 'bsync_auction_current_bid', true );
                            if ( '' === $item_status ) {
                                $item_status = 'available';
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html( (string) $item_num ); ?></td>
                                <td><?php echo esc_html( (string) $order_num ); ?></td>
                                <td><a href="<?php echo esc_url( get_permalink( $item->ID ) ); ?>"><?php echo esc_html( get_the_title( $item->ID ) ); ?></a></td>
                                <td><?php echo wp_kses_post( bsync_auction_get_status_badge( $item_status ) ); ?></td>
                                <td><?php echo esc_html( bsync_auction_format_money( $current_bid ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No items are linked to this auction yet.', 'bsync-auction' ); ?></p>
            <?php endif; ?>
        </article>
    <?php endwhile; ?>
</div>
<?php
get_footer();
