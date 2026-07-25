<?php
/**
 * Plugin Name:       Health'eat Core
 * Plugin URI:        https://healtheat.example
 * Description:       Moteur du concept Health'eat : carte détaillée (nutrition, régimes, allergènes) et commande à emporter en click &amp; collect.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Health'eat
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       healtheat
 * Domain Path:       /languages
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

define( 'HEALTHEAT_VERSION', '1.0.0' );
define( 'HEALTHEAT_FILE', __FILE__ );
define( 'HEALTHEAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'HEALTHEAT_URL', plugin_dir_url( __FILE__ ) );

require_once HEALTHEAT_DIR . 'includes/functions.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-post-types.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-media.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-provisional.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-stock.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-dish-meta.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-settings.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-slots.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-orders.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-rest.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-shortcodes.php';
require_once HEALTHEAT_DIR . 'includes/class-healtheat-install.php';

/**
 * Boots every module once WordPress is ready.
 *
 * @return void
 */
function healtheat_bootstrap() {
	Healtheat_Post_Types::init();
	Healtheat_Media::init();
	Healtheat_Provisional::init();
	Healtheat_Stock::init();
	Healtheat_Dish_Meta::init();
	Healtheat_Settings::init();
	Healtheat_Orders::init();
	Healtheat_Rest::init();
	Healtheat_Shortcodes::init();
}
add_action( 'plugins_loaded', 'healtheat_bootstrap' );

/**
 * Loads the translation files.
 *
 * @return void
 */
function healtheat_load_textdomain() {
	load_plugin_textdomain( 'healtheat', false, dirname( plugin_basename( HEALTHEAT_FILE ) ) . '/languages' );
}
add_action( 'init', 'healtheat_load_textdomain' );

register_activation_hook( HEALTHEAT_FILE, array( 'Healtheat_Install', 'activate' ) );
register_deactivation_hook( HEALTHEAT_FILE, array( 'Healtheat_Install', 'deactivate' ) );
