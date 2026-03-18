<?php

defined( 'ABSPATH' ) || exit;

class WPEG_Activator {

	public static function activate(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_keys = "CREATE TABLE {$prefix}restauth_keys (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name         VARCHAR(100)        NOT NULL,
			key_hash     VARCHAR(255)        NOT NULL,
			key_prefix   VARCHAR(8)          NOT NULL,
			user_id      BIGINT(20) UNSIGNED DEFAULT NULL,
			created_at   DATETIME            NOT NULL,
			last_used_at DATETIME            DEFAULT NULL,
			expires_at   DATETIME            DEFAULT NULL,
			status       VARCHAR(10)         NOT NULL DEFAULT 'active',
			PRIMARY KEY (id),
			UNIQUE KEY key_prefix (key_prefix)
		) $charset_collate;";

		$sql_rules = "CREATE TABLE {$prefix}restauth_endpoint_rules (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			route      VARCHAR(500)        NOT NULL,
			namespace  VARCHAR(100)        NOT NULL,
			rule       VARCHAR(10)         NOT NULL DEFAULT 'open',
			updated_at DATETIME            NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY route (route(191))
		) $charset_collate;";

		dbDelta( $sql_keys );
		dbDelta( $sql_rules );

		update_option( 'wpeg_db_version', 1 );

		if ( ! get_option( 'wpeg_default_rule' ) ) {
			update_option( 'wpeg_default_rule', 'open' );
		}
	}
}
