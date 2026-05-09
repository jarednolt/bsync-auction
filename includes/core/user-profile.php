<?php
/**
 * Bsync Auction — User Profile Integration
 *
 * Adds an "Auction" section to the WordPress user edit/profile page so
 * administrators and member managers can assign a buyer number (member number)
 * to each user.  The value is stored under the primary meta key
 * `bsync_member_number`, which is the first key in the buyer-number resolver
 * chain used by the Manager Item Grid.
 *
 * No modifications to the bsync-member plugin are needed; we simply hook into
 * the same WordPress actions (show_user_profile / edit_user_profile /
 * user_new_form and their save counterparts).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Auction section on the user profile / user-edit screen.
 *
 * @param WP_User|string $user WP_User object (profile/edit screens) or the
 *                             string 'add-existing-user' (user_new_form).
 */
function bsync_auction_render_user_profile_fields( $user ) {
    // Only show to admins and users with the manage-auctions or manage-members cap.
    if (
        ! current_user_can( 'manage_options' ) &&
        ! current_user_can( BSYNC_AUCTION_MANAGE_CAP ) &&
        ! current_user_can( 'bsync_manage_members' )
    ) {
        return;
    }

    $user_id       = ( $user instanceof WP_User ) ? $user->ID : 0;
    $buyer_number  = $user_id ? (string) get_user_meta( $user_id, 'bsync_member_number', true ) : '';
    $next_number   = bsync_auction_get_next_available_member_number( $user_id );
    $nonce_action  = 'bsync_auction_save_user_profile_' . $user_id;
    ?>
    <h2><?php esc_html_e( 'Auction', 'bsync-auction' ); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">
                <label for="bsync_member_number"><?php esc_html_e( 'Buyer / Member Number', 'bsync-auction' ); ?></label>
            </th>
            <td>
                <?php wp_nonce_field( $nonce_action, '_bsync_auction_user_profile_nonce' ); ?>
                <input
                    type="text"
                    id="bsync_member_number"
                    name="bsync_member_number"
                    value="<?php echo esc_attr( $buyer_number ); ?>"
                    class="regular-text"
                    autocomplete="off"
                />
                <p class="description">
                    <?php esc_html_e( 'This number is used by the Auction Item Grid to link items to their buyer. It is matched against the buyer number entered on each auction item row.', 'bsync-auction' ); ?>
                </p>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %d is the next available numeric buyer/member number. */
                        esc_html__( 'Next available number: %d', 'bsync-auction' ),
                        (int) $next_number
                    );
                    ?>
                </p>
                <hr style="margin:14px 0;" />
                <p><strong><?php esc_html_e( 'Scan License (Paste Scanner Output)', 'bsync-auction' ); ?></strong></p>
                <textarea id="bsync_auction_license_raw" class="large-text" rows="4" placeholder="@ANSI ... DAQ123456 DACJOHN DCSDOE"></textarea>
                <p>
                    <button type="button" class="button" id="bsync_auction_parse_license_button"><?php esc_html_e( 'Parse License Data', 'bsync-auction' ); ?></button>
                </p>
                <p class="description" id="bsync_auction_license_result"></p>
                <p class="description"><?php esc_html_e( 'If buyer/member number is empty, the parsed license number (DAQ) will be used.', 'bsync-auction' ); ?></p>
                <p class="description" style="margin-top:8px;"><strong><?php esc_html_e( 'Quick test sample (paste into scanner box):', 'bsync-auction' ); ?></strong></p>
                <pre style="background:#f6f7f7;border:1px solid #dcdcde;padding:8px;max-width:640px;white-space:pre-wrap;">@ANSI 636000080002DL00410288ZA03290015DLDAQA1234567
DCSDOE
DACJANE
DAG123 MAIN ST
DAISEATTLE
DAJWA
DAK98101
DBB19850412</pre>
                <p class="description"><?php esc_html_e( 'Expected result: first/last name, member number (if empty), address, and birthdate are filled.', 'bsync-auction' ); ?></p>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'bsync_auction_render_user_profile_fields' );
add_action( 'edit_user_profile', 'bsync_auction_render_user_profile_fields' );
add_action( 'user_new_form',     'bsync_auction_render_user_profile_fields' );

/**
 * Enqueue license scan helper script on user profile screens.
 *
 * @param string $hook_suffix Admin page hook.
 * @return void
 */
function bsync_auction_enqueue_user_profile_assets( $hook_suffix ) {
    if ( ! in_array( $hook_suffix, array( 'profile.php', 'user-edit.php', 'user-new.php' ), true ) ) {
        return;
    }

    if (
        ! current_user_can( 'manage_options' ) &&
        ! current_user_can( BSYNC_AUCTION_MANAGE_CAP ) &&
        ! current_user_can( 'bsync_manage_members' )
    ) {
        return;
    }

    wp_enqueue_script(
        'bsync-auction-license-scan',
        BSYNC_AUCTION_PLUGIN_URL . 'assets/js/license-scan.js',
        array( 'jquery' ),
        BSYNC_AUCTION_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'bsync_auction_enqueue_user_profile_assets' );

/**
 * Find an existing user ID for a given buyer/member number.
 *
 * @param string $buyer_number     Buyer/member number to match.
 * @param int    $exclude_user_id  Optional user ID to exclude from the search.
 * @return int Matching user ID, or 0 when not found.
 */
function bsync_auction_get_user_id_by_member_number( $buyer_number, $exclude_user_id = 0 ) {
    $buyer_number = trim( (string) $buyer_number );
    if ( '' === $buyer_number ) {
        return 0;
    }

    $args = array(
        'number'     => 1,
        'fields'     => 'ids',
        'meta_key'   => 'bsync_member_number',
        'meta_value' => $buyer_number,
    );

    if ( $exclude_user_id > 0 ) {
        $args['exclude'] = array( (int) $exclude_user_id );
    }

    $users = get_users( $args );
    if ( empty( $users ) ) {
        return 0;
    }

    return (int) $users[0];
}

/**
 * Get the next available numeric buyer/member number.
 *
 * @param int $exclude_user_id Optional user ID to ignore when calculating max.
 * @return int
 */
function bsync_auction_get_next_available_member_number( $exclude_user_id = 0 ) {
    global $wpdb;

    if ( $exclude_user_id > 0 ) {
        $query = $wpdb->prepare(
            "SELECT MAX(CAST(um.meta_value AS UNSIGNED))
            FROM {$wpdb->usermeta} um
            WHERE um.meta_key = %s
                AND um.meta_value REGEXP '^[0-9]+$'
                AND um.user_id != %d",
            'bsync_member_number',
            (int) $exclude_user_id
        );
    } else {
        $query = $wpdb->prepare(
            "SELECT MAX(CAST(um.meta_value AS UNSIGNED))
            FROM {$wpdb->usermeta} um
            WHERE um.meta_key = %s
                AND um.meta_value REGEXP '^[0-9]+$'",
            'bsync_member_number'
        );
    }

    $max_number = (int) $wpdb->get_var( $query );

    return max( 1, $max_number + 1 );
}

/**
 * Validate unique buyer/member number before WordPress saves the user profile.
 *
 * @param WP_Error        $errors WP error object.
 * @param bool            $update Whether this is an existing user.
 * @param WP_User|stdClass $user  User object being saved.
 * @return void
 */
function bsync_auction_validate_unique_member_number( $errors, $update, $user ) {
    if ( ! isset( $_POST['bsync_member_number'] ) ) {
        return;
    }

    $buyer_number    = trim( sanitize_text_field( wp_unslash( $_POST['bsync_member_number'] ) ) );
    $current_user_id = 0;

    if ( is_object( $user ) && isset( $user->ID ) ) {
        $current_user_id = (int) $user->ID;
    }

    if ( '' === $buyer_number ) {
        return;
    }

    $existing_user_id = bsync_auction_get_user_id_by_member_number( $buyer_number, $current_user_id );
    if ( $existing_user_id > 0 ) {
        $errors->add(
            'bsync_member_number_exists',
            __( 'Buyer / Member Number is already assigned to another user. Please use a unique number.', 'bsync-auction' )
        );
    }
}
add_action( 'user_profile_update_errors', 'bsync_auction_validate_unique_member_number', 10, 3 );

/**
 * Save the buyer / member number from the user profile form.
 *
 * @param int $user_id ID of the user being saved.
 */
function bsync_auction_save_user_profile_fields( $user_id ) {
    if (
        ! current_user_can( 'manage_options' ) &&
        ! current_user_can( BSYNC_AUCTION_MANAGE_CAP ) &&
        ! current_user_can( 'bsync_manage_members' )
    ) {
        return;
    }

    $nonce_action_current = 'bsync_auction_save_user_profile_' . $user_id;
    $nonce_action_new     = 'bsync_auction_save_user_profile_0';
    $nonce_value          = isset( $_POST['_bsync_auction_user_profile_nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['_bsync_auction_user_profile_nonce'] ) )
        : '';

    if (
        '' === $nonce_value ||
        (
            ! wp_verify_nonce( $nonce_value, $nonce_action_current ) &&
            ! wp_verify_nonce( $nonce_value, $nonce_action_new )
        )
    ) {
        return;
    }

    if ( ! isset( $_POST['bsync_member_number'] ) ) {
        return;
    }

    $value = trim( sanitize_text_field( wp_unslash( $_POST['bsync_member_number'] ) ) );

    if ( '' === $value ) {
        delete_user_meta( $user_id, 'bsync_member_number' );
    } else {
        // Defensive guard in case another save path bypassed profile validation.
        $existing_user_id = bsync_auction_get_user_id_by_member_number( $value, (int) $user_id );
        if ( $existing_user_id > 0 ) {
            return;
        }

        update_user_meta( $user_id, 'bsync_member_number', $value );
    }
}
add_action( 'personal_options_update',  'bsync_auction_save_user_profile_fields' );
add_action( 'edit_user_profile_update', 'bsync_auction_save_user_profile_fields' );
add_action( 'user_register',            'bsync_auction_save_user_profile_fields' );
