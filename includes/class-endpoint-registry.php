<?php

defined( 'ABSPATH' ) || exit;

class WPEG_Endpoint_Registry {

	public function discover_routes(): array {
		$server = rest_get_server();
		$routes = $server->get_routes();
		$result = [];

		foreach ( $routes as $route => $handlers ) {
			$parts     = explode( '/', ltrim( $route, '/' ) );
			$namespace = isset( $parts[1] ) ? $parts[0] . '/' . $parts[1] : $parts[0];

			// Skip our own routes.
			if ( str_contains( $route, 'wp-endpoint-guard' ) ) {
				continue;
			}

			$methods = [];
			foreach ( $handlers as $handler ) {
				if ( isset( $handler['methods'] ) ) {
					$methods = array_merge( $methods, array_keys( $handler['methods'] ) );
				}
			}

			$result[ $route ] = [
				'route'     => $route,
				'namespace' => $namespace,
				'methods'   => array_unique( $methods ),
			];
		}

		return $result;
	}

	public function sync_routes(): int {
		$routes  = $this->discover_routes();
		$default = get_option( 'wpeg_default_rule', 'open' );

		global $wpdb;
		$table    = $wpdb->prefix . 'restauth_endpoint_rules';
		$existing = $this->get_all_routes_indexed();
		$added    = 0;

		foreach ( $routes as $route_path => $info ) {
			if ( isset( $existing[ $route_path ] ) ) {
				continue;
			}

			$wpdb->insert(
				$table,
				[
					'route'      => $route_path,
					'namespace'  => $info['namespace'],
					'rule'       => $default,
					'updated_at' => current_time( 'mysql' ),
				],
				[ '%s', '%s', '%s', '%s' ]
			);
			$added++;
		}

		return $added;
	}

	public function get_all_rules(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'restauth_endpoint_rules';

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY route ASC" );
	}

	public function update_rule( int $id, string $rule ): bool {
		$allowed = [ 'open', 'auth', 'block' ];
		if ( ! in_array( $rule, $allowed, true ) ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'restauth_endpoint_rules';

		return (bool) $wpdb->update(
			$table,
			[
				'rule'       => $rule,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function bulk_update_rules( array $ids, string $rule ): int {
		$allowed = [ 'open', 'auth', 'block' ];
		if ( ! in_array( $rule, $allowed, true ) ) {
			return 0;
		}

		global $wpdb;
		$table       = $wpdb->prefix . 'restauth_endpoint_rules';
		$ids         = array_map( 'absint', $ids );
		$ids_escaped = implode( ',', $ids );
		$now         = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are absint-cast.
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET rule = %s, updated_at = %s WHERE id IN ({$ids_escaped})",
				$rule,
				$now
			)
		);
	}

	public function get_namespaces(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'restauth_endpoint_rules';

		return $wpdb->get_col( "SELECT DISTINCT namespace FROM {$table} ORDER BY namespace ASC" );
	}

	private function get_all_routes_indexed(): array {
		global $wpdb;
		$table   = $wpdb->prefix . 'restauth_endpoint_rules';
		$rows    = $wpdb->get_results( "SELECT route FROM {$table}", OBJECT );
		$indexed = [];

		foreach ( $rows as $row ) {
			$indexed[ $row->route ] = true;
		}

		return $indexed;
	}
}
