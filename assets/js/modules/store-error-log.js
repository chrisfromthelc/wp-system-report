/**
 * WP System Report - Error Log Tab Interactivity Store.
 *
 * @package SystemReport
 */

import { store, getElement } from '@wordpress/interactivity';
import {
	copyToClipboard,
	showCopySuccess,
	downloadFile,
	buildFilename,
	fetchCombinedContent,
} from './store-utils.js';

const { state, actions } = store( 'wp-system-report', {
	state: {
		get debugStatusVisible() {
			return ! state.errorLog.statusLoaded;
		},
		get debugControlsVisible() {
			return state.errorLog.statusLoaded;
		},
		get toggleActionsVisible() {
			return state.errorLog.canModify;
		},
		get readOnlyNoticeVisible() {
			return state.errorLog.statusLoaded && ! state.errorLog.canModify;
		},
		get logFileInfoVisible() {
			return state.errorLog.fileExists;
		},
		get loadBtnVisible() {
			return ! state.errorLog.logLoaded;
		},
		get refreshBtnVisible() {
			return state.errorLog.logLoaded;
		},
		get logActionsVisible() {
			return state.errorLog.logLoaded && state.errorLog.hasLines;
		},
		get logOutputVisible() {
			return state.errorLog.logLoaded && state.errorLog.hasLines;
		},
		get logEmptyVisible() {
			return state.errorLog.logLoaded && ! state.errorLog.hasLines;
		},
		get loadBtnDisabled() {
			return state.errorLog.isLoading;
		},
		get loadBtnLabel() {
			return state.errorLog.isLoading
				? state.i18n.loading
				: state.i18n.loadLog;
		},
		get toggleBtnsDisabled() {
			return state.errorLog.isToggling;
		},
		get wpDebugBadgeText() {
			return actions.getBadgeText( state.errorLog.wpDebug );
		},
		get wpDebugLogBadgeText() {
			return actions.getBadgeText( state.errorLog.wpDebugLog );
		},
		get wpDebugDisplayBadgeText() {
			return actions.getBadgeText( state.errorLog.wpDebugDisplay );
		},
		get copyErrorVisible() {
			return state.copyError;
		},
		get toggleNoticeVisible() {
			return !! state.errorLog.noticeMessage;
		},
		get toggleNoticeClass() {
			return 'notice notice-' + ( state.errorLog.noticeType || 'info' ) + ' inline';
		},
	},

	actions: {
		/**
		 * Get badge display text for a debug value.
		 *
		 * @param {*} value Debug constant value.
		 * @return {string} Display text.
		 */
		getBadgeText( value ) {
			if ( value === null || value === undefined ) {
				return state.i18n.notSet;
			}
			if ( typeof value === 'string' ) {
				return value;
			}
			if ( value ) {
				return state.i18n.enabled;
			}
			return state.i18n.disabled;
		},

		/**
		 * Load debug status from the REST API.
		 */
		*loadStatus() {
			try {
				const response = yield fetch( state.config.statusUrl, {
					headers: { 'X-WP-Nonce': state.config.restNonce },
					credentials: 'same-origin',
				} );

				if ( ! response.ok ) {
					throw new Error( state.i18n.loadFailed );
				}

				const data = yield response.json();
				const toggle = data.toggle || {};

				state.errorLog.wpDebug = toggle.wp_debug ?? null;
				state.errorLog.wpDebugLog = toggle.wp_debug_log ?? null;
				state.errorLog.wpDebugDisplay = toggle.wp_debug_display ?? null;
				state.errorLog.canModify = !! toggle.can_modify;
				state.errorLog.statusLoaded = true;

				const file = data.file || {};
				if ( file.exists && file.path ) {
					state.errorLog.fileExists = true;
					state.errorLog.filePath = file.path;
					state.errorLog.fileSize = file.size_formatted;
				}

				if ( ! state.errorLog.logLoaded ) {
					const settings = data.settings || {};
					if ( settings.error_log_lines ) {
						state.errorLog.lines = settings.error_log_lines;
					}
				}
			} catch ( error ) {
				/* eslint-disable-next-line no-console */
				console.error( 'WP System Report: Failed to load status', error );
				actions.showToggleNotice(
					error.message || state.i18n.loadFailed,
					'error'
				);
			}
		},

		/**
		 * Load the error log content.
		 */
		*loadLog() {
			state.errorLog.isLoading = true;

			const lines = parseInt( state.errorLog.lines, 10 ) || 100;
			const sep = state.config.logUrl.indexOf( '?' ) === -1 ? '?' : '&';

			try {
				const response = yield fetch(
					state.config.logUrl + sep + 'lines=' + lines + '&format=json',
					{
						headers: { 'X-WP-Nonce': state.config.restNonce },
						credentials: 'same-origin',
					}
				);

				if ( ! response.ok ) {
					const err = yield response.json().catch( () => ( {} ) );
					throw new Error( err.message || state.i18n.loadFailed );
				}

				const data = yield response.json();
				state.errorLog.logLoaded = true;

				if ( data.lines && data.lines.length > 0 ) {
					state.errorLog.logContent = data.lines.join( '\n' );
					state.errorLog.hasLines = true;
				} else {
					state.errorLog.logContent = '';
					state.errorLog.hasLines = false;
				}

				if ( data.file ) {
					state.errorLog.fileSize = data.file.size_formatted;
				}
			} catch ( error ) {
				actions.showToggleNotice(
					error.message || state.i18n.loadFailed,
					'error'
				);
			} finally {
				state.errorLog.isLoading = false;
			}
		},

		/**
		 * Refresh the log and status.
		 */
		*refreshLog() {
			yield actions.loadLog();
			yield actions.loadStatus();
		},

		/**
		 * Enable debug logging.
		 */
		*enableDebug() {
			yield actions.toggleDebug( true );
		},

		/**
		 * Disable debug logging.
		 */
		*disableDebug() {
			yield actions.toggleDebug( false );
		},

		/**
		 * Toggle debug logging on or off.
		 *
		 * @param {boolean} enable Whether to enable.
		 */
		*toggleDebug( enable ) {
			state.errorLog.isToggling = true;

			try {
				const response = yield fetch( state.config.toggleUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': state.config.restNonce,
					},
					credentials: 'same-origin',
					body: JSON.stringify( { enable } ),
				} );

				if ( ! response.ok ) {
					const err = yield response.json();
					throw new Error( err.message || state.i18n.toggleFailed );
				}

				const data = yield response.json();
				actions.showToggleNotice( state.i18n.toggleSuccess, 'success' );

				if ( data.state ) {
					state.errorLog.wpDebug = data.state.wp_debug ?? null;
					state.errorLog.wpDebugLog = data.state.wp_debug_log ?? null;
					state.errorLog.wpDebugDisplay =
						data.state.wp_debug_display ?? null;
				}
			} catch ( error ) {
				actions.showToggleNotice(
					error.message || state.i18n.toggleFailed,
					'error'
				);
			} finally {
				state.errorLog.isToggling = false;
			}
		},

		/**
		 * Update the lines input value.
		 */
		updateLines() {
			const { ref } = getElement();
			let val = parseInt( ref.value, 10 );
			if ( isNaN( val ) || val < 1 ) {
				val = 1;
			} else if ( val > 10000 ) {
				val = 10000;
			}
			state.errorLog.lines = val;
			ref.value = val;
		},

		/**
		 * Download the error log content.
		 */
		*downloadLog() {
			const { ref } = getElement();
			const logContent = state.errorLog.logContent;
			const includeReport = state.errorLog.includeReport;
			const reportUrl = includeReport ? state.config.reportUrl : null;
			const headings = {
				report: state.i18n.reportHeading,
				log: state.i18n.errorLogHeading,
			};

			if ( reportUrl ) {
				ref.disabled = true;
				const origText = ref.textContent;
				ref.textContent = state.i18n.loading;

				try {
					const content = yield fetchCombinedContent(
						logContent,
						reportUrl,
						state.config.restNonce,
						headings
					);
					const isCombined = content !== logContent;
					downloadFile(
						content,
						buildFilename(
							isCombined ? 'SystemReport_ErrorLog' : 'ErrorLog',
							isCombined ? 'txt' : 'log'
						)
					);
				} finally {
					ref.disabled = false;
					ref.textContent = origText;
				}
			} else {
				downloadFile(
					logContent,
					buildFilename( 'ErrorLog', 'log' )
				);
			}
		},

		/**
		 * Copy the error log content.
		 */
		*copyLog() {
			const { ref } = getElement();
			state.copyError = false;

			const logContent = state.errorLog.logContent;
			const includeReport = state.errorLog.includeReport;
			const reportUrl = includeReport ? state.config.reportUrl : null;
			const headings = {
				report: state.i18n.reportHeading,
				log: state.i18n.errorLogHeading,
			};

			const onSuccess = () => showCopySuccess( ref, state.i18n.copied );
			const onError = () => {
				state.copyError = true;
			};

			if ( reportUrl ) {
				ref.disabled = true;
				const origText = ref.textContent;
				ref.textContent = state.i18n.loading;

				try {
					const content = yield fetchCombinedContent(
						logContent,
						reportUrl,
						state.config.restNonce,
						headings
					);
					copyToClipboard( content, onSuccess, onError );
				} finally {
					ref.disabled = false;
					ref.textContent = origText;
				}
			} else {
				copyToClipboard( logContent, onSuccess, onError );
			}
		},

		/**
		 * Toggle the include report checkbox.
		 */
		toggleIncludeReport() {
			const { ref } = getElement();
			state.errorLog.includeReport = ref.checked;
		},

		/**
		 * Show a toggle notice.
		 *
		 * @param {string} message The message.
		 * @param {string} type    The notice type.
		 */
		showToggleNotice( message, type ) {
			state.errorLog.noticeMessage = message;
			state.errorLog.noticeType = type;

			if ( type === 'success' ) {
				setTimeout( () => {
					state.errorLog.noticeMessage = '';
				}, 5000 );
			}
		},
	},

	callbacks: {
		/**
		 * Initialize the error log tab on mount.
		 */
		*initErrorLog() {
			yield actions.loadStatus();
		},
	},
} );
