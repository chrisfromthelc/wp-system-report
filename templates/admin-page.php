<?php
/**
 * Admin page template.
 *
 * @package SystemReport
 *
 * @var array $report Report data from Report_Generator::generate().
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap system-report-wrap">
	<h1><?php esc_html_e( 'System Report', 'system-report' ); ?></h1>

	<div class="sr-report-actions updated">
		<p>
			<?php esc_html_e( 'Copy and paste this information when contacting support or use the AI export for detailed analysis:', 'system-report' ); ?>
		</p>
		<p class="submit">
			<button type="button" class="button-primary" id="sr-generate-report">
				<?php esc_html_e( 'Get system report', 'system-report' ); ?>
			</button>
			<button type="button" class="button" id="sr-download-ai" style="margin-left: 4px;">
				<?php esc_html_e( 'Download for AI analysis', 'system-report' ); ?>
			</button>
		</p>
		<div id="sr-debug-report" style="display: none;">
			<textarea readonly="readonly" rows="12"></textarea>
			<p class="submit">
				<button type="button" class="button-primary" id="sr-download-support">
					<?php esc_html_e( 'Download for support', 'system-report' ); ?>
				</button>
				<button type="button" class="button" id="sr-copy-support">
					<?php esc_html_e( 'Copy for support', 'system-report' ); ?>
				</button>
				<button type="button" class="button" id="sr-copy-github">
					<?php esc_html_e( 'Copy for GitHub', 'system-report' ); ?>
				</button>
			</p>
			<p class="sr-copy-error" style="display: none;">
				<?php esc_html_e( 'Copying to clipboard failed. Please press Ctrl/Cmd+C to copy.', 'system-report' ); ?>
			</p>
		</div>
	</div>

	<?php
	foreach ( $report as $section_id => $section ) :
		$fields = $section['fields'];
		if ( empty( $fields ) ) {
			continue;
		}
		?>
		<table class="sr_status_table widefat" cellspacing="0">
			<thead>
				<tr>
					<th colspan="3" data-export-label="<?php echo esc_attr( $section['label'] ); ?>">
						<h2><?php echo esc_html( $section['label'] ); ?></h2>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $fields as $field ) : ?>
					<?php
					if ( ! empty( $field['private'] ) ) {
						continue;
					}

					$status_class = '';
					if ( ! empty( $field['status'] ) ) {
						$status_class = 'status-' . sanitize_html_class( $field['status'] );
					}

					$export_label = ! empty( $field['export_label'] ) ? $field['export_label'] : $field['label'];
					?>
					<tr>
						<td data-export-label="<?php echo esc_attr( $export_label ); ?>">
							<?php echo esc_html( $field['label'] ); ?>:
						</td>
						<td class="help">
							<?php if ( ! empty( $field['description'] ) ) : ?>
								<span class="dashicons dashicons-editor-help" title="<?php echo esc_attr( $field['description'] ); ?>"></span>
							<?php endif; ?>
						</td>
						<td class="<?php echo esc_attr( $status_class ); ?>">
							<?php
							$value = $field['value'];

							if ( 'good' === $field['status'] ) {
								echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> ' . esc_html( $value ) . '</mark>';
							} elseif ( 'critical' === $field['status'] ) {
								echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html( $value ) . '</mark>';
							} elseif ( 'warning' === $field['status'] ) {
								echo '<mark class="warning"><span class="dashicons dashicons-warning"></span> ' . esc_html( $value ) . '</mark>';
							} else {
								echo esc_html( $value );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>
</div>
