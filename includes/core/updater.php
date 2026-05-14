<?php
/**
 * Bsync Auction — GitHub Updater
 *
 * Checks GitHub tags for new versions and injects update data into WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Store last updater error context for admin diagnostics.
 *
 * @param string $reason Error reason slug.
 * @param int    $status HTTP status code.
 * @param string $details Extra details.
 * @return void
 */
function bsync_auction_set_updater_error( $reason, $status = 0, $details = '' ) {
    set_transient(
        'bsync_auction_github_updater_error',
        array(
            'reason'  => sanitize_key( (string) $reason ),
            'status'  => absint( $status ),
            'details' => sanitize_text_field( (string) $details ),
            'time'    => time(),
        ),
        HOUR_IN_SECONDS
    );
}

/**
 * Clear updater error context.
 *
 * @return void
 */
function bsync_auction_clear_updater_error() {
    delete_transient( 'bsync_auction_github_updater_error' );
}

/**
 * Get repository metadata for update checks.
 *
 * @return array{owner:string,repo:string,branch:string,token:string}
 */
function bsync_auction_get_repo_config() {
    return array(
        'owner'  => 'jarednolt',
        'repo'   => 'bsync-auction',
        'branch' => 'main',
        // Optional: define( 'BSYNC_AUCTION_GITHUB_TOKEN', 'ghp_xxx' ) for private repositories.
        'token'  => defined( 'BSYNC_AUCTION_GITHUB_TOKEN' ) ? (string) BSYNC_AUCTION_GITHUB_TOKEN : '',
    );
}

/**
 * Build standard headers for GitHub API requests.
 *
 * @param string $token Optional personal access token.
 * @return array<string,string>
 */
function bsync_auction_github_headers( $token = '' ) {
    $headers = array(
        'Accept'     => 'application/vnd.github+json',
        'User-Agent' => 'Bsync-Auction-Updater/' . BSYNC_AUCTION_VERSION,
    );

    if ( '' !== $token ) {
        $headers['Authorization'] = 'Bearer ' . $token;
    }

    return $headers;
}

/**
 * Fetch the latest semver-like tag from GitHub.
 *
 * @return array{version:string,tag:string,zipball_url:string}|null
 */
function bsync_auction_get_latest_github_tag() {
    $config = bsync_auction_get_repo_config();
    $url    = sprintf( 'https://api.github.com/repos/%s/%s/tags?per_page=100', $config['owner'], $config['repo'] );

    $response = wp_remote_get(
        $url,
        array(
            'headers' => bsync_auction_github_headers( $config['token'] ),
            'timeout' => 15,
        )
    );

    if ( is_wp_error( $response ) ) {
        bsync_auction_set_updater_error( 'request_failed', 0, $response->get_error_message() );
        return null;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    if ( 200 !== $status ) {
        bsync_auction_set_updater_error( 'bad_status', $status, 'GitHub tags API returned non-200.' );
        return null;
    }

    $tags = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $tags ) || empty( $tags ) ) {
        bsync_auction_set_updater_error( 'empty_tags', 200, 'No release tags found or invalid JSON.' );
        return null;
    }

    $best = null;

    foreach ( $tags as $tag ) {
        if ( ! is_array( $tag ) || empty( $tag['name'] ) || empty( $tag['zipball_url'] ) ) {
            continue;
        }

        $raw_name = (string) $tag['name'];
        $version  = ltrim( $raw_name, "vV" );

        // Only accept tags that look like semantic versions.
        if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
            continue;
        }

        if ( null === $best || version_compare( $version, $best['version'], '>' ) ) {
            $best = array(
                'version'     => $version,
                'tag'         => $raw_name,
                'zipball_url' => (string) $tag['zipball_url'],
            );
        }
    }

    if ( null === $best ) {
        bsync_auction_set_updater_error( 'no_semver_tags', 200, 'No semantic tags found.' );
        return null;
    }

    bsync_auction_clear_updater_error();

    return $best;
}

/**
 * Inject update payload for this plugin from GitHub tags.
 *
 * @param stdClass $transient The update transient.
 * @return stdClass
 */
function bsync_auction_check_for_updates( $transient ) {
    if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
        return $transient;
    }

    $plugin_basename = plugin_basename( BSYNC_AUCTION_PLUGIN_FILE );
    $latest          = bsync_auction_get_latest_github_tag();

    if ( null === $latest ) {
        return $transient;
    }

    if ( version_compare( $latest['version'], BSYNC_AUCTION_VERSION, '<=' ) ) {
        return $transient;
    }

    $config = bsync_auction_get_repo_config();

    $update              = new stdClass();
    $update->slug        = dirname( $plugin_basename );
    $update->plugin      = $plugin_basename;
    $update->new_version = $latest['version'];
    $update->url         = sprintf( 'https://github.com/%s/%s', $config['owner'], $config['repo'] );
    $update->package     = $latest['zipball_url'];

    $transient->response[ $plugin_basename ] = $update;

    return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'bsync_auction_check_for_updates' );

/**
 * Provide plugin information popup data.
 *
 * @param false|object|array $result The result object or array. Default false.
 * @param string             $action The type of information being requested.
 * @param object             $args   Plugin API arguments.
 * @return false|object|array
 */
function bsync_auction_plugins_api( $result, $action, $args ) {
    if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
        return $result;
    }

    $plugin_basename = plugin_basename( BSYNC_AUCTION_PLUGIN_FILE );
    $expected_slug   = dirname( $plugin_basename );

    if ( $args->slug !== $expected_slug ) {
        return $result;
    }

    $config = bsync_auction_get_repo_config();

    $info                 = new stdClass();
    $info->name           = 'Bsync Auction';
    $info->slug           = $expected_slug;
    $info->version        = BSYNC_AUCTION_VERSION;
    $info->author         = '<a href="https://bsync.me">Bsync</a>';
    $info->homepage       = sprintf( 'https://github.com/%s/%s', $config['owner'], $config['repo'] );
    $info->requires       = '6.0';
    $info->requires_php   = '7.4';
    $info->last_updated   = gmdate( 'Y-m-d' );
    $info->sections       = array(
        'description' => 'Auction and auction item management with public catalog pages and manager inline editing tools.',
        'changelog'   => 'See the GitHub repository tags/releases for full changelog details.',
    );

    return $info;
}
add_filter( 'plugins_api', 'bsync_auction_plugins_api', 10, 3 );

/**
 * Enable automatic updates for this plugin.
 *
 * @param bool   $update Whether to update.
 * @param object $item   Update offer item.
 * @return bool
 */
function bsync_auction_enable_auto_updates( $update, $item ) {
    $plugin_basename = plugin_basename( BSYNC_AUCTION_PLUGIN_FILE );

    if ( isset( $item->plugin ) && $item->plugin === $plugin_basename ) {
        return true;
    }

    return $update;
}
add_filter( 'auto_update_plugin', 'bsync_auction_enable_auto_updates', 10, 2 );

/**
 * Show updater source/error notice on Plugins screen.
 *
 * @return void
 */
function bsync_auction_render_updater_notice() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || 'plugins' !== $screen->id ) {
        return;
    }

    if ( isset( $_GET['bsync_auction_dismiss_github_updater_notice'] ) ) {
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( wp_verify_nonce( $nonce, 'bsync_auction_dismiss_github_updater_notice' ) ) {
            update_option( 'bsync_auction_github_updater_notice_dismissed', 1 );
        }
    }

    if ( 1 === (int) get_option( 'bsync_auction_github_updater_notice_dismissed', 0 ) ) {
        return;
    }

    $config      = bsync_auction_get_repo_config();
    $repo_handle = $config['owner'] . '/' . $config['repo'];
    $repo_url    = sprintf( 'https://github.com/%s/%s', $config['owner'], $config['repo'] );
    $error       = get_transient( 'bsync_auction_github_updater_error' );

    $dismiss_url = add_query_arg(
        array(
            'bsync_auction_dismiss_github_updater_notice' => 1,
        ),
        admin_url( 'plugins.php' )
    );
    $dismiss_url = wp_nonce_url( $dismiss_url, 'bsync_auction_dismiss_github_updater_notice' );

    echo '<div class="notice notice-info"><p>';
    echo '<strong>' . esc_html__( 'Bsync Auction Updater:', 'bsync-auction' ) . '</strong> ';
    echo esc_html__( 'Updates are sourced from', 'bsync-auction' ) . ' ';
    echo '<a href="' . esc_url( $repo_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $repo_handle ) . '</a>. ';
    echo esc_html__( 'Auto-updates are enabled for this plugin.', 'bsync-auction' );
    echo '</p>';

    if ( is_array( $error ) && ! empty( $error['reason'] ) ) {
        $status  = isset( $error['status'] ) ? absint( $error['status'] ) : 0;
        $details = isset( $error['details'] ) ? sanitize_text_field( (string) $error['details'] ) : '';

        echo '<p><strong>' . esc_html__( 'Last GitHub check issue:', 'bsync-auction' ) . '</strong> ';

        if ( 401 === $status || 403 === $status ) {
            echo esc_html__( 'Authentication failed. Set BSYNC_AUCTION_GITHUB_TOKEN in wp-config.php for private repositories.', 'bsync-auction' );
        } elseif ( $status > 0 ) {
            echo esc_html( sprintf( __( 'GitHub API status %d.', 'bsync-auction' ), $status ) );
        } else {
            echo esc_html__( 'Request failed while contacting GitHub.', 'bsync-auction' );
        }

        if ( '' !== $details ) {
            echo ' ' . esc_html( $details );
        }

        echo '</p>';
    }

    echo '<p><a class="button button-secondary" href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss Notice', 'bsync-auction' ) . '</a></p>';
    echo '</div>';
}
add_action( 'admin_notices', 'bsync_auction_render_updater_notice' );
