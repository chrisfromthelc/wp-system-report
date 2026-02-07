/**
 * WP System Report - Admin JavaScript (Vanilla, no jQuery)
 *
 * @package SystemReport
 */
( function () {
	'use strict';

	/**
	 * Generate the plain-text WP System Report from the DOM tables.
	 *
	 * Scrapes all .sr_status_table elements, using data-export-label
	 * attributes for section headers and field labels.
	 *
	 * @return {string} Formatted report text.
	 */
	function generateReport() {
		var report = '';
		var tables = document.querySelectorAll( '.sr_status_table thead, .sr_status_table tbody' );

		tables.forEach( function ( el ) {
			if ( el.tagName === 'THEAD' ) {
				var th = el.querySelector( 'th' );
				var label = th ? ( th.getAttribute( 'data-export-label' ) || th.textContent ) : '';
				report += '\n### ' + label.trim() + ' ###\n\n';
			} else {
				var rows = el.querySelectorAll( 'tr' );
				rows.forEach( function ( row ) {
					var labelCell = row.querySelector( 'td:first-child' );
					if ( ! labelCell ) {
						return;
					}

					var fieldLabel = labelCell.getAttribute( 'data-export-label' ) || labelCell.textContent;
					fieldLabel = fieldLabel.replace( /<[^>]*>/g, '' ).trim();

					// Get value cell (third td).
					var valueCell = row.querySelector( 'td:nth-child(3)' );
					if ( ! valueCell ) {
						return;
					}

					// Clone to manipulate without affecting DOM.
					var clone = valueCell.cloneNode( true );

					// Remove private elements.
					clone.querySelectorAll( '.private' ).forEach( function ( el ) {
						el.remove();
					} );

					// Replace dashicons with unicode symbols.
					clone.querySelectorAll( '.dashicons-yes' ).forEach( function ( el ) {
						el.replaceWith( '\u2714' );
					} );
					clone.querySelectorAll( '.dashicons-no-alt, .dashicons-warning' ).forEach( function ( el ) {
						el.replaceWith( '\u274C' );
					} );

					var value = clone.textContent.trim();

					// Split comma-separated lists onto new lines.
					var parts = value.split( ', ' );
					if ( parts.length > 1 ) {
						value = parts.join( '\n' );
					}

					report += fieldLabel + ': ' + value + '\n';
				} );
			}
		} );

		return report;
	}

	/**
	 * Copy text to clipboard using the modern API with fallback.
	 *
	 * @param {string}      text     Text to copy.
	 * @param {HTMLElement}  button   Button element to show feedback on.
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
			// Fallback for older browsers.
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
	 * Show a "Copied!" indicator near the button.
	 *
	 * @param {HTMLElement} button The button element.
	 */
	function showCopySuccess( button ) {
		var indicator = button.nextElementSibling;
		if ( ! indicator || ! indicator.classList.contains( 'sr-copy-success' ) ) {
			indicator = document.createElement( 'span' );
			indicator.className = 'sr-copy-success';
			indicator.textContent = systemReportAdmin.i18n.copied;
			button.parentNode.insertBefore( indicator, button.nextSibling );
		}

		indicator.classList.add( 'visible' );
		setTimeout( function () {
			indicator.classList.remove( 'visible' );
		}, 2000 );
	}

	/**
	 * Show the copy error message.
	 */
	function showCopyError() {
		var errorEl = document.querySelector( '.sr-copy-error' );
		if ( errorEl ) {
			errorEl.style.display = 'block';
		}

		var textarea = document.querySelector( '#sr-debug-report textarea' );
		if ( textarea ) {
			textarea.focus();
			textarea.select();
		}
	}

	/**
	 * Apply redactions for GitHub export.
	 *
	 * @param {string} report The raw report text.
	 * @return {string} Redacted report.
	 */
	function applyRedactions( report ) {
		var redactions = [
			{
				regex: /(Home URL:)[^\n]*/,
				replacement: '$1 [Redacted]',
			},
			{
				regex: /(Site URL:)[^\n]*/,
				replacement: '$1 [Redacted]',
			},
			{
				regex: /(### Database ###\n)([\s\S]*?)(\n### |$)/,
				replacement: '$1\n[REDACTED]\n$3',
			},
		];

		/**
		 * Allow plugins to add custom redaction patterns via the localized data.
		 */
		if ( systemReportAdmin.redactions ) {
			redactions = redactions.concat( systemReportAdmin.redactions );
		}

		redactions.forEach( function ( redaction ) {
			report = report.replace( redaction.regex, redaction.replacement );
		} );

		return report;
	}

	/**
	 * Download text content as a file.
	 *
	 * @param {string} content  File content.
	 * @param {string} filename File name.
	 * @param {string} mimeType MIME type. Default 'text/plain'.
	 */
	function downloadFile( content, filename, mimeType ) {
		mimeType = mimeType || 'text/plain';
		var blob = new Blob( [ content ], { type: mimeType } );
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
	 * @param {string} prefix File name prefix.
	 * @param {string} ext    File extension.
	 * @return {string} Full filename.
	 */
	function buildFilename( prefix, ext ) {
		var domain = window.location.hostname;
		var datetime = new Date().toISOString().slice( 0, 19 ).replace( /:/g, '-' );
		return prefix + '_' + domain + '_' + datetime + '.' + ext;
	}

	/**
	 * Initialize event listeners.
	 */
	function init() {
		var generateBtn = document.getElementById( 'sr-generate-report' );
		var copyBtn = document.getElementById( 'sr-copy-support' );
		var copyGithubBtn = document.getElementById( 'sr-copy-github' );
		var downloadBtn = document.getElementById( 'sr-download-support' );
		var downloadAiBtn = document.getElementById( 'sr-download-ai' );
		var debugReport = document.getElementById( 'sr-debug-report' );

		if ( generateBtn ) {
			generateBtn.addEventListener( 'click', function () {
				var report = generateReport();
				var textarea = debugReport.querySelector( 'textarea' );
				textarea.value = '`' + report + '`';
				debugReport.style.display = 'block';
				textarea.focus();
				textarea.select();
				generateBtn.style.display = 'none';
			} );
		}

		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var textarea = debugReport.querySelector( 'textarea' );
				copyToClipboard( textarea.value, copyBtn );
			} );
		}

		if ( copyGithubBtn ) {
			copyGithubBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var textarea = debugReport.querySelector( 'textarea' );
				var redacted = applyRedactions( textarea.value );
				var githubReport =
					'<details><summary>System Status Report</summary>\n\n``' +
					redacted +
					'``\n</details>';
				copyToClipboard( githubReport, copyGithubBtn );
			} );
		}

		if ( downloadBtn ) {
			downloadBtn.addEventListener( 'click', function () {
				var textarea = debugReport.querySelector( 'textarea' );
				downloadFile(
					textarea.value,
					buildFilename( 'SystemStatusReport', 'txt' )
				);
			} );
		}

		if ( downloadAiBtn ) {
			downloadAiBtn.addEventListener( 'click', function () {
				downloadAiBtn.disabled = true;
				downloadAiBtn.textContent = systemReportAdmin.i18n.generating || 'Generating...';

				fetch( systemReportAdmin.restUrl + '?format=ai', {
					method: 'GET',
					headers: {
						'X-WP-Nonce': systemReportAdmin.restNonce,
					},
				} )
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( 'Network response was not ok' );
						}
						return response.text();
					} )
					.then( function ( data ) {
						downloadFile(
							data,
							buildFilename( 'WPSystemReport_AI', 'md' ),
							'text/markdown'
						);
					} )
					.catch( function ( error ) {
						/* eslint-disable no-console */
						console.error( 'WP System Report AI download failed:', error );
						/* eslint-enable no-console */
						/* eslint-disable no-alert */
						alert( systemReportAdmin.i18n.aiFailed || 'Failed to generate AI report. Please try again.' );
						/* eslint-enable no-alert */
					} )
					.finally( function () {
						downloadAiBtn.disabled = false;
						downloadAiBtn.textContent =
							systemReportAdmin.i18n.downloadAi || 'Download for AI analysis';
					} );
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
