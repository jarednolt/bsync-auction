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
        return null;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    if ( 200 !== $status ) {
        return null;
    }

    $tags = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $tags ) || empty( $tags ) ) {
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
