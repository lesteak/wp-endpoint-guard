<?php

defined( 'ABSPATH' ) || exit;

$rules      = $this->registry->get_all_rules();
$namespaces = $this->registry->get_namespaces();

// Group rules by namespace.
$grouped = [];
foreach ( $rules as $endpoint ) {
	$grouped[ $endpoint->namespace ][] = $endpoint;
}

// Sort: custom namespaces first, core WP namespaces last.
$core_prefixes = [ 'wp/', 'wp-', 'oembed/', 'batch/' ];
$custom_groups = [];
$core_groups   = [];

foreach ( $grouped as $ns => $endpoints ) {
	$is_core = false;
	foreach ( $core_prefixes as $prefix ) {
		if ( str_starts_with( $ns, $prefix ) ) {
			$is_core = true;
			break;
		}
	}
	if ( $is_core ) {
		$core_groups[ $ns ] = $endpoints;
	} else {
		$custom_groups[ $ns ] = $endpoints;
	}
}

ksort( $custom_groups );
ksort( $core_groups );
$sorted_groups = array_merge( $custom_groups, $core_groups );
?>

<div class="wpeg-endpoints-wrap">
	<div class="wpeg-endpoints-header">
		<h2><?php esc_html_e( 'Endpoint Rules', 'wp-endpoint-guard' ); ?></h2>
		<div class="wpeg-endpoints-header-actions">
			<button type="button" id="wpeg-expand-all" class="button button-secondary"><?php esc_html_e( 'Expand All', 'wp-endpoint-guard' ); ?></button>
			<button type="button" id="wpeg-collapse-all" class="button button-secondary"><?php esc_html_e( 'Collapse All', 'wp-endpoint-guard' ); ?></button>
			<button type="button" id="wpeg-refresh-routes" class="button button-secondary"><?php esc_html_e( 'Refresh Routes', 'wp-endpoint-guard' ); ?></button>
		</div>
	</div>

	<!-- Filters -->
	<div class="wpeg-filters">
		<input type="text" id="wpeg-filter-search" placeholder="<?php esc_attr_e( 'Search routes...', 'wp-endpoint-guard' ); ?>" class="regular-text">

		<select id="wpeg-filter-rule">
			<option value=""><?php esc_html_e( 'All Rules', 'wp-endpoint-guard' ); ?></option>
			<option value="open"><?php esc_html_e( 'Open', 'wp-endpoint-guard' ); ?></option>
			<option value="auth"><?php esc_html_e( 'Require Auth', 'wp-endpoint-guard' ); ?></option>
			<option value="block"><?php esc_html_e( 'Block', 'wp-endpoint-guard' ); ?></option>
		</select>
	</div>

	<!-- Bulk Actions -->
	<div class="wpeg-bulk-actions">
		<label>
			<input type="checkbox" id="wpeg-select-all">
			<?php esc_html_e( 'Select All Visible', 'wp-endpoint-guard' ); ?>
		</label>
		<select id="wpeg-bulk-rule">
			<option value=""><?php esc_html_e( 'Bulk Actions', 'wp-endpoint-guard' ); ?></option>
			<option value="open"><?php esc_html_e( 'Set Open', 'wp-endpoint-guard' ); ?></option>
			<option value="auth"><?php esc_html_e( 'Set Require Auth', 'wp-endpoint-guard' ); ?></option>
			<option value="block"><?php esc_html_e( 'Set Block', 'wp-endpoint-guard' ); ?></option>
		</select>
		<button type="button" id="wpeg-apply-bulk" class="button"><?php esc_html_e( 'Apply', 'wp-endpoint-guard' ); ?></button>
	</div>

	<?php if ( empty( $rules ) ) : ?>
		<p><?php esc_html_e( 'No endpoints discovered yet. Click Refresh Routes above.', 'wp-endpoint-guard' ); ?></p>
	<?php else : ?>
		<!-- Namespace quick nav -->
		<div class="wpeg-namespace-nav">
			<?php foreach ( $sorted_groups as $ns => $endpoints ) :
				$is_custom = isset( $custom_groups[ $ns ] );
			?>
				<a href="#wpeg-ns-<?php echo esc_attr( sanitize_title( $ns ) ); ?>" class="wpeg-nav-pill <?php echo $is_custom ? 'wpeg-nav-pill-custom' : ''; ?>">
					<?php echo esc_html( $ns ); ?>
					<span class="wpeg-nav-count"><?php echo count( $endpoints ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<!-- Accordion sections -->
		<?php foreach ( $sorted_groups as $ns => $endpoints ) :
			$is_custom  = isset( $custom_groups[ $ns ] );
			$section_id = 'wpeg-ns-' . sanitize_title( $ns );

			$rule_counts = [ 'open' => 0, 'auth' => 0, 'block' => 0 ];
			foreach ( $endpoints as $ep ) {
				if ( isset( $rule_counts[ $ep->rule ] ) ) {
					$rule_counts[ $ep->rule ]++;
				}
			}
		?>
			<div class="wpeg-accordion" id="<?php echo esc_attr( $section_id ); ?>" data-namespace="<?php echo esc_attr( $ns ); ?>">
				<button type="button" class="wpeg-accordion-toggle <?php echo $is_custom ? 'wpeg-accordion-custom' : ''; ?>">
					<span class="wpeg-accordion-arrow dashicons dashicons-arrow-right-alt2"></span>
					<span class="wpeg-accordion-title"><?php echo esc_html( $ns ); ?></span>
					<span class="wpeg-accordion-meta">
						<span class="wpeg-route-count"><?php echo count( $endpoints ); ?> <?php esc_html_e( 'routes', 'wp-endpoint-guard' ); ?></span>
						<?php if ( $rule_counts['auth'] > 0 ) : ?>
							<span class="wpeg-badge wpeg-badge-auth"><?php echo (int) $rule_counts['auth']; ?> <?php esc_html_e( 'auth', 'wp-endpoint-guard' ); ?></span>
						<?php endif; ?>
						<?php if ( $rule_counts['block'] > 0 ) : ?>
							<span class="wpeg-badge wpeg-badge-block"><?php echo (int) $rule_counts['block']; ?> <?php esc_html_e( 'blocked', 'wp-endpoint-guard' ); ?></span>
						<?php endif; ?>
					</span>
				</button>

				<div class="wpeg-accordion-body" style="display:none;">
					<table class="wp-list-table widefat fixed striped wpeg-endpoints-table">
						<thead>
							<tr>
								<th scope="col" class="wpeg-col-check">
									<input type="checkbox" class="wpeg-select-all-ns" data-namespace="<?php echo esc_attr( $ns ); ?>">
								</th>
								<th scope="col"><?php esc_html_e( 'Route', 'wp-endpoint-guard' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Rule', 'wp-endpoint-guard' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $endpoints as $endpoint ) : ?>
								<tr data-rule-id="<?php echo esc_attr( $endpoint->id ); ?>" data-namespace="<?php echo esc_attr( $endpoint->namespace ); ?>" data-route="<?php echo esc_attr( $endpoint->route ); ?>" data-rule="<?php echo esc_attr( $endpoint->rule ); ?>">
									<td class="wpeg-col-check">
										<input type="checkbox" class="wpeg-endpoint-check" value="<?php echo esc_attr( $endpoint->id ); ?>">
									</td>
									<td><code><?php echo esc_html( $endpoint->route ); ?></code></td>
									<td>
										<select class="wpeg-rule-select" data-rule-id="<?php echo esc_attr( $endpoint->id ); ?>">
											<option value="open" <?php selected( $endpoint->rule, 'open' ); ?>><?php esc_html_e( 'Open', 'wp-endpoint-guard' ); ?></option>
											<option value="auth" <?php selected( $endpoint->rule, 'auth' ); ?>><?php esc_html_e( 'Require Auth', 'wp-endpoint-guard' ); ?></option>
											<option value="block" <?php selected( $endpoint->rule, 'block' ); ?>><?php esc_html_e( 'Block', 'wp-endpoint-guard' ); ?></option>
										</select>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
