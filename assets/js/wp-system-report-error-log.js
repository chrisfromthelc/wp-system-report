/**
 * WP System Report - Error Log Tab JavaScript (Vanilla, no jQuery)
 *
 * @package SystemReport
 */
( function () {
	'use strict';

	var config = window.systemReportErrorLog || {};
	var logLoaded = false;

	/**
	 * Fetch wrapper with nonce header.
	 *
	 * @param {string} url    Request URL.
	 * @param {Object} options Fetch options.
	 * @return {Promise} Fetch promise.
	 */
	function apiFetch( url, options ) {
		options = options || {};
		options.headers = options.headers || {};
		options.headers[ 'X-WP-Nonce' ] = config.restNonce;
		options.credentials = 'same-origin';
		return fetch( url, options );
	}

	/**
	 * Copy text to clipboard.
	 *
	 * @param {string}      text   Text to copy.
	 * @param {HTMLElement}  button Button element for feedback.
	 */
	function copyToClipboard( text, button ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then(
				function () {
					showCopySuccess( button );
				},
				function () {
					showCopyError();
				}
			);
		} else {
			var textarea = document.createElement( 'textarea' );
			textarea.value = text;
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild( textarea );
			textarea.select();

			try {
				document.execCommand( 'copy' );
				showCopySuccess( button );
			} catch ( e ) {
				showCopyError();
			}

			document.body.removeChild( textarea );
		}
	}

	/**
	 * Show copy success indicator.
	 *
	 * @param {HTMLElement} button The button element.
	 */
	function showCopySuccess( button ) {
		var indicator = button.nextElementSibling;
		if ( ! indicator || ! indicator.classList.contains( 'sr-copy-success' ) ) {
			indicator = document.createElement( 'span' );
			indicator.className = 'sr-copy-success';
			indicator.textContent = config.i18n.copied;
			button.parentNode.insertBefore( indicator, button.nextSibling );
		}

		indicator.classList.add( 'visible' );
		setTimeout( function () {
			indicator.classList.remove( 'visible' );
		}, 2000 );
	}

	/**
	 * Show copy error message.
	 */
	function showCopyError() {
		var errorEl = document.querySelector( '.sr-copy-error' );
		if ( errorEl ) {
			errorEl.style.display = 'block';
		}
	}

	/**
	 * Download text as a file.
	 *
	 * @param {string} content  File content.
	 * @param {string} filename File name.
	 */
	function downloadFile( content, filename ) {
		var blob = new Blob( [ content ], { type: 'text/plain' } );
		var a = document.createElement( 'a' );
		a.download = filename;
		a.href = window.URL.createObjectURL( blob );
		a.style.display = 'none';
		document.body.appendChild( a );
		a.click();
		a.remove();
		window.URL.revokeObjectURL( a.href );
	}

	/**
	 * Build a filename with domain and timestamp.
	 *
	 * @param {string} prefix File prefix.
	 * @param {string} ext    File extension.
	 * @return {string} Full filename.
	 */
	function buildFilename( prefix, ext ) {
		var domain = window.location.hostname;
		var datetime = new Date().toISOString().slice( 0, 19 ).replace( /:/g, '-' );
		return prefix + '_' + domain + '_' + datetime + '.' + ext;
	}

	/**
	 * Update a badge element with a value and style.
	 *
	 * @param {string}      id    Element ID.
	 * @param {*}           value The value (true, false, null, string).
	 * @param {boolean}     invertColor Invert color (true=bad for some constants).
	 */
	function updateBadge( id, value, invertColor ) {
		var badge = document.getElementById( id );
		if ( ! badge ) {
			return;
		}

		badge.classList.remove( 'sr-badge-on', 'sr-badge-off', 'sr-badge-null' );

		if ( null === value || typeof value === 'undefined' ) {
			badge.textContent = config.i18n.notSet;
			badge.classList.add( 'sr-badge-null' );
		} else if ( typeof value === 'string' ) {
			badge.textContent = value;
			badge.classList.add( 'sr-badge-on' );
		} else if ( value ) {
			badge.textContent = config.i18n.enabled;
			badge.classList.add( invertColor ? 'sr-badge-off' : 'sr-badge-on' );
		} else {
			badge.textContent = config.i18n.disabled;
			badge.classList.add( invertColor ? 'sr-badge-on' : 'sr-badge-off' );
		}
	}

	/**
	 * Show a notice message.
	 *
	 * @param {string} message Notice text.
	 * @param {string} type    Notice type: 'success', 'error', 'info'.
	 */
	function showNotice( message, type ) {
		var container = document.getElementById( 'sr-toggle-notice' );
		if ( ! container ) {
			return;
		}

		container.innerHTML = '';
		container.style.display = 'block';

		var notice = document.createElement( 'div' );
		notice.className = 'notice notice-' + type + ' inline';
		var p = document.createElement( 'p' );
		p.textContent = message;
		notice.appendChild( p );
		container.appendChild( notice );

		if ( 'success' === type ) {
			setTimeout( function () {
				container.style.display = 'none';
			}, 5000 );
		}
	}

	/**
	 * Load the debug status from the API.
	 */
	function loadStatus() {
		apiFetch( config.statusUrl )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Status request failed' );
				}
				return response.json();
			} )
			.then( function ( data ) {
				var statusEl = document.getElementById( 'sr-debug-status' );
				var controlsEl = document.getElementById( 'sr-debug-controls' );

				if ( statusEl ) {
					statusEl.style.display = 'none';
				}
				if ( controlsEl ) {
					controlsEl.style.display = 'block';
				}

				// Update badges from toggle state (wp-config.php values).
				var toggle = data.toggle || {};
				updateBadge( 'sr-wp-debug-badge', toggle.wp_debug, false );
				updateBadge( 'sr-wp-debug-log-badge', toggle.wp_debug_log, false );
				// WP_DEBUG_DISPLAY true = bad (shows errors to visitors).
				updateBadge( 'sr-wp-debug-display-badge', toggle.wp_debug_display, true );

				// Show toggle buttons or read-only notice.
				var toggleActions = document.getElementById( 'sr-toggle-actions' );
				var readonlyNotice = document.getElementById( 'sr-readonly-notice' );

				if ( toggle.can_modify ) {
					if ( toggleActions ) {
						toggleActions.style.display = 'block';
					}
					if ( readonlyNotice ) {
						readonlyNotice.style.display = 'none';
					}
				} else {
					if ( toggleActions ) {
						toggleActions.style.display = 'none';
					}
					if ( readonlyNotice ) {
						readonlyNotice.style.display = 'block';
					}
				}

				// Update file info.
				var file = data.file || {};
				var fileInfoEl = document.getElementById( 'sr-log-file-info' );
				var pathEl = document.getElementById( 'sr-log-path' );
				var sizeEl = document.getElementById( 'sr-log-size' );

				if ( file.exists && file.path ) {
					if ( fileInfoEl ) {
						fileInfoEl.style.display = 'block';
					}
					if ( pathEl ) {
						pathEl.textContent = file.path;
					}
					if ( sizeEl ) {
						sizeEl.textContent = file.size_formatted;
					}
				}

				// Update line count from settings only on initial load.
				if ( ! logLoaded ) {
					var settings = data.settings || {};
					var linesInput = document.getElementById( 'sr-log-lines' );
					if ( linesInput && settings.error_log_lines ) {
						linesInput.value = settings.error_log_lines;
					}
				}
			} )
			.catch( function ( error ) {
				/* eslint-disable no-console */
				console.error( 'WP System Report: Failed to load status', error );
				/* eslint-enable no-console */
			} );
	}

	/**
	 * Load the error log content.
	 */
	function loadLog() {
		var linesInput = document.getElementById( 'sr-log-lines' );
		var lines = linesInput ? parseInt( linesInput.value, 10 ) || 100 : 100;
		var loadBtn = document.getElementById( 'sr-load-log' );
		var refreshBtn = document.getElementById( 'sr-refresh-log' );
		var downloadBtn = document.getElementById( 'sr-download-log' );
		var copyBtn = document.getElementById( 'sr-copy-log' );
		var outputEl = document.getElementById( 'sr-log-output' );
		var contentEl = document.getElementById( 'sr-log-content' );
		var emptyEl = document.getElementById( 'sr-log-empty' );

		if ( loadBtn ) {
			loadBtn.disabled = true;
			loadBtn.textContent = config.i18n.loading;
		}

		var separator = config.logUrl.indexOf( '?' ) === -1 ? '?' : '&';
		apiFetch( config.logUrl + separator + 'lines=' + lines + '&format=json' )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return response.json().then( function ( err ) {
						throw new Error( err.message || config.i18n.loadFailed );
					} );
				}
				return response.json();
			} )
			.then( function ( data ) {
				logLoaded = true;

				if ( data.lines && data.lines.length > 0 ) {
					if ( contentEl ) {
						contentEl.textContent = data.lines.join( '\n' );
					}
					if ( outputEl ) {
						outputEl.style.display = 'block';
					}
					if ( emptyEl ) {
						emptyEl.style.display = 'none';
					}
					if ( downloadBtn ) {
						downloadBtn.style.display = '';
					}
					if ( copyBtn ) {
						copyBtn.style.display = '';
					}
					var includeLabel = document.getElementById( 'sr-include-report-label' );
					if ( includeLabel ) {
						includeLabel.style.display = '';
					}
				} else {
					if ( outputEl ) {
						outputEl.style.display = 'none';
					}
					if ( emptyEl ) {
						emptyEl.style.display = 'block';
					}
				}

				// Update file info.
				if ( data.file ) {
					var sizeEl = document.getElementById( 'sr-log-size' );
					if ( sizeEl ) {
						sizeEl.textContent = data.file.size_formatted;
					}
				}

				// Show refresh button, update load button.
				if ( loadBtn ) {
					loadBtn.style.display = 'none';
				}
				if ( refreshBtn ) {
					refreshBtn.style.display = '';
				}
			} )
			.catch( function ( error ) {
				showNotice( error.message || config.i18n.loadFailed, 'error' );
			} )
			.finally( function () {
				if ( loadBtn ) {
					loadBtn.disabled = false;
					loadBtn.textContent = config.i18n.loadLog;
				}
			} );
	}

	/**
	 * Toggle debug logging on or off.
	 *
	 * @param {boolean} enable Whether to enable or disable.
	 */
	function toggleDebug( enable ) {
		var enableBtn = document.getElementById( 'sr-enable-debug' );
		var disableBtn = document.getElementById( 'sr-disable-debug' );

		if ( enableBtn ) {
			enableBtn.disabled = true;
		}
		if ( disableBtn ) {
			disableBtn.disabled = true;
		}

		apiFetch( config.toggleUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( { enable: enable } ),
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return response.json().then( function ( err ) {
						throw new Error( err.message || config.i18n.toggleFailed );
					} );
				}
				return response.json();
			} )
			.then( function ( data ) {
				showNotice( config.i18n.toggleSuccess, 'success' );

				// Refresh the status display.
				if ( data.state ) {
					updateBadge( 'sr-wp-debug-badge', data.state.wp_debug, false );
					updateBadge( 'sr-wp-debug-log-badge', data.state.wp_debug_log, false );
					updateBadge( 'sr-wp-debug-display-badge', data.state.wp_debug_display, true );
				}
			} )
			.catch( function ( error ) {
				showNotice( error.message || config.i18n.toggleFailed, 'error' );
			} )
			.finally( function () {
				if ( enableBtn ) {
					enableBtn.disabled = false;
				}
				if ( disableBtn ) {
					disableBtn.disabled = false;
				}
			} );
	}

	/**
	 * Initialize event listeners.
	 */
	function init() {
		// Load status on page load.
		loadStatus();

		// Load log button.
		var loadBtn = document.getElementById( 'sr-load-log' );
		if ( loadBtn ) {
			loadBtn.addEventListener( 'click', loadLog );
		}

		// Refresh button.
		var refreshBtn = document.getElementById( 'sr-refresh-log' );
		if ( refreshBtn ) {
			refreshBtn.addEventListener( 'click', function () {
				loadLog();
				loadStatus();
			} );
		}

		// Download button.
		var downloadBtn = document.getElementById( 'sr-download-log' );
		if ( downloadBtn ) {
			downloadBtn.addEventListener( 'click', function () {
				var contentEl = document.getElementById( 'sr-log-content' );
				if ( ! contentEl ) {
					return;
				}

				var logContent = contentEl.textContent;
				var includeReport = document.getElementById( 'sr-include-report' );

				if ( includeReport && includeReport.checked && config.reportUrl ) {
					downloadBtn.disabled = true;
					downloadBtn.textContent = config.i18n.loading;

					var sep = config.reportUrl.indexOf( '?' ) === -1 ? '?' : '&';
					apiFetch( config.reportUrl + sep + 'format=plain' )
						.then( function ( response ) {
							if ( ! response.ok ) {
								throw new Error( 'Failed to fetch system report' );
							}
							return response.text();
						} )
						.then( function ( reportText ) {
							var combined = '===================================\n' +
								'WP SYSTEM REPORT\n' +
								'===================================\n\n' +
								reportText + '\n\n' +
								'===================================\n' +
								'ERROR LOG\n' +
								'===================================\n\n' +
								logContent;
							downloadFile( combined, buildFilename( 'SystemReport_ErrorLog', 'txt' ) );
						} )
						.catch( function () {
							// Fall back to error log only on failure.
							downloadFile( logContent, buildFilename( 'ErrorLog', 'log' ) );
						} )
						.finally( function () {
							downloadBtn.disabled = false;
							downloadBtn.textContent = config.i18n.download;
						} );
				} else {
					downloadFile( logContent, buildFilename( 'ErrorLog', 'log' ) );
				}
			} );
		}

		// Copy button.
		var copyBtn = document.getElementById( 'sr-copy-log' );
		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', function () {
				var contentEl = document.getElementById( 'sr-log-content' );
				if ( ! contentEl ) {
					return;
				}

				var logContent = contentEl.textContent;
				var includeReport = document.getElementById( 'sr-include-report' );

				if ( includeReport && includeReport.checked && config.reportUrl ) {
					copyBtn.disabled = true;
					copyBtn.textContent = config.i18n.loading;

					var sep = config.reportUrl.indexOf( '?' ) === -1 ? '?' : '&';
					apiFetch( config.reportUrl + sep + 'format=plain' )
						.then( function ( response ) {
							if ( ! response.ok ) {
								throw new Error( 'Failed to fetch system report' );
							}
							return response.text();
						} )
						.then( function ( reportText ) {
							var combined = '===================================\n' +
								'WP SYSTEM REPORT\n' +
								'===================================\n\n' +
								reportText + '\n\n' +
								'===================================\n' +
								'ERROR LOG\n' +
								'===================================\n\n' +
								logContent;
							copyToClipboard( combined, copyBtn );
						} )
						.catch( function () {
							// Fall back to error log only on failure.
							copyToClipboard( logContent, copyBtn );
						} )
						.finally( function () {
							copyBtn.disabled = false;
							copyBtn.textContent = config.i18n.copyClipboard;
						} );
				} else {
					copyToClipboard( logContent, copyBtn );
				}
			} );
		}

		// Enable debug button.
		var enableBtn = document.getElementById( 'sr-enable-debug' );
		if ( enableBtn ) {
			enableBtn.addEventListener( 'click', function () {
				toggleDebug( true );
			} );
		}

		// Disable debug button.
		var disableBtn = document.getElementById( 'sr-disable-debug' );
		if ( disableBtn ) {
			disableBtn.addEventListener( 'click', function () {
				toggleDebug( false );
			} );
		}

		// Lines input — validate on blur.
		var linesInput = document.getElementById( 'sr-log-lines' );
		if ( linesInput ) {
			linesInput.addEventListener( 'blur', function () {
				var val = parseInt( this.value, 10 );
				if ( isNaN( val ) || val < 1 ) {
					this.value = 1;
				} else if ( val > 10000 ) {
					this.value = 10000;
				}
			} );
		}
	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
