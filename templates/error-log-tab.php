<?php
/**
 * Error log tab template.
 *
 * @package SystemReport
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>

<div class="sr-error-log-wrap"
	<?php if ( $sr_iapi ) : ?>
		data-wp-init="callbacks.initErrorLog"
	<?php endif; ?>
>

	<!-- Debug Configuration -->
	<div class="sr-debug-config card">
		<h2><?php esc_html_e( 'Debug Configuration', 'wp-system-report' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Toggle WordPress debug logging. Changes modify wp-config.php and take effect on the next page load.', 'wp-system-report' ); ?>
		</p>

		<div id="sr-debug-status" class="sr-debug-status"
			<?php if ( $sr_iapi ) : ?>
				data-wp-bind--hidden="!state.debugStatusVisible"
			<?php endif; ?>
		>
			<p class="sr-loading"><span class="spinner is-active"></span> <?php esc_html_e( 'Loading status...', 'wp-system-report' ); ?></p>
		</div>

		<div id="sr-debug-controls" class="sr-debug-controls"
			<?php if ( $sr_iapi ) : ?>
				data-wp-bind--hidden="!state.debugControlsVisible"
				hidden
			<?php else : ?>
				style="display: none;"
			<?php endif; ?>
		>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'WP_DEBUG', 'wp-system-report' ); ?></th>
						<td>
							<span id="sr-wp-debug-badge" class="sr-badge"
								<?php if ( $sr_iapi ) : ?>
									data-wp-class--sr-badge-on="state.errorLog.wpDebug"
									data-wp-class--sr-badge-off="!state.errorLog.wpDebug"
									data-wp-text="state.wpDebugBadgeText"
								<?php endif; ?>
							></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WP_DEBUG_LOG', 'wp-system-report' ); ?></th>
						<td>
							<span id="sr-wp-debug-log-badge" class="sr-badge"
								<?php if ( $sr_iapi ) : ?>
									data-wp-class--sr-badge-on="state.errorLog.wpDebugLog"
									data-wp-class--sr-badge-off="!state.errorLog.wpDebugLog"
									data-wp-text="state.wpDebugLogBadgeText"
								<?php endif; ?>
							></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WP_DEBUG_DISPLAY', 'wp-system-report' ); ?></th>
						<td>
							<span id="sr-wp-debug-display-badge" class="sr-badge"
								<?php if ( $sr_iapi ) : ?>
									data-wp-class--sr-badge-on="state.errorLog.wpDebugDisplay"
									data-wp-class--sr-badge-off="!state.errorLog.wpDebugDisplay"
									data-wp-text="state.wpDebugDisplayBadgeText"
								<?php endif; ?>
							></span>
						</td>
					</tr>
				</tbody>
			</table>

			<div id="sr-toggle-actions" class="sr-toggle-actions"
				<?php if ( $sr_iapi ) : ?>
					data-wp-bind--hidden="!state.toggleActionsVisible"
					hidden
				<?php else : ?>
					style="display: none;"
				<?php endif; ?>
			>
				<button type="button" class="button button-primary" id="sr-enable-debug"
					<?php if ( $sr_iapi ) : ?>
						data-wp-on--click="actions.enableDebug"
						data-wp-bind--disabled="state.toggleBtnsDisabled"
					<?php endif; ?>
				>
					<?php esc_html_e( 'Enable debug logging', 'wp-system-report' ); ?>
				</button>
				<button type="button" class="button" id="sr-disable-debug"
					<?php if ( $sr_iapi ) : ?>
						data-wp-on--click="actions.disableDebug"
						data-wp-bind--disabled="state.toggleBtnsDisabled"
					<?php endif; ?>
				>
					<?php esc_html_e( 'Disable debug logging', 'wp-system-report' ); ?>
				</button>
			</div>

			<div id="sr-readonly-notice" class="notice notice-info inline"
				<?php if ( $sr_iapi ) : ?>
					data-wp-bind--hidden="!state.readOnlyNoticeVisible"
					hidden
				<?php else : ?>
					style="display: none;"
				<?php endif; ?>
			>
				<p>
					<?php esc_html_e( 'wp-config.php is not writable or file modifications are disabled. Add these lines manually:', 'wp-system-report' ); ?>
				</p>
				<pre class="sr-code-snippet">define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );</pre>
				<p>
					<?php esc_html_e( 'Or use WP-CLI:', 'wp-system-report' ); ?>
				</p>
				<pre class="sr-code-snippet">wp config set WP_DEBUG true --raw
wp config set WP_DEBUG_LOG true --raw
wp config set WP_DEBUG_DISPLAY false --raw</pre>
			</div>

			<div id="sr-toggle-notice" class="sr-toggle-notice"
				<?php if ( $sr_iapi ) : ?>
					data-wp-bind--hidden="!state.toggleNoticeVisible"
					data-wp-class="state.toggleNoticeClass"
					data-wp-text="state.errorLog.noticeMessage"
					hidden
				<?php else : ?>
					style="display: none;"
				<?php endif; ?>
			></div>
		</div>
	</div>

	<!-- Error Log Viewer -->
	<div class="sr-log-viewer card">
		<h2><?php esc_html_e( 'Error Log Viewer', 'wp-system-report' ); ?></h2>

		<div id="sr-log-file-info" class="sr-log-file-info"
			<?php if ( $sr_iapi ) : ?>
				data-wp-bind--hidden="!state.logFileInfoVisible"
				hidden
			<?php else : ?>
				style="display: none;"
			<?php endif; ?>
		>
			<span class="dashicons dashicons-media-text"></span>
			<span id="sr-log-path"
				<?php if ( $sr_iapi ) : ?>
					data-wp-text="state.errorLog.filePath"
				<?php endif; ?>
			></span>
			<span class="sr-log-size">(<span id="sr-log-size"
				<?php if ( $sr_iapi ) : ?>
					data-wp-text="state.errorLog.fileSize"
				<?php endif; ?>
			></span>)</span>
		</div>

		<div class="sr-log-controls">
			<label for="sr-log-lines" class="sr-log-lines-label">
				<?php esc_html_e( 'Lines:', 'wp-system-report' ); ?>
				<input type="number" id="sr-log-lines" min="1" max="10000" value="100" class="small-text"
					<?php if ( $sr_iapi ) : ?>
						data-wp-on--change="actions.updateLines"
					<?php endif; ?>
				/>
			</label>

			<button type="button" class="button button-primary" id="sr-load-log"
				<?php if ( $sr_iapi ) : ?>
					data-wp-on--click="actions.loadLog"
					data-wp-bind--hidden="!state.loadBtnVisible"
					data-wp-bind--disabled="state.loadBtnDisabled"
					data-wp-text="state.loadBtnLabel"
				<?php endif; ?>
			>
				<?php esc_html_e( 'Load error log', 'wp-system-report' ); ?>
			</button>
			<button type="button" class="button" id="sr-refresh-log"
				<?php if ( $sr_iapi ) : ?>
					data-wp-on--click="actions.refreshLog"
					data-wp-bind--hidden="!state.refreshBtnVisible"
					hidden
				<?php else : ?>
					style="display: none;"
				<?php endif; ?>
			>
				<?php esc_html_e( 'Refresh', 'wp-system-report' ); ?>
			</button>
			<button type="button" class="button" id="sr-download-log"
				<?php if ( $sr_iapi ) : ?>
					data-wp-on--click="actions.downloadLog"
					data-wp-bind--hidden="!state.logActionsVisible"
					hidden
				<?php else : ?>
					style="display: none;"
				<?php endif; ?>
			>
				<?php esc_html_e( 'Download', 'wp-system-report' ); ?>
			</button>
			<button type="button" class="button" id="sr-copy-log"
				<?php if ( $sr_iapi ) : ?>
					data-wp-on--click="actions.copyLog"
					data-wp-bind--hidden="!state.logActionsVisible"
					hidden
				<?php else : ?>
					style="display: none;"
				<?php endif; ?>
			>
				<?php esc_html_e( 'Copy to clipboard', 'wp-system-report' ); ?>
			</button>
			<label for="sr-include-report" class="sr-include-report-label" id="sr-include-report-label"
				<?php if ( $sr_iapi ) : ?>
					data-wp-bind--hidden="!state.logActionsVisible"
					hidden
				<?php else : ?>
					style="display: none;"
				<?php endif; ?>
			>
				<input type="checkbox" id="sr-include-report"
					<?php if ( $sr_iapi ) : ?>
						data-wp-on--change="actions.toggleIncludeReport"
					<?php endif; ?>
				/>
				<?php esc_html_e( 'Include system report', 'wp-system-report' ); ?>
			</label>
		</div>

		<div id="sr-log-output" class="sr-log-output"
			<?php if ( $sr_iapi ) : ?>
				data-wp-bind--hidden="!state.logOutputVisible"
				hidden
			<?php else : ?>
				style="display: none;"
			<?php endif; ?>
		>
			<pre id="sr-log-content"
				<?php if ( $sr_iapi ) : ?>
					data-wp-text="state.errorLog.logContent"
				<?php endif; ?>
			></pre>
		</div>

		<div id="sr-log-empty" class="sr-log-empty"
			<?php if ( $sr_iapi ) : ?>
				data-wp-bind--hidden="!state.logEmptyVisible"
				hidden
			<?php else : ?>
				style="display: none;"
			<?php endif; ?>
		>
			<p><?php esc_html_e( 'No log entries found.', 'wp-system-report' ); ?></p>
		</div>

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
