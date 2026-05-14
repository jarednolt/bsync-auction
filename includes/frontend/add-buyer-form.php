<?php
/**
 * Frontend add buyer shortcode.
 *
 * @package bsync-auction
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render add buyer form.
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string
 */
function bsync_auction_render_add_buyer_form_shortcode( $atts = array() ) {
    unset( $atts );

    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'You must be logged in to add buyers.', 'bsync-auction' ) . '</p>';
    }

    if ( ! bsync_auction_can_clerk_auction() ) {
        return '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to access this form.', 'bsync-auction' ) . '</p></div>';
    }

    wp_enqueue_style(
        'bsync-auction-add-item-form',
        BSYNC_AUCTION_PLUGIN_URL . 'assets/css/frontend-add-item-form.css',
        array(),
        BSYNC_AUCTION_VERSION
    );

    wp_enqueue_script(
        'bsync-auction-license-scan',
        BSYNC_AUCTION_PLUGIN_URL . 'assets/js/license-scan.js',
        array(),
        BSYNC_AUCTION_VERSION,
        true
    );

    wp_enqueue_script( 'jquery' );

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
            return '<div class="notice notice-warning"><p>' . esc_html__( 'You are not assigned to any auctions.', 'bsync-auction' ) . '</p></div>';
        }

        $query['post__in'] = array_map( 'absint', $accessible );
    }

    $auctions = get_posts( $query );
    if ( empty( $auctions ) ) {
        return '<div class="notice notice-warning"><p>' . esc_html__( 'No available auctions found for your account.', 'bsync-auction' ) . '</p></div>';
    }

    $next_number = (string) bsync_auction_get_next_available_member_number();

    ob_start();
    ?>
    <div class="bsync-auction-add-item-wrap bsync-auction-add-buyer-wrap">
        <h2><?php esc_html_e( 'Add Buyer To Auction Registry', 'bsync-auction' ); ?></h2>
        <p><?php esc_html_e( 'Create a buyer account and register them for an auction. Email is optional if phone is provided.', 'bsync-auction' ); ?></p>

        <form id="bsync-auction-add-buyer-form" class="bsync-auction-add-item-form" method="post">
            <div class="bsync-auction-form-grid">
                <div class="bsync-auction-field">
                    <label for="bsync-auction-id"><?php esc_html_e( 'Auction', 'bsync-auction' ); ?> *</label>
                    <select id="bsync-auction-id" name="auction_id" required>
                        <option value=""><?php esc_html_e( 'Select auction', 'bsync-auction' ); ?></option>
                        <?php foreach ( $auctions as $auction ) : ?>
                            <option value="<?php echo esc_attr( $auction->ID ); ?>"><?php echo esc_html( $auction->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bsync-auction-field">
                    <label for="member_number"><?php esc_html_e( 'Buyer / Member Number', 'bsync-auction' ); ?></label>
                    <input type="text" id="member_number" name="buyer_number" value="<?php echo esc_attr( $next_number ); ?>" />
                    <small><?php printf( esc_html__( 'Next available: %s', 'bsync-auction' ), esc_html( $next_number ) ); ?></small>
                </div>

                <div class="bsync-auction-field bsync-auction-field-full">
                    <label for="bsync_auction_license_raw"><?php esc_html_e( 'License Scan', 'bsync-auction' ); ?></label>
                    <textarea id="bsync_auction_license_raw" name="license_scan" rows="3" placeholder="<?php esc_attr_e( 'Scan barcode data here, then click Parse License', 'bsync-auction' ); ?>"></textarea>
                    <p>
                        <button type="button" class="button" id="bsync_auction_parse_license_button"><?php esc_html_e( 'Parse License', 'bsync-auction' ); ?></button>
                        <span id="bsync_auction_license_result" aria-live="polite"></span>
                    </p>
                </div>

                <div class="bsync-auction-field">
                    <label for="first_name"><?php esc_html_e( 'First Name', 'bsync-auction' ); ?></label>
                    <input type="text" id="first_name" name="first_name" required />
                </div>

                <div class="bsync-auction-field">
                    <label for="last_name"><?php esc_html_e( 'Last Name', 'bsync-auction' ); ?></label>
                    <input type="text" id="last_name" name="last_name" />
                </div>

                <div class="bsync-auction-field">
                    <label for="email"><?php esc_html_e( 'Email', 'bsync-auction' ); ?></label>
                    <input type="email" id="email" name="email" />
                </div>

                <div class="bsync-auction-field">
                    <label for="bsync_member_main_phone"><?php esc_html_e( 'Phone', 'bsync-auction' ); ?></label>
                    <input type="text" id="bsync_member_main_phone" name="phone" />
                </div>

                <div class="bsync-auction-field">
                    <label for="bsync_member_main_birthdate"><?php esc_html_e( 'Date Of Birth', 'bsync-auction' ); ?></label>
                    <input type="text" id="bsync_member_main_birthdate" name="birthdate" placeholder="YYYY-MM-DD" />
                </div>

                <div class="bsync-auction-field bsync-auction-field-full">
                    <label for="bsync_member_address"><?php esc_html_e( 'Address', 'bsync-auction' ); ?></label>
                    <textarea id="bsync_member_address" name="address" rows="2"></textarea>
                </div>
            </div>

            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bsync_auction_frontend_add_buyer' ) ); ?>" />
            <input type="hidden" id="existing_user_id" name="existing_user_id" value="" />

            <div class="bsync-auction-form-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Buyer', 'bsync-auction' ); ?></button>
            </div>

            <div id="bsync-auction-add-buyer-feedback" class="bsync-auction-feedback" style="display:none;"></div>
        </form>
    </div>

    <script>
    jQuery(function($){
        var duplicateCandidate = null;

        $('#bsync-auction-add-buyer-form').on('submit', function(e){
            e.preventDefault();

            var $form = $(this);
            var $feedback = $('#bsync-auction-add-buyer-feedback');
            var firstName = $.trim($('#first_name').val());
            var lastName = $.trim($('#last_name').val());
            var email = $.trim($('#email').val());
            var phone = $.trim($('#bsync_member_main_phone').val());

            if (!firstName && !lastName) {
                $feedback.removeClass('success').addClass('error').text('<?php echo esc_js( __( 'Please provide a first or last name.', 'bsync-auction' ) ); ?>').show();
                return;
            }

            if (!email && !phone) {
                $feedback.removeClass('success').addClass('error').text('<?php echo esc_js( __( 'Provide at least an email or phone number.', 'bsync-auction' ) ); ?>').show();
                return;
            }

            $.ajax({
                url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bsync_auction_frontend_add_buyer',
                    nonce: $form.find('input[name="nonce"]').val(),
                    auction_id: $('#bsync-auction-id').val(),
                    buyer_number: $('#member_number').val(),
                    first_name: firstName,
                    last_name: lastName,
                    email: email,
                    phone: phone,
                    existing_user_id: $.trim($('#existing_user_id').val()),
                    birthdate: $.trim($('#bsync_member_main_birthdate').val()),
                    address: $.trim($('#bsync_member_address').val())
                },
                beforeSend: function(){
                    $feedback.removeClass('error success').text('<?php echo esc_js( __( 'Saving buyer...', 'bsync-auction' ) ); ?>').show();
                },
                success: function(res){
                    if (res && res.success) {
                        var msg = res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Buyer added successfully.', 'bsync-auction' ) ); ?>';
                        var buyerNumber = res.data && res.data.buyerNumber ? String(res.data.buyerNumber) : '';
                        var username = res.data && res.data.username ? String(res.data.username) : '';

                        if (buyerNumber || username) {
                            msg += ' '; 
                            if (buyerNumber) {
                                msg += '<?php echo esc_js( __( 'Buyer #:', 'bsync-auction' ) ); ?> ' + buyerNumber;
                            }
                            if (username) {
                                msg += (buyerNumber ? ' | ' : '') + '<?php echo esc_js( __( 'Username:', 'bsync-auction' ) ); ?> ' + username;
                            }
                        }

                        $feedback.removeClass('error').addClass('success').html($('<span>').text(msg).html()).show();

                        duplicateCandidate = null;
                        $form.trigger('reset');
                        $('#existing_user_id').val('');
                        if (res.data && res.data.nextBuyerNumber) {
                            $('#member_number').val(res.data.nextBuyerNumber);
                        } else {
                            $('#member_number').val('<?php echo esc_js( $next_number ); ?>');
                        }
                    } else {
                        var rawErr = res && res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Unable to add buyer.', 'bsync-auction' ) ); ?>';
                        var dupe = res && res.data && res.data.duplicate ? res.data.duplicate : null;
                        var errHtml = $('<span>').text(rawErr).html();
                        duplicateCandidate = dupe || null;

                        if (dupe) {
                            var parts = [];
                            if (dupe.displayName) parts.push($('<span>').text(dupe.displayName).html());
                            if (dupe.memberNumber) parts.push('<?php echo esc_js( __( 'Buyer #', 'bsync-auction' ) ); ?>: ' + $('<span>').text(dupe.memberNumber).html());
                            if (dupe.editUrl) {
                                parts.push('<a href="' + $('<span>').text(dupe.editUrl).html() + '" target="_blank" rel="noopener noreferrer"><?php echo esc_js( __( 'View / Edit user', 'bsync-auction' ) ); ?></a>');
                            }
                            if (dupe.userId) {
                                parts.push('<button type="button" class="button button-secondary bsync-auction-use-existing-user"><?php echo esc_js( __( 'Use Existing User', 'bsync-auction' ) ); ?></button>');
                            }
                            if (parts.length) {
                                errHtml += ' &mdash; ' + parts.join(' &middot; ');
                            }
                        }

                        $feedback.removeClass('success').addClass('error').html(errHtml).show();
                    }
                },
                error: function(xhr){
                    var errHtml = '<?php echo esc_js( __( 'Server error while adding buyer.', 'bsync-auction' ) ); ?>';
                    try {
                        var parsed = JSON.parse(xhr.responseText);
                        if (parsed && parsed.data && parsed.data.message) {
                            var rawErr = parsed.data.message;
                            var dupe = parsed.data.duplicate || null;
                            errHtml = $('<span>').text(rawErr).html();
                            duplicateCandidate = dupe || null;
                            if (dupe) {
                                var parts = [];
                                if (dupe.displayName) parts.push($('<span>').text(dupe.displayName).html());
                                if (dupe.memberNumber) parts.push('<?php echo esc_js( __( 'Buyer #', 'bsync-auction' ) ); ?>: ' + $('<span>').text(dupe.memberNumber).html());
                                if (dupe.editUrl) {
                                    parts.push('<a href="' + $('<span>').text(dupe.editUrl).html() + '" target="_blank" rel="noopener noreferrer"><?php echo esc_js( __( 'View / Edit user', 'bsync-auction' ) ); ?></a>');
                                }
                                if (dupe.userId) {
                                    parts.push('<button type="button" class="button button-secondary bsync-auction-use-existing-user"><?php echo esc_js( __( 'Use Existing User', 'bsync-auction' ) ); ?></button>');
                                }
                                if (parts.length) {
                                    errHtml += ' &mdash; ' + parts.join(' &middot; ');
                                }
                            }
                        }
                    } catch(e) {}
                    $feedback.removeClass('success').addClass('error').html(errHtml).show();
                }
            });
        });

        $(document).on('click', '.bsync-auction-use-existing-user', function(e){
            e.preventDefault();

            if (!duplicateCandidate || !duplicateCandidate.userId) {
                return;
            }

            $('#existing_user_id').val(String(duplicateCandidate.userId));

            if (duplicateCandidate.firstName) {
                $('#first_name').val(String(duplicateCandidate.firstName));
            }
            if (duplicateCandidate.lastName) {
                $('#last_name').val(String(duplicateCandidate.lastName));
            }
            if (duplicateCandidate.email) {
                $('#email').val(String(duplicateCandidate.email));
            }
            if (duplicateCandidate.phone) {
                $('#bsync_member_main_phone').val(String(duplicateCandidate.phone));
            }
            if (duplicateCandidate.address) {
                $('#bsync_member_address').val(String(duplicateCandidate.address));
            }
            if (duplicateCandidate.birthdate) {
                $('#bsync_member_main_birthdate').val(String(duplicateCandidate.birthdate));
            }
            if (duplicateCandidate.memberNumber) {
                $('#member_number').val(String(duplicateCandidate.memberNumber));
            }

            var readyMsg = '<?php echo esc_js( __( 'Existing user selected. Submit to register this user to the chosen auction without creating a duplicate.', 'bsync-auction' ) ); ?>';
            $feedback.removeClass('error').addClass('success').html($('<span>').text(readyMsg).html()).show();
        });

        $('#first_name, #last_name, #email, #bsync_member_main_phone, #member_number').on('input', function(){
            if ($.trim($('#existing_user_id').val()) !== '') {
                $('#existing_user_id').val('');
            }
        });
    });
    </script>
    <?php

    return (string) ob_get_clean();
}
add_shortcode( 'auction_add_buyer_form', 'bsync_auction_render_add_buyer_form_shortcode' );
