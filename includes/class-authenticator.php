<?php

defined( 'ABSPATH' ) || exit;

class WPEG_Authenticator {

	private WPEG_Key_Manager       $key_manager;
	private WPEG_JWT_Handler       $jwt_handler;
	private WPEG_Endpoint_Registry $registry;
	private ?array                 $rules_cache = null;

	public function __construct( WPEG_Key_Manager $key_manager, WPEG_JWT_Handler $jwt_handler, WPEG_Endpoint_Registry $registry ) {
		$this->key_manager = $key_manager;
		$this->jwt_handler = $jwt_handler;
		$this->registry    = $registry;
	}

	public function init(): void {
		add_filter( 'rest_authentication_errors', [ $this, 'authenticate' ] );
	}

	public function authenticate( $result ) {
		// If another plugin has already authenticated, respect it.
		if ( true === $result ) {
			return $result;
		}

		$route = $this->get_current_route();

		// Always allow the plugin's own token endpoint.
		if ( str_contains( $route, 'wp-endpoint-guard' ) ) {
			return $result;
		}

		// Check lockdown mode.
		if ( get_option( 'wpeg_lockdown', '0' ) === '1' && ! is_user_logged_in() ) {
			$authenticated = $this->try_api_key() ?? $this->try_jwt() ?? $this->try_basic_auth();

			if ( $authenticated instanceof WP_User ) {
				wp_set_current_user( $authenticated->ID );
				return true;
			}

			return new WP_Error(
				'rest_forbidden',
				__( 'REST API access is disabled.', 'wp-endpoint-guard' ),
				[ 'status' => 403 ]
			);
		}

		// Hide /wp-json/ index if configured.
		if ( get_option( 'wpeg_hide_index', '0' ) === '1' && ( $route === '/' || $route === '' ) && ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'The REST API index is not available.', 'wp-endpoint-guard' ),
				[ 'status' => 404 ]
			);
		}

		$rule = $this->get_rule_for_route( $route );

		if ( $rule === 'open' ) {
			return $result;
		}

		if ( $rule === 'block' ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'This endpoint is disabled.', 'wp-endpoint-guard' ),
				[ 'status' => 403 ]
			);
		}

		// rule === 'auth' — if the user is already logged in (cookie auth), allow.
		if ( is_user_logged_in() ) {
			return $result;
		}

		// Attempt authentication.
		$authenticated = $this->try_api_key() ?? $this->try_jwt() ?? $this->try_basic_auth();

		if ( is_wp_error( $authenticated ) ) {
			return $authenticated;
		}

		if ( $authenticated instanceof WP_User ) {
			wp_set_current_user( $authenticated->ID );
			return true;
		}

		return new WP_Error(
			'rest_not_authenticated',
			__( 'Authentication required for this endpoint.', 'wp-endpoint-guard' ),
			[ 'status' => 401 ]
		);
	}

	private function get_current_route(): string {
		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$path = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! $path ) {
			return '/';
		}

		$pos = strpos( $path, $rest_prefix );

		if ( false === $pos ) {
			return '/';
		}

		return '/' . ltrim( substr( $path, $pos + strlen( $rest_prefix ) ), '/' );
	}

	private function get_rule_for_route( string $route ): string {
		$rules = $this->get_all_rules();

		// Exact match first.
		if ( isset( $rules[ $route ] ) ) {
			return $rules[ $route ];
		}

		// Pattern match (WP routes use regex).
		foreach ( $rules as $pattern => $rule ) {
			$regex = '#^' . $pattern . '$#i';
			if ( @preg_match( $regex, $route ) ) {
				return $rule;
			}
		}

		return get_option( 'wpeg_default_rule', 'open' );
	}

	private function get_all_rules(): array {
		if ( null !== $this->rules_cache ) {
			return $this->rules_cache;
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'restauth_endpoint_rules';
		$results = $wpdb->get_results( "SELECT route, rule FROM {$table}", OBJECT );

		$this->rules_cache = [];
		if ( $results ) {
			foreach ( $results as $row ) {
				$this->rules_cache[ $row->route ] = $row->rule;
			}
		}

		return $this->rules_cache;
	}

	private function try_api_key(): WP_User|WP_Error|null {
		$header = $this->get_authorization_header();
		$key    = null;

		if ( str_starts_with( $header, 'Bearer wpeg_' ) ) {
			$key = substr( $header, 7 );
		} elseif ( isset( $_SERVER['HTTP_X_API_KEY'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_API_KEY'] ) );
		}

		if ( ! $key || ! str_starts_with( $key, 'wpeg_' ) ) {
			return null;
		}

		$key_hash = hash( 'sha256', $key );
		$record   = $this->key_manager->get_by_hash( $key_hash );

		if ( ! $record || $record->status !== 'active' ) {
			return new WP_Error( 'invalid_api_key', __( 'Invalid API key.', 'wp-endpoint-guard' ), [ 'status' => 401 ] );
		}

		$this->key_manager->touch( $record->id );

		$user_id = $record->user_id ?? get_option( 'wpeg_global_key_user', 1 );
		$user    = get_user_by( 'id', $user_id );

		return $user ?: new WP_Error( 'invalid_user', __( 'The user associated with this API key does not exist.', 'wp-endpoint-guard' ), [ 'status' => 401 ] );
	}

	private function try_jwt(): WP_User|WP_Error|null {
		$header = $this->get_authorization_header();

		if ( ! str_starts_with( $header, 'Bearer ' ) ) {
			return null;
		}

		$token = substr( $header, 7 );

		// Don't try to validate API keys here.
		if ( str_starts_with( $token, 'wpeg_' ) ) {
			return null;
		}

		$payload = $this->jwt_handler->decode( $token );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$user_id = $payload->data->user_id ?? 0;
		$user    = get_user_by( 'id', $user_id );

		return $user ?: new WP_Error( 'invalid_token_user', __( 'The user associated with this token does not exist.', 'wp-endpoint-guard' ), [ 'status' => 401 ] );
	}

	private function try_basic_auth(): WP_User|WP_Error|null {
		if ( get_option( 'wpeg_basic_auth', '0' ) !== '1' ) {
			return null;
		}

		$header = $this->get_authorization_header();

		if ( ! str_starts_with( $header, 'Basic ' ) ) {
			return null;
		}

		$decoded = base64_decode( substr( $header, 6 ), true );

		if ( false === $decoded || ! str_contains( $decoded, ':' ) ) {
			return new WP_Error( 'invalid_basic_auth', __( 'Malformed Basic Auth header.', 'wp-endpoint-guard' ), [ 'status' => 401 ] );
		}

		[ $username, $password ] = explode( ':', $decoded, 2 );

		$user = wp_authenticate( sanitize_text_field( $username ), $password );

		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'invalid_credentials', __( 'Invalid credentials.', 'wp-endpoint-guard' ), [ 'status' => 401 ] );
		}

		return $user;
	}

	private function get_authorization_header(): string {
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( isset( $headers['Authorization'] ) ) {
				return sanitize_text_field( $headers['Authorization'] );
			}
		}

		return '';
	}
}
