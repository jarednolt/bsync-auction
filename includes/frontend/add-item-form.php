<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', 'bsync_auction_register_add_item_form_assets' );
add_shortcode( 'bsync_auction_add_item_form', 'bsync_auction_render_add_item_form_shortcode' );

/**
 * Register frontend assets for add-item form.
 *
 * @return void
 */
function bsync_auction_register_add_item_form_assets() {
    wp_register_style(
        'bsync-auction-add-item-form',
        BSYNC_AUCTION_PLUGIN_URL . 'assets/css/frontend-add-item-form.css',
        array(),
        BSYNC_AUCTION_VERSION
    );
}

/**
 * Render scoped frontend add-item form.
 *
 * @return string
 */
function bsync_auction_render_add_item_form_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'You must be logged in to add auction items.', 'bsync-auction' ) . '</p>';
    }

    if ( ! bsync_auction_can_clerk_auction() ) {
        return '<p>' . esc_html__( 'You do not have permission to add auction items.', 'bsync-auction' ) . '</p>';
    }

    $accessible = bsync_auction_get_accessible_auction_ids();

    $query = array(
        'post_type'      => BSYNC_AUCTION_AUCTION_CPT,
        'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
        'posts_per_page' => 300,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );

    if ( is_array( $accessible ) ) {
        if ( empty( $accessible ) ) {
            return '<p>' . esc_html__( 'You are not assigned to any auctions.', 'bsync-auction' ) . '</p>';
        }

        $query['post__in'] = array_map( 'absint', $accessible );
    }

    $auctions = get_posts( $query );
    if ( empty( $auctions ) ) {
        return '<p>' . esc_html__( 'No auctions are currently available for item entry.', 'bsync-auction' ) . '</p>';
    }

    $statuses = bsync_auction_get_item_statuses();

    wp_enqueue_style( 'bsync-auction-add-item-form' );

    ob_start();
    ?>
    <div class="bsync-auction-frontend-add-item-wrap">
        <form class="bsync-auction-frontend-add-item-form" enctype="multipart/form-data" method="post" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
            <h3><?php esc_html_e( 'Add Auction Item', 'bsync-auction' ); ?></h3>
            <p>
                <label for="bsync_front_add_title"><strong><?php esc_html_e( 'Item Title', 'bsync-auction' ); ?></strong></label><br />
                <input type="text" id="bsync_front_add_title" name="title" required />
            </p>

            <p>
                <label for="bsync_front_add_auction"><strong><?php esc_html_e( 'Auction', 'bsync-auction' ); ?></strong></label><br />
                <select id="bsync_front_add_auction" name="auction_id" required>
                    <option value=""><?php esc_html_e( 'Select Auction', 'bsync-auction' ); ?></option>
                    <?php foreach ( $auctions as $auction ) : ?>
                        <option value="<?php echo esc_attr( (string) $auction->ID ); ?>"><?php echo esc_html( $auction->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="bsync_front_add_order"><strong><?php esc_html_e( 'Order Number', 'bsync-auction' ); ?></strong></label><br />
                <input type="number" id="bsync_front_add_order" name="order_number" min="1" step="0.01" placeholder="<?php echo esc_attr__( 'Auto next available', 'bsync-auction' ); ?>" />
            </p>

            <p>
                <label for="bsync_front_add_status"><strong><?php esc_html_e( 'Status', 'bsync-auction' ); ?></strong></label><br />
                <select id="bsync_front_add_status" name="status">
                    <?php foreach ( $statuses as $status_key => $status_label ) : ?>
                        <option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="bsync_front_add_opening"><strong><?php esc_html_e( 'Opening Bid', 'bsync-auction' ); ?></strong></label><br />
                <input type="number" id="bsync_front_add_opening" name="opening_bid" min="0" step="0.01" value="0.00" />
            </p>

            <p>
                <label for="bsync_front_add_current"><strong><?php esc_html_e( 'Current Bid', 'bsync-auction' ); ?></strong></label><br />
                <input type="number" id="bsync_front_add_current" name="current_bid" min="0" step="0.01" value="0.00" />
            </p>

            <p>
                <label for="bsync_front_add_sold"><strong><?php esc_html_e( 'Sold Price', 'bsync-auction' ); ?></strong></label><br />
                <input type="number" id="bsync_front_add_sold" name="sold_price" min="0" step="0.01" value="0.00" />
            </p>

            <p>
                <label for="bsync_front_add_buyer"><strong><?php esc_html_e( 'Buyer Number', 'bsync-auction' ); ?></strong></label><br />
                <input type="text" id="bsync_front_add_buyer" name="buyer_number" placeholder="<?php echo esc_attr__( 'Optional', 'bsync-auction' ); ?>" />
            </p>

            <p>
                <label for="bsync_front_add_image"><strong><?php esc_html_e( 'Featured Image', 'bsync-auction' ); ?></strong></label><br />
                <input type="file" id="bsync_front_add_image" name="featured_image" accept="image/*" />
            </p>

            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bsync_auction_frontend_add_item' ) ); ?>" />

            <p>
                <button type="submit"><?php esc_html_e( 'Add Item', 'bsync-auction' ); ?></button>
                <span class="bsync-auction-frontend-add-item-status" aria-live="polite"></span>
            </p>
        </form>
    </div>

    <script>
    (function() {
        if (window.BsyncAuctionFrontendAddItemInit) {
            return;
        }
        window.BsyncAuctionFrontendAddItemInit = true;

        function setStatus(form, message, isError) {
            var statusEl = form.querySelector('.bsync-auction-frontend-add-item-status');
            if (!statusEl) {
                return;
            }

            statusEl.textContent = message || '';
            statusEl.style.color = isError ? '#b91c1c' : '#166534';
        }

        document.addEventListener('submit', function(event) {
            var form = event.target;
            if (!form || !form.classList || !form.classList.contains('bsync-auction-frontend-add-item-form')) {
                return;
            }

            event.preventDefault();

            var submitButton = form.querySelector('button[type="submit"]');
            var ajaxUrl = form.getAttribute('data-ajax-url') || '';
            var payload = new FormData(form);
            payload.append('action', 'bsync_auction_frontend_add_item');

            if (!ajaxUrl) {
                setStatus(form, 'Missing AJAX URL.', true);
                return;
            }

            if (submitButton) {
                submitButton.disabled = true;
            }
            setStatus(form, 'Adding item...', false);

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: payload
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(result) {
                if (result && result.success) {
                    var message = result.data && result.data.message ? result.data.message : 'Item created.';
                    setStatus(form, message, false);
                    form.reset();
                    return;
                }

                var errorMessage = 'Could not add item.';
                if (result && result.data && result.data.message) {
                    errorMessage = result.data.message;
                }
                setStatus(form, errorMessage, true);
            })
            .catch(function() {
                setStatus(form, 'Could not add item.', true);
            })
            .finally(function() {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
        });
    })();
    </script>
    <?php

    return (string) ob_get_clean();
}
