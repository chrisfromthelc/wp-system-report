<?php
/**
 * Fixes tab template.
 *
 * @package SystemReport
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>

<div class="sr-fixes-wrap">

	<div class="sr-fixes-header card">
		<h2><?php esc_html_e( 'Available Fixes', 'wp-system-report' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Automated fixes for common WordPress issues. Each fix shows its risk level and whether issues were detected. Review descriptions carefully before running.', 'wp-system-report' ); ?>
		</p>
	</div>

	<div id="sr-fixes-loading" class="sr-fixes-loading">
		<p class="sr-loading"><span class="spinner is-active"></span> <?php esc_html_e( 'Loading available fixes...', 'wp-system-report' ); ?></p>
	</div>

	<div id="sr-fixes-error" class="sr-fixes-error" style="display: none;">
		<div class="notice notice-error inline">
			<p id="sr-fixes-error-message"></p>
		</div>
	</div>

	<div id="sr-fixes-empty" class="sr-fixes-empty" style="display: none;">
		<p><?php esc_html_e( 'No fixers are available.', 'wp-system-report' ); ?></p>
	</div>

	<div id="sr-fixes-list" style="display: none;"></div>

	<!-- Confirmation Modal -->
	<div id="sr-confirm-modal" class="sr-confirm-modal" style="display: none;">
		<div class="sr-confirm-modal-backdrop"></div>
		<div class="sr-confirm-modal-content">
			<h3 id="sr-confirm-modal-title"></h3>
			<p id="sr-confirm-modal-message"></p>
			<div id="sr-confirm-modal-description" class="sr-confirm-modal-description"></div>
			<div class="sr-confirm-modal-actions">
				<button type="button" class="button button-primary" id="sr-confirm-run">
					<?php esc_html_e( 'Yes, run fix', 'wp-system-report' ); ?>
				</button>
				<button type="button" class="button" id="sr-confirm-cancel">
					<?php esc_html_e( 'Cancel', 'wp-system-report' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>
