<?php

defined( 'ABSPATH' ) || exit;

$default_rule = get_option( 'wpeg_default_rule', 'open' );
$lockdown     = get_option( 'wpeg_lockdown', '0' );
$hide_index   = get_option( 'wpeg_hide_index', '0' );
$basic_auth   = get_option( 'wpeg_basic_auth', '0' );
$jwt_secret   = $this->jwt_handler->get_secret();
$jwt_expiry   = get_option( 'wpeg_jwt_expiry', 3600 );
?>

<div class="wpeg-settings-wrap">
	<form id="wpeg-settings-form">
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Global Default Rule', 'wp-endpoint-guard' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="default_rule" value="open" <?php checked( $default_rule, 'open' ); ?>>
								<?php esc_html_e( 'Open', 'wp-endpoint-guard' ); ?>
							</label>
							<br>
							<label>
								<input type="radio" name="default_rule" value="auth" <?php checked( $default_rule, 'auth' ); ?>>
								<?php esc_html_e( 'Require Auth', 'wp-endpoint-guard' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Fallback rule for endpoints without a specific rule set.', 'wp-endpoint-guard' ); ?></p>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Lockdown Mode', 'wp-endpoint-guard' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="lockdown" value="1" <?php checked( $lockdown, '1' ); ?>>
							<?php esc_html_e( 'Disable REST API for unauthenticated users globally', 'wp-endpoint-guard' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Overrides all per-endpoint rules. Use as a kill switch.', 'wp-endpoint-guard' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Hide REST API Index', 'wp-endpoint-guard' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hide_index" value="1" <?php checked( $hide_index, '1' ); ?>>
							<?php esc_html_e( 'Return 404 for unauthenticated requests to /wp-json/', 'wp-endpoint-guard' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Basic Authentication', 'wp-endpoint-guard' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="basic_auth" value="1" <?php checked( $basic_auth, '1' ); ?>>
							<?php esc_html_e( 'Enable HTTP Basic Authentication', 'wp-endpoint-guard' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Allows authentication via Authorization: Basic header. Only enable if your site uses HTTPS.', 'wp-endpoint-guard' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'JWT Secret Key', 'wp-endpoint-guard' ); ?></th>
					<td>
						<div class="wpeg-jwt-secret-wrap">
							<input type="text" id="wpeg-jwt-secret" value="<?php echo esc_attr( $jwt_secret ); ?>" class="large-text code" readonly>
							<button type="button" id="wpeg-regenerate-secret" class="button button-secondary">
								<?php esc_html_e( 'Regenerate', 'wp-endpoint-guard' ); ?>
							</button>
						</div>
						<p class="description wpeg-warning"><?php esc_html_e( 'Warning: Regenerating the secret will invalidate all existing JWT tokens.', 'wp-endpoint-guard' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wpeg-jwt-expiry"><?php esc_html_e( 'JWT Token Expiry', 'wp-endpoint-guard' ); ?></label>
					</th>
					<td>
						<input type="number" id="wpeg-jwt-expiry" name="jwt_expiry" value="<?php echo esc_attr( $jwt_expiry ); ?>" class="small-text" min="60" step="1">
						<?php esc_html_e( 'seconds', 'wp-endpoint-guard' ); ?>
						<p class="description"><?php esc_html_e( 'Default: 3600 (1 hour). Minimum: 60 seconds.', 'wp-endpoint-guard' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" id="wpeg-save-settings">
				<?php esc_html_e( 'Save Settings', 'wp-endpoint-guard' ); ?>
			</button>
			<span id="wpeg-settings-status" class="wpeg-status-msg"></span>
		</p>
	</form>
</div>
