<?php
/**
 * Fixes tab template.
 *
 * @package SystemReport
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>

<div class="sr-fixes-wrap"
	<?php if ( $sr_iapi ) : ?>
		data-wp-init="callbacks.initFixes"
	<?php endif; ?>
>

	<div class="sr-fixes-header card">
		<h2><?php esc_html_e( 'Available Fixes', 'wp-system-report' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Automated fixes for common WordPress issues. Each fix shows its risk level and whether issues were detected. Review descriptions carefully before running.', 'wp-system-report' ); ?>
		</p>
	</div>

	<div id="sr-fixes-loading" class="sr-fixes-loading"
		<?php if ( $sr_iapi ) : ?>
			data-wp-bind--hidden="!state.fixesLoadingVisible"
		<?php endif; ?>
	>
		<p class="sr-loading"><span class="spinner is-active"></span> <?php esc_html_e( 'Loading available fixes...', 'wp-system-report' ); ?></p>
	</div>

	<div id="sr-fixes-error" class="sr-fixes-error"
		<?php if ( $sr_iapi ) : ?>
			data-wp-bind--hidden="!state.fixesErrorVisible"
			hidden
		<?php else : ?>
			style="display: none;"
		<?php endif; ?>
	>
		<div class="notice notice-error inline">
			<p id="sr-fixes-error-message"
				<?php if ( $sr_iapi ) : ?>
					data-wp-text="state.fixes.errorMessage"
				<?php endif; ?>
			></p>
		</div>
	</div>

	<div id="sr-fixes-empty" class="sr-fixes-empty"
		<?php if ( $sr_iapi ) : ?>
			data-wp-bind--hidden="!state.fixesEmptyVisible"
			hidden
		<?php else : ?>
			style="display: none;"
		<?php endif; ?>
	>
		<p><?php esc_html_e( 'No fixers are available.', 'wp-system-report' ); ?></p>
	</div>

	<div id="sr-fixes-list"
		<?php if ( $sr_iapi ) : ?>
			data-wp-bind--hidden="!state.fixesListVisible"
			hidden
		<?php else : ?>
			style="display: none;"
		<?php endif; ?>
	>
		<?php if ( $sr_iapi ) : ?>
			<template data-wp-each="state.fixes.categories">
				<div class="sr-fixes-category">
					<h2 class="sr-fixes-category-heading" data-wp-text="context.item.label"></h2>
					<template data-wp-each="context.item.fixers">
						<div class="sr-fixer-card card" data-wp-bind--data-fix-id="context.item.id">
							<!-- Header row: label + risk badge -->
							<div class="sr-fixer-card-header">
								<h3 class="sr-fixer-card-title" data-wp-text="context.item.label"></h3>
								<span class="sr-badge sr-risk-badge"
									data-wp-class--sr-risk-low="context.item.isRiskLow"
									data-wp-class--sr-risk-medium="context.item.isRiskMedium"
									data-wp-class--sr-risk-high="context.item.isRiskHigh"
									data-wp-text="context.item.risk_label"
								></span>
							</div>
							<!-- Description -->
							<p class="sr-fixer-card-description" data-wp-text="context.item.description"></p>
							<!-- Status + action -->
							<div class="sr-fixer-card-actions">
								<span class="sr-fixer-status"
									data-wp-class--sr-fixer-status-warning="context.item.can_fix"
									data-wp-class--sr-fixer-status-good="!context.item.can_fix"
								>
									<span class="dashicons"
										data-wp-class--dashicons-warning="context.item.can_fix"
										data-wp-class--dashicons-yes-alt="!context.item.can_fix"
									></span>
									<span data-wp-text="context.item.statusLabel"></span>
								</span>
								<button type="button"
									class="button button-primary sr-run-fix-btn"
									data-wp-on--click="actions.handleRunFix"
									data-wp-bind--data-fix-id="context.item.id"
									data-wp-bind--disabled="context.item.isDisabled"
									data-wp-text="context.item.buttonLabel"
								></button>
							</div>
							<!-- Result area -->
							<div class="sr-fixer-result"
								data-wp-bind--hidden="!context.item.result"
							>
								<div class="notice inline sr-result-notice"
									data-wp-class--notice-success="context.item.result.isSuccess"
									data-wp-class--notice-error="context.item.result.isError"
									data-wp-class--notice-info="context.item.result.isInfo"
								>
									<p>
										<strong data-wp-text="context.item.result.statusLabel"></strong>
										<span data-wp-text="context.item.result.message"></span>
									</p>
								</div>
								<details class="sr-result-details"
									data-wp-bind--hidden="!context.item.result.hasDetails"
								>
									<summary><?php esc_html_e( 'Details', 'wp-system-report' ); ?></summary>
									<div class="sr-result-details-content">
										<div class="sr-result-snapshot"
											data-wp-bind--hidden="!context.item.result.hasBefore"
										>
											<strong><?php esc_html_e( 'Before:', 'wp-system-report' ); ?></strong>
											<pre data-wp-text="context.item.result.beforeJson"></pre>
										</div>
										<div class="sr-result-snapshot"
											data-wp-bind--hidden="!context.item.result.hasAfter"
										>
											<strong><?php esc_html_e( 'After:', 'wp-system-report' ); ?></strong>
											<pre data-wp-text="context.item.result.afterJson"></pre>
										</div>
									</div>
								</details>
							</div>
						</div>
					</template>
				</div>
			</template>
		<?php endif; ?>
	</div>

	<!-- Confirmation Modal -->
	<div
		id="sr-confirm-modal"
		class="sr-confirm-modal"
		role="dialog"
		aria-modal="true"
		aria-labelledby="sr-confirm-modal-title"
		aria-describedby="sr-confirm-modal-description"
		tabindex="-1"
		<?php if ( $sr_iapi ) : ?>
			data-wp-bind--hidden="!state.confirmModalVisible"
			data-wp-on--keydown="actions.handleModalKeydown"
			hidden
		<?php else : ?>
			style="display: none;"
		<?php endif; ?>
	>
		<div class="sr-confirm-modal-backdrop" aria-hidden="true"
			<?php if ( $sr_iapi ) : ?>
				data-wp-on--click="actions.hideConfirmModal"
			<?php endif; ?>
		></div>
		<div class="sr-confirm-modal-content">
			<h3 id="sr-confirm-modal-title"
				<?php if ( $sr_iapi ) : ?>
					data-wp-text="state.fixes.modalTitle"
				<?php endif; ?>
			></h3>
			<p id="sr-confirm-modal-message"
				<?php if ( $sr_iapi ) : ?>
					data-wp-text="state.fixes.modalMessage"
				<?php endif; ?>
			></p>
			<div id="sr-confirm-modal-description" class="sr-confirm-modal-description"
				<?php if ( $sr_iapi ) : ?>
					data-wp-text="state.fixes.modalDescription"
				<?php endif; ?>
			></div>
			<div class="sr-confirm-modal-actions">
				<button type="button" class="button button-primary" id="sr-confirm-run"
					<?php if ( $sr_iapi ) : ?>
						data-wp-on--click="actions.confirmAndRun"
					<?php endif; ?>
				>
					<?php esc_html_e( 'Yes, run fix', 'wp-system-report' ); ?>
				</button>
				<button type="button" class="button" id="sr-confirm-cancel"
					<?php if ( $sr_iapi ) : ?>
						data-wp-on--click="actions.hideConfirmModal"
					<?php endif; ?>
				>
					<?php esc_html_e( 'Cancel', 'wp-system-report' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>
