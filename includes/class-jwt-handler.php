<?php

defined( 'ABSPATH' ) || exit;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class WPEG_JWT_Handler {

	public function register_routes(): void {
		add_action( 'rest_api_init', [ $this, 'register_token_endpoint' ] );
	}

	public function register_token_endpoint(): void {
		register_rest_route( 'wp-endpoint-guard/v1', '/token', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'generate_token' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'username' => [
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'password' => [
					'required' => true,
				],
			],
		] );
	}

	public function generate_token( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user = wp_authenticate(
			$request->get_param( 'username' ),
			$request->get_param( 'password' )
		);

		if ( is_wp_error( $user ) ) {
			return new WP_Error(
				'invalid_credentials',
				__( 'Invalid credentials.', 'wp-endpoint-guard' ),
				[ 'status' => 401 ]
			);
		}

		$secret    = $this->get_secret();
		$issued_at = time();
		$expiry    = $issued_at + (int) get_option( 'wpeg_jwt_expiry', 3600 );

		$payload = [
			'iss'  => get_bloginfo( 'url' ),
			'iat'  => $issued_at,
			'nbf'  => $issued_at,
			'exp'  => $expiry,
			'data' => [ 'user_id' => $user->ID ],
		];

		$token = JWT::encode( $payload, $secret, 'HS256' );

		return new WP_REST_Response( [
			'token'   => $token,
			'expires' => $expiry,
			'user_id' => $user->ID,
			'email'   => $user->user_email,
		] );
	}

	public function decode( string $token ): \stdClass|WP_Error {
		$secret = $this->get_secret();

		try {
			return JWT::decode( $token, new Key( $secret, 'HS256' ) );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'invalid_token',
				$e->getMessage(),
				[ 'status' => 401 ]
			);
		}
	}

	public function get_secret(): string {
		$secret = get_option( 'wpeg_jwt_secret' );

		if ( ! $secret ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( 'wpeg_jwt_secret', $secret );
		}

		return $secret;
	}

	public function regenerate_secret(): string {
		$secret = wp_generate_password( 64, true, true );
		update_option( 'wpeg_jwt_secret', $secret );
		return $secret;
	}
}
