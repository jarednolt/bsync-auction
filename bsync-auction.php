<?php
/**
 * Plugin Name: Bsync Auction
 * Description: Auction and auction item management with public catalog pages and manager inline editing tools.
 * Version: 1.0.1
 * Author: bsync.me
 * Author URI: https://github.com/jarednolt/bsync-auction
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bsync-auction
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BSYNC_AUCTION_VERSION', '1.0.1' );
define( 'BSYNC_AUCTION_PLUGIN_FILE', __FILE__ );
define( 'BSYNC_AUCTION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BSYNC_AUCTION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BSYNC_AUCTION_AUCTION_CPT', 'bsync_auction' );
define( 'BSYNC_AUCTION_ITEM_CPT', 'bsync_auction_item' );
define( 'BSYNC_AUCTION_MANAGE_CAP', 'bsync_manage_auctions' );
define( 'BSYNC_AUCTION_SCHEMA_VERSION', '2026.05.10.1' );

/**
 * Check that Bsync Member is active before loading plugin modules.
 * Uses plugins_loaded so the check runs after all plugins have registered.
 */
add_action( 'plugins_loaded', 'bsync_auction_init_plugin' );

/**
 * Track missing module files so we can show one actionable admin notice.
 *
 * @var string[]
 */
$bsync_auction_missing_modules = array();

function bsync_auction_init_plugin() {
    if ( ! defined( 'BSYNC_MEMBER_VERSION' ) ) {
        add_action( 'admin_notices', 'bsync_auction_missing_member_notice' );

        return;
    }

    $module_files = array(
        'includes/core/permissions.php',
        'includes/core/assignments.php',
        'includes/core/query-scope.php',
        'includes/core/audit-log.php',
        'includes/core/activation.php',
        'includes/core/cpt.php',
        'includes/core/meta-boxes.php',
        'includes/core/user-profile.php',
        'includes/core/updater.php',
        'includes/admin/menu.php',
        'includes/admin/manager-grid.php',
        'includes/admin/ajax.php',
        'includes/admin/import.php',
        'includes/admin/buyer-receipts.php',
        'includes/frontend/add-item-form.php',
        'includes/frontend/add-buyer-form.php',
        'includes/frontend/templates.php',
    );

    foreach ( $module_files as $relative_path ) {
        if ( ! bsync_auction_require_module( $relative_path ) ) {
            add_action( 'admin_notices', 'bsync_auction_missing_module_notice' );
            return;
        }
    }

    if ( defined( 'ELEMENTOR_VERSION' ) ) {
        bsync_auction_require_module( 'includes/elementor/widgets-loader.php' );
    }
}

/**
 * Require one auction module file safely.
 *
 * @param string $relative_path Relative path from plugin root.
 * @return bool
 */
function bsync_auction_require_module( $relative_path ) {
    global $bsync_auction_missing_modules;

    $absolute_path = BSYNC_AUCTION_PLUGIN_DIR . ltrim( $relative_path, '/' );
    if ( ! file_exists( $absolute_path ) ) {
        $bsync_auction_missing_modules[] = $relative_path;
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Bsync Auction missing module file: ' . $relative_path );
        }
        return false;
    }

    require_once $absolute_path;
    return true;
}

/**
 * Admin notice when module file(s) are missing.
 *
 * @return void
 */
function bsync_auction_missing_module_notice() {
    global $bsync_auction_missing_modules;

    if ( empty( $bsync_auction_missing_modules ) ) {
        return;
    }

    $list = implode( ', ', array_map( 'esc_html', array_unique( $bsync_auction_missing_modules ) ) );

    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__( 'Bsync Auction', 'bsync-auction' ) . '</strong> ';
    echo esc_html__( 'could not load required plugin files:', 'bsync-auction' ) . ' ';
    echo $list . '. ';
    echo esc_html__( 'Please re-deploy or reinstall the plugin package.', 'bsync-auction' );
    echo '</p></div>';
}

/**
 * Admin notice shown when Bsync Member is not active.
 */
function bsync_auction_missing_member_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__( 'Bsync Auction', 'bsync-auction' ) . '</strong> ';
    echo esc_html__( 'requires the Bsync Member plugin to be installed and active. Bsync Auction is not running.', 'bsync-auction' );
    echo '</p></div>';
}

/**
 * Block activation if Bsync Member is not active.
 */
add_filter( 'plugin_action_links_bsync-auction/bsync-auction.php', 'bsync_auction_action_links' );
function bsync_auction_action_links( $links ) {
    $url     = wp_nonce_url(
        add_query_arg( 'bsync_auction_check_update', '1', admin_url( 'plugins.php' ) ),
        'bsync_auction_check_update'
    );
    $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for Updates', 'bsync-auction' ) . '</a>';
    return $links;
}

add_action( 'admin_init', 'bsync_auction_handle_check_update' );
function bsync_auction_handle_check_update() {
    if ( ! isset( $_GET['bsync_auction_check_update'] ) ) {
        return;
    }
    check_admin_referer( 'bsync_auction_check_update' );
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_die( esc_html__( 'You do not have permission to update plugins.', 'bsync-auction' ) );
    }
    delete_transient( 'update_plugins' );
    wp_safe_redirect( admin_url( 'plugins.php' ) );
    exit;
}

register_activation_hook( BSYNC_AUCTION_PLUGIN_FILE, 'bsync_auction_on_activate' );
register_deactivation_hook( BSYNC_AUCTION_PLUGIN_FILE, 'bsync_auction_on_deactivate' );

function bsync_auction_on_activate() {
    if ( ! defined( 'BSYNC_MEMBER_VERSION' ) ) {
        deactivate_plugins( plugin_basename( BSYNC_AUCTION_PLUGIN_FILE ) );
        wp_die(
            esc_html__( 'Bsync Auction requires the Bsync Member plugin to be installed and active. Please activate Bsync Member first.', 'bsync-auction' ),
            esc_html__( 'Plugin dependency error', 'bsync-auction' ),
            array( 'back_link' => true )
        );
    }

    require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/core/cpt.php';
    require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/core/activation.php';
    bsync_auction_activate_plugin();
}

function bsync_auction_on_deactivate() {
    if ( function_exists( 'bsync_auction_deactivate_plugin' ) ) {
        bsync_auction_deactivate_plugin();
    } else {
        flush_rewrite_rules();
    }
}
