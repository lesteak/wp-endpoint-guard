<?php
/**
 * Fired when the plugin is uninstalled.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}restauth_keys" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}restauth_endpoint_rules" );

// Delete all plugin options.
$options = [
	'wpeg_default_rule',
	'wpeg_lockdown',
	'wpeg_hide_index',
	'wpeg_jwt_secret',
	'wpeg_jwt_expiry',
	'wpeg_basic_auth',
	'wpeg_db_version',
	'wpeg_global_key_user',
];

foreach ( $options as $option ) {
	delete_option( $option );
}
