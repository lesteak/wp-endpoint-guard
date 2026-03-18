<?php
/**
 * Plugin Name:       WP Endpoint Guard
 * Plugin URI:        https://xinc.digital/wordpress-plugins/wp-endpoint-guard/
 * Description:       Per-endpoint REST API authentication for WordPress. API Keys, JWT, zero file editing required.
 * Version:           1.0.0
 * Author:            Xinc Digital
 * Author URI:        https://xinc.digital/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-endpoint-guard
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Tested up to:      6.9
 */

defined( 'ABSPATH' ) || exit;

define( 'WPEG_VERSION',    '1.0.0' );
define( 'WPEG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPEG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPEG_PLUGIN_FILE', __FILE__ );

require_once WPEG_PLUGIN_DIR . 'vendor/autoload.php';
require_once WPEG_PLUGIN_DIR . 'includes/class-activator.php';
require_once WPEG_PLUGIN_DIR . 'includes/class-deactivator.php';

register_activation_hook( __FILE__,   [ 'WPEG_Activator',   'activate' ] );
register_deactivation_hook( __FILE__, [ 'WPEG_Deactivator', 'deactivate' ] );

function wpeg_init(): void {
	require_once WPEG_PLUGIN_DIR . 'includes/class-key-manager.php';
	require_once WPEG_PLUGIN_DIR . 'includes/class-jwt-handler.php';
	require_once WPEG_PLUGIN_DIR . 'includes/class-endpoint-registry.php';
	require_once WPEG_PLUGIN_DIR . 'includes/class-authenticator.php';

	$key_manager = new WPEG_Key_Manager();
	$jwt_handler = new WPEG_JWT_Handler();
	$registry    = new WPEG_Endpoint_Registry();
	$auth        = new WPEG_Authenticator( $key_manager, $jwt_handler, $registry );

	$auth->init();
	$jwt_handler->register_routes();

	if ( is_admin() ) {
		require_once WPEG_PLUGIN_DIR . 'includes/class-admin.php';
		( new WPEG_Admin( $key_manager, $jwt_handler, $registry ) )->init();
	}
}
add_action( 'plugins_loaded', 'wpeg_init' );
