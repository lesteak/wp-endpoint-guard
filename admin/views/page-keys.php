<?php

defined( 'ABSPATH' ) || exit;

$keys         = $this->key_manager->get_all();
$active_count = $this->key_manager->count_active();
$at_limit     = $active_count >= 2;
?>

<div class="wpeg-keys-wrap">
	<div class="wpeg-keys-header">
		<h2><?php esc_html_e( 'API Keys', 'wp-endpoint-guard' ); ?></h2>
		<div class="wpeg-generate-wrap">
			<input type="text" id="wpeg-new-key-name" placeholder="<?php esc_attr_e( 'Key name (e.g. Mobile App)', 'wp-endpoint-guard' ); ?>" class="regular-text" <?php echo $at_limit ? 'disabled' : ''; ?>>
			<button type="button" id="wpeg-generate-key" class="button button-primary" <?php echo $at_limit ? 'disabled' : ''; ?>>
				<?php esc_html_e( 'Generate New Key', 'wp-endpoint-guard' ); ?>
			</button>
			<?php if ( $at_limit ) : ?>
				<span class="wpeg-limit-notice"><?php esc_html_e( 'Free tier limit: 2 active keys.', 'wp-endpoint-guard' ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<!-- Key reveal modal -->
	<div id="wpeg-key-modal" class="wpeg-modal" style="display:none;">
		<div class="wpeg-modal-content">
			<h3><?php esc_html_e( 'Your New API Key', 'wp-endpoint-guard' ); ?></h3>
			<p><?php esc_html_e( 'Copy this key now. It will not be shown again.', 'wp-endpoint-guard' ); ?></p>
			<div class="wpeg-key-display">
				<code id="wpeg-raw-key"></code>
				<button type="button" id="wpeg-copy-key" class="button"><?php esc_html_e( 'Copy', 'wp-endpoint-guard' ); ?></button>
			</div>
			<p>
				<button type="button" id="wpeg-close-modal" class="button button-primary"><?php esc_html_e( "I've copied this key", 'wp-endpoint-guard' ); ?></button>
			</p>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Name', 'wp-endpoint-guard' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Key Prefix', 'wp-endpoint-guard' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Created', 'wp-endpoint-guard' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Last Used', 'wp-endpoint-guard' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'wp-endpoint-guard' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'wp-endpoint-guard' ); ?></th>
			</tr>
		</thead>
		<tbody id="wpeg-keys-tbody">
			<?php if ( empty( $keys ) ) : ?>
				<tr class="wpeg-no-keys">
					<td colspan="6"><?php esc_html_e( 'No API keys yet. Generate one above.', 'wp-endpoint-guard' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $keys as $key ) : ?>
					<tr data-key-id="<?php echo esc_attr( $key->id ); ?>">
						<td><?php echo esc_html( $key->name ); ?></td>
						<td><code><?php echo esc_html( $key->key_prefix ); ?>...</code></td>
						<td><?php echo esc_html( $key->created_at ); ?></td>
						<td><?php echo $key->last_used_at ? esc_html( human_time_diff( strtotime( $key->last_used_at ) ) . ' ' . __( 'ago', 'wp-endpoint-guard' ) ) : esc_html__( 'Never', 'wp-endpoint-guard' ); ?></td>
						<td>
							<span class="wpeg-status wpeg-status-<?php echo esc_attr( $key->status ); ?>">
								<?php echo esc_html( ucfirst( $key->status ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $key->status === 'active' ) : ?>
								<button type="button" class="button wpeg-revoke-key" data-key-id="<?php echo esc_attr( $key->id ); ?>">
									<?php esc_html_e( 'Revoke', 'wp-endpoint-guard' ); ?>
								</button>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
