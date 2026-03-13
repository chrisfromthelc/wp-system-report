<?php
/**
 * Admin page template.
 *
 * @package SystemReport
 *
 * @var array  $report         Report data from Report_Generator::generate().
 * @var string $sr_current_tab Current active tab.
 */

defined( 'ABSPATH' ) || exit;

// Template variables are scoped to the including method, not truly global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$sr_iapi = \SystemReport\Features::has_interactivity();
?>
<div class="wrap wp-system-report-wrap"
	<?php if ( $sr_iapi ) : ?>
		data-wp-interactive="wp-system-report"
	<?php endif; ?>
>
	<h1><?php esc_html_e( 'WP System Report', 'wp-system-report' ); ?></h1>

	<nav class="nav-tab-wrapper wp-clearfix">
		<a href="<?php echo esc_url( admin_url( 'tools.php?page=wp-system-report' ) ); ?>"
			class="nav-tab <?php echo 'report' === $sr_current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'System Report', 'wp-system-report' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'tools.php?page=wp-system-report&tab=error-log' ) ); ?>"
			class="nav-tab <?php echo 'error-log' === $sr_current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Error Log', 'wp-system-report' ); ?>
		</a>
		<?php if ( \SystemReport\Features::has_fixers() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=wp-system-report&tab=fixes' ) ); ?>"
				class="nav-tab <?php echo 'fixes' === $sr_current_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Fixes', 'wp-system-report' ); ?>
			</a>
		<?php endif; ?>
	</nav>

	<?php if ( 'report' === $sr_current_tab ) : ?>

		<div class="sr-report-actions updated">
			<p>
				<?php esc_html_e( 'Copy and paste this information when contacting support or use the AI export for detailed analysis:', 'wp-system-report' ); ?>
			</p>
			<p class="submit">
				<button type="button" class="button-primary" id="sr-generate-report"
					<?php if ( $sr_iapi ) : ?>
						data-wp-on--click="actions.generateReport"
						data-wp-bind--hidden="state.reportBtnHidden"
					<?php endif; ?>
				>
					<?php esc_html_e( 'Get system report', 'wp-system-report' ); ?>
				</button>
				<button type="button" class="button" id="sr-download-ai" style="margin-left: 4px;"
					<?php if ( $sr_iapi ) : ?>
						data-wp-on--click="actions.downloadForAi"
						data-wp-bind--disabled="state.aiGenerating"
						data-wp-text="state.aiDownloadLabel"
					<?php endif; ?>
				>
					<?php esc_html_e( 'Download for AI analysis', 'wp-system-report' ); ?>
				</button>
			</p>
			<div id="sr-debug-report"
				<?php if ( $sr_iapi ) : ?>
					data-wp-bind--hidden="!state.debugReportVisible"
					hidden
				<?php else : ?>
					style="display: none;"
				<?php endif; ?>
			>
				<textarea readonly="readonly" rows="12"></textarea>
				<p class="submit">
					<button type="button" class="button-primary" id="sr-download-support"
						<?php if ( $sr_iapi ) : ?>
							data-wp-on--click="actions.downloadForSupport"
						<?php endif; ?>
					>
						<?php esc_html_e( 'Download for support', 'wp-system-report' ); ?>
					</button>
					<button type="button" class="button" id="sr-copy-support"
						<?php if ( $sr_iapi ) : ?>
							data-wp-on--click="actions.copyForSupport"
						<?php endif; ?>
					>
						<?php esc_html_e( 'Copy for support', 'wp-system-report' ); ?>
					</button>
					<button type="button" class="button" id="sr-copy-github"
						<?php if ( $sr_iapi ) : ?>
							data-wp-on--click="actions.copyForGitHub"
						<?php endif; ?>
					>
						<?php esc_html_e( 'Copy for GitHub', 'wp-system-report' ); ?>
					</button>
				</p>
				<p class="sr-copy-error"
					<?php if ( $sr_iapi ) : ?>
						data-wp-bind--hidden="!state.copyErrorVisible"
						hidden
					<?php else : ?>
						style="display: none;"
					<?php endif; ?>
				>
					<?php esc_html_e( 'Copying to clipboard failed. Please press Ctrl/Cmd+C to copy.', 'wp-system-report' ); ?>
				</p>
			</div>
		</div>

		<?php
		foreach ( $report as $sr_section_id => $sr_section ) :
			$sr_fields = $sr_section['fields'];
			if ( empty( $sr_fields ) ) {
				continue;
			}
			?>
			<table class="sr_status_table widefat" cellspacing="0">
				<thead>
					<tr>
						<th colspan="3" data-export-label="<?php echo esc_attr( $sr_section['label'] ); ?>">
							<h2><?php echo esc_html( $sr_section['label'] ); ?></h2>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sr_fields as $sr_field ) : ?>
						<?php
						if ( ! empty( $sr_field['private'] ) ) {
							continue;
						}

						$sr_status_class = '';
						if ( ! empty( $sr_field['status'] ) ) {
							$sr_status_class = 'status-' . sanitize_html_class( $sr_field['status'] );
						}

						$sr_export_label = ! empty( $sr_field['export_label'] ) ? $sr_field['export_label'] : $sr_field['label'];
						?>
						<tr>
							<td data-export-label="<?php echo esc_attr( $sr_export_label ); ?>">
								<?php echo esc_html( $sr_field['label'] ); ?>:
							</td>
							<td class="help">
								<?php if ( ! empty( $sr_field['description'] ) ) : ?>
									<span class="dashicons dashicons-editor-help" title="<?php echo esc_attr( $sr_field['description'] ); ?>"></span>
								<?php endif; ?>
							</td>
							<td class="<?php echo esc_attr( $sr_status_class ); ?>">
								<?php
								$sr_value = $sr_field['value'];

								if ( 'good' === $sr_field['status'] ) {
									echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> ' . esc_html( $sr_value ) . '</mark>';
								} elseif ( 'critical' === $sr_field['status'] ) {
									echo '<mark class="error"><span class="dashicons dashicons-warning"></span> ' . esc_html( $sr_value ) . '</mark>';
								} elseif ( 'warning' === $sr_field['status'] ) {
									echo '<mark class="warning"><span class="dashicons dashicons-warning"></span> ' . esc_html( $sr_value ) . '</mark>';
								} else {
									echo esc_html( $sr_value );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>

	<?php elseif ( 'error-log' === $sr_current_tab ) : ?>

		<?php include WP_SYSTEM_REPORT_DIR . 'templates/error-log-tab.php'; ?>

	<?php elseif ( 'fixes' === $sr_current_tab ) : ?>

		<?php include WP_SYSTEM_REPORT_DIR . 'templates/fixes-tab.php'; ?>

	<?php endif; ?>
</div>
