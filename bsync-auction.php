<?php
/**
 * Plugin Name: Bsync Auction
 * Description: Auction and auction item management with public catalog pages and manager inline editing tools.
 * Version: 1.0.0
 * Author: Bsync
 * Text Domain: bsync-auction
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BSYNC_AUCTION_VERSION', '1.0.0' );
define( 'BSYNC_AUCTION_PLUGIN_FILE', __FILE__ );
define( 'BSYNC_AUCTION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BSYNC_AUCTION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BSYNC_AUCTION_AUCTION_CPT', 'bsync_auction' );
define( 'BSYNC_AUCTION_ITEM_CPT', 'bsync_auction_item' );
define( 'BSYNC_AUCTION_MANAGE_CAP', 'bsync_manage_auctions' );
define( 'BSYNC_AUCTION_SCHEMA_VERSION', '2026.05.10.1' );

require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/core/permissions.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/core/assignments.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/core/query-scope.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/core/activation.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/core/cpt.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/core/meta-boxes.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/core/user-profile.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/core/updater.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/admin/menu.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/admin/manager-grid.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/admin/ajax.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/admin/import.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/admin/buyer-receipts.php';
require_once BSYNC_AUCTION_PLUGIN_DIR . 'includes/frontend/templates.php';

register_activation_hook( BSYNC_AUCTION_PLUGIN_FILE, 'bsync_auction_activate_plugin' );
register_deactivation_hook( BSYNC_AUCTION_PLUGIN_FILE, 'bsync_auction_deactivate_plugin' );
