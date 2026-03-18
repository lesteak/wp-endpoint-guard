<?php

defined( 'ABSPATH' ) || exit;

class WPEG_Key_Manager {

	private const FREE_TIER_LIMIT = 2;

	public function generate( string $name, ?int $user_id = null ): array|WP_Error {
		if ( $this->count_active() >= self::FREE_TIER_LIMIT ) {
			return new WP_Error(
				'key_limit_reached',
				__( 'Free tier allows a maximum of 2 active API keys.', 'wp-endpoint-guard' ),
				[ 'status' => 403 ]
			);
		}

		$raw_key    = 'wpeg_' . wp_generate_password( 32, false );
		$key_hash   = hash( 'sha256', $raw_key );
		$key_prefix = substr( $raw_key, 0, 8 );

		global $wpdb;
		$table = $wpdb->prefix . 'restauth_keys';

		$inserted = $wpdb->insert(
			$table,
			[
				'name'       => sanitize_text_field( $name ),
				'key_hash'   => $key_hash,
				'key_prefix' => $key_prefix,
				'user_id'    => $user_id,
				'created_at' => current_time( 'mysql' ),
				'status'     => 'active',
			],
			[ '%s', '%s', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return new WP_Error(
				'key_creation_failed',
				__( 'Failed to create API key.', 'wp-endpoint-guard' )
			);
		}

		return [
			'id'         => $wpdb->insert_id,
			'raw_key'    => $raw_key,
			'key_prefix' => $key_prefix,
			'name'       => $name,
		];
	}

	public function get_all(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'restauth_keys';

		return $wpdb->get_results( "SELECT id, name, key_prefix, user_id, created_at, last_used_at, expires_at, status FROM {$table} ORDER BY created_at DESC" );
	}

	public function get_by_hash( string $key_hash ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'restauth_keys';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE key_hash = %s LIMIT 1", $key_hash )
		);
	}

	public function revoke( int $id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'restauth_keys';

		return (bool) $wpdb->update(
			$table,
			[ 'status' => 'revoked' ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	public function touch( int $id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'restauth_keys';

		$wpdb->update(
			$table,
			[ 'last_used_at' => current_time( 'mysql' ) ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	public function count_active(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'restauth_keys';

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );
	}
}
