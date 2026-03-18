<?php

defined( 'ABSPATH' ) || exit;

class WPEG_Admin {

	private WPEG_Key_Manager       $key_manager;
	private WPEG_JWT_Handler       $jwt_handler;
	private WPEG_Endpoint_Registry $registry;

	public function __construct( WPEG_Key_Manager $key_manager, WPEG_JWT_Handler $jwt_handler, WPEG_Endpoint_Registry $registry ) {
		$this->key_manager = $key_manager;
		$this->jwt_handler = $jwt_handler;
		$this->registry    = $registry;
	}

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_wpeg_generate_key', [ $this, 'ajax_generate_key' ] );
		add_action( 'wp_ajax_wpeg_revoke_key', [ $this, 'ajax_revoke_key' ] );
		add_action( 'wp_ajax_wpeg_update_rule', [ $this, 'ajax_update_rule' ] );
		add_action( 'wp_ajax_wpeg_bulk_update_rules', [ $this, 'ajax_bulk_update_rules' ] );
		add_action( 'wp_ajax_wpeg_refresh_routes', [ $this, 'ajax_refresh_routes' ] );
		add_action( 'wp_ajax_wpeg_save_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_wpeg_regenerate_jwt_secret', [ $this, 'ajax_regenerate_jwt_secret' ] );
		add_action( 'activated_plugin', [ $this, 'on_plugin_activated' ] );
	}

	public function add_menu(): void {
		add_options_page(
			__( 'WP Endpoint Guard', 'wp-endpoint-guard' ),
			__( 'WP Endpoint Guard', 'wp-endpoint-guard' ),
			'manage_options',
			'wp-endpoint-guard',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_wp-endpoint-guard' !== $hook ) {
			return;
		}

		wp_register_style(
			'wpeg-admin',
			WPEG_PLUGIN_URL . 'admin/assets/admin.css',
			[],
			WPEG_VERSION
		);
		wp_enqueue_style( 'wpeg-admin' );

		wp_register_script(
			'wpeg-admin',
			WPEG_PLUGIN_URL . 'admin/assets/admin.js',
			[ 'jquery' ],
			WPEG_VERSION,
			true
		);
		wp_enqueue_script( 'wpeg-admin' );

		wp_localize_script( 'wpeg-admin', 'wpeg', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wpeg_admin' ),
			'i18n'     => [
				'confirm_revoke'      => __( 'Are you sure you want to revoke this key? This cannot be undone.', 'wp-endpoint-guard' ),
				'confirm_regenerate'  => __( 'Regenerating the JWT secret will invalidate ALL existing tokens. Are you sure?', 'wp-endpoint-guard' ),
				'key_copied'          => __( 'Key copied to clipboard!', 'wp-endpoint-guard' ),
				'copy_failed'         => __( 'Failed to copy. Please copy manually.', 'wp-endpoint-guard' ),
			],
		] );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wp-endpoint-guard' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$allowed_tabs = [ 'settings', 'keys', 'endpoints' ];

		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'settings';
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WP Endpoint Guard', 'wp-endpoint-guard' ) . '</h1>';

		$this->render_tabs( $tab );

		switch ( $tab ) {
			case 'keys':
				include WPEG_PLUGIN_DIR . 'admin/views/page-keys.php';
				break;
			case 'endpoints':
				include WPEG_PLUGIN_DIR . 'admin/views/page-endpoints.php';
				break;
			default:
				include WPEG_PLUGIN_DIR . 'admin/views/page-settings.php';
				break;
		}

		echo '</div>';
	}

	private function render_tabs( string $active_tab ): void {
		$tabs = [
			'settings'  => __( 'Settings', 'wp-endpoint-guard' ),
			'keys'      => __( 'API Keys', 'wp-endpoint-guard' ),
			'endpoints' => __( 'Endpoints', 'wp-endpoint-guard' ),
		];

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url   = add_query_arg( [ 'page' => 'wp-endpoint-guard', 'tab' => $slug ], admin_url( 'options-general.php' ) );
			$class = ( $slug === $active_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	// --- AJAX Handlers ---

	public function ajax_generate_key(): void {
		check_ajax_referer( 'wpeg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'wp-endpoint-guard' ), 403 );
		}

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( empty( $name ) ) {
			wp_send_json_error( __( 'Key name is required.', 'wp-endpoint-guard' ) );
		}

		$result = $this->key_manager->generate( $name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public function ajax_revoke_key(): void {
		check_ajax_referer( 'wpeg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'wp-endpoint-guard' ), 403 );
		}

		$id = isset( $_POST['key_id'] ) ? absint( $_POST['key_id'] ) : 0;

		if ( ! $id || ! $this->key_manager->revoke( $id ) ) {
			wp_send_json_error( __( 'Failed to revoke key.', 'wp-endpoint-guard' ) );
		}

		wp_send_json_success();
	}

	public function ajax_update_rule(): void {
		check_ajax_referer( 'wpeg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'wp-endpoint-guard' ), 403 );
		}

		$id   = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		$rule = isset( $_POST['rule'] ) ? sanitize_text_field( wp_unslash( $_POST['rule'] ) ) : '';

		if ( ! $id || ! $this->registry->update_rule( $id, $rule ) ) {
			wp_send_json_error( __( 'Failed to update rule.', 'wp-endpoint-guard' ) );
		}

		wp_send_json_success();
	}

	public function ajax_bulk_update_rules(): void {
		check_ajax_referer( 'wpeg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'wp-endpoint-guard' ), 403 );
		}

		$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
		$rule = isset( $_POST['rule'] ) ? sanitize_text_field( wp_unslash( $_POST['rule'] ) ) : '';

		if ( empty( $ids ) || empty( $rule ) ) {
			wp_send_json_error( __( 'Missing parameters.', 'wp-endpoint-guard' ) );
		}

		$updated = $this->registry->bulk_update_rules( $ids, $rule );
		wp_send_json_success( [ 'updated' => $updated ] );
	}

	public function ajax_refresh_routes(): void {
		check_ajax_referer( 'wpeg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'wp-endpoint-guard' ), 403 );
		}

		$added = $this->registry->sync_routes();
		wp_send_json_success( [ 'added' => $added ] );
	}

	public function ajax_save_settings(): void {
		check_ajax_referer( 'wpeg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'wp-endpoint-guard' ), 403 );
		}

		$default_rule = isset( $_POST['default_rule'] ) ? sanitize_text_field( wp_unslash( $_POST['default_rule'] ) ) : 'open';
		$lockdown     = isset( $_POST['lockdown'] ) ? '1' : '0';
		$hide_index   = isset( $_POST['hide_index'] ) ? '1' : '0';
		$basic_auth   = isset( $_POST['basic_auth'] ) ? '1' : '0';
		$jwt_expiry   = isset( $_POST['jwt_expiry'] ) ? absint( $_POST['jwt_expiry'] ) : 3600;

		if ( ! in_array( $default_rule, [ 'open', 'auth' ], true ) ) {
			$default_rule = 'open';
		}

		if ( $jwt_expiry < 60 ) {
			$jwt_expiry = 60;
		}

		update_option( 'wpeg_default_rule', $default_rule );
		update_option( 'wpeg_lockdown', $lockdown );
		update_option( 'wpeg_hide_index', $hide_index );
		update_option( 'wpeg_basic_auth', $basic_auth );
		update_option( 'wpeg_jwt_expiry', $jwt_expiry );

		wp_send_json_success();
	}

	public function ajax_regenerate_jwt_secret(): void {
		check_ajax_referer( 'wpeg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'wp-endpoint-guard' ), 403 );
		}

		$secret = $this->jwt_handler->regenerate_secret();

		wp_send_json_success( [ 'secret' => $secret ] );
	}

	public function on_plugin_activated(): void {
		$this->registry->sync_routes();
	}
}
