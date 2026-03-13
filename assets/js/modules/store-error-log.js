/**
 * WP System Report - Error Log Tab Interactivity Store.
 *
 * @package SystemReport
 */

import { store, getElement } from '@wordpress/interactivity';

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
				? ( state.i18n.loading || 'Loading...' )
				: ( state.i18n.loadLog || 'Load error log' );
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
				return state.i18n.notSet || 'Not set';
			}
			if ( typeof value === 'string' ) {
				return value;
			}
			if ( value ) {
				return state.i18n.enabled || 'Enabled';
			}
			return state.i18n.disabled || 'Disabled';
		},

		/**
		 * Load the debug status on init.
		 */
		*initErrorLog() {
			yield actions.loadStatus();
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
					throw new Error( 'Status request failed' );
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
					const err = yield response.json();
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

			if ( includeReport && state.config.reportUrl ) {
				ref.disabled = true;
				const origText = ref.textContent;
				ref.textContent = state.i18n.loading || 'Loading...';

				try {
					const sep =
						state.config.reportUrl.indexOf( '?' ) === -1 ? '?' : '&';
					const response = yield fetch(
						state.config.reportUrl + sep + 'format=plain',
						{
							headers: { 'X-WP-Nonce': state.config.restNonce },
							credentials: 'same-origin',
						}
					);

					if ( ! response.ok ) {
						throw new Error( 'Failed to fetch system report' );
					}

					const reportText = yield response.text();
					const combined =
						'===================================\n' +
						'WP SYSTEM REPORT\n' +
						'===================================\n\n' +
						reportText +
						'\n\n' +
						'===================================\n' +
						'ERROR LOG\n' +
						'===================================\n\n' +
						logContent;
					actions.downloadFileHelper(
						combined,
						actions.buildFilename( 'SystemReport_ErrorLog', 'txt' )
					);
				} catch ( e ) {
					actions.downloadFileHelper(
						logContent,
						actions.buildFilename( 'ErrorLog', 'log' )
					);
				} finally {
					ref.disabled = false;
					ref.textContent = origText;
				}
			} else {
				actions.downloadFileHelper(
					logContent,
					actions.buildFilename( 'ErrorLog', 'log' )
				);
			}
		},

		/**
		 * Copy the error log content.
		 */
		*copyLog() {
			const { ref } = getElement();
			const logContent = state.errorLog.logContent;
			const includeReport = state.errorLog.includeReport;

			if ( includeReport && state.config.reportUrl ) {
				ref.disabled = true;
				const origText = ref.textContent;
				ref.textContent = state.i18n.loading || 'Loading...';

				try {
					const sep =
						state.config.reportUrl.indexOf( '?' ) === -1 ? '?' : '&';
					const response = yield fetch(
						state.config.reportUrl + sep + 'format=plain',
						{
							headers: { 'X-WP-Nonce': state.config.restNonce },
							credentials: 'same-origin',
						}
					);

					if ( ! response.ok ) {
						throw new Error( 'Failed to fetch system report' );
					}

					const reportText = yield response.text();
					const combined =
						'===================================\n' +
						'WP SYSTEM REPORT\n' +
						'===================================\n\n' +
						reportText +
						'\n\n' +
						'===================================\n' +
						'ERROR LOG\n' +
						'===================================\n\n' +
						logContent;
					actions.copyToClipboard( combined, ref );
				} catch ( e ) {
					actions.copyToClipboard( logContent, ref );
				} finally {
					ref.disabled = false;
					ref.textContent = origText;
				}
			} else {
				actions.copyToClipboard( logContent, ref );
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

		/**
		 * Copy text to clipboard.
		 *
		 * @param {string}      text   Text to copy.
		 * @param {HTMLElement} button Button element.
		 */
		copyToClipboard( text, button ) {
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then(
					() => actions.showCopySuccess( button ),
					() => {
						state.copyError = true;
					}
				);
			} else {
				const textarea = document.createElement( 'textarea' );
				textarea.value = text;
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				document.body.appendChild( textarea );
				textarea.select();
				try {
					document.execCommand( 'copy' );
					actions.showCopySuccess( button );
				} catch ( e ) {
					state.copyError = true;
				}
				document.body.removeChild( textarea );
			}
		},

		/**
		 * Show copy success indicator.
		 *
		 * @param {HTMLElement} button The button element.
		 */
		showCopySuccess( button ) {
			let indicator = button.nextElementSibling;
			if (
				! indicator ||
				! indicator.classList.contains( 'sr-copy-success' )
			) {
				indicator = document.createElement( 'span' );
				indicator.className = 'sr-copy-success';
				indicator.textContent = state.i18n.copied || 'Copied!';
				button.parentNode.insertBefore( indicator, button.nextSibling );
			}
			indicator.classList.add( 'visible' );
			setTimeout( () => indicator.classList.remove( 'visible' ), 2000 );
		},

		/**
		 * Download helper.
		 *
		 * @param {string} content  File content.
		 * @param {string} filename File name.
		 */
		downloadFileHelper( content, filename ) {
			const blob = new Blob( [ content ], { type: 'text/plain' } );
			const a = document.createElement( 'a' );
			a.download = filename;
			a.href = window.URL.createObjectURL( blob );
			a.style.display = 'none';
			document.body.appendChild( a );
			a.click();
			a.remove();
			window.URL.revokeObjectURL( a.href );
		},

		/**
		 * Build a filename.
		 *
		 * @param {string} prefix File prefix.
		 * @param {string} ext    File extension.
		 * @return {string} Full filename.
		 */
		buildFilename( prefix, ext ) {
			const domain = window.location.hostname;
			const datetime = new Date()
				.toISOString()
				.slice( 0, 19 )
				.replace( /:/g, '-' );
			return prefix + '_' + domain + '_' + datetime + '.' + ext;
		},
	},

	callbacks: {
		/**
		 * Initialize the error log tab on mount.
		 */
		initErrorLog() {
			actions.initErrorLog();
		},
	},
} );
