/**
 * WP System Report - Report Tab Interactivity Store.
 *
 * @package SystemReport
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

const { state, actions } = store( 'wp-system-report', {
	state: {
		get reportBtnHidden() {
			return state.reportGenerated;
		},
		get debugReportVisible() {
			return state.reportGenerated;
		},
		get aiDownloadLabel() {
			return state.aiGenerating
				? ( state.i18n.generating || 'Generating...' )
				: ( state.i18n.downloadAi || 'Download for AI analysis' );
		},
		get copyErrorVisible() {
			return state.copyError;
		},
	},
	actions: {
		/**
		 * Generate the plain-text report by scraping DOM tables.
		 */
		generateReport() {
			let report = '';
			const elements = document.querySelectorAll(
				'.sr_status_table thead, .sr_status_table tbody'
			);

			elements.forEach( ( el ) => {
				if ( el.tagName === 'THEAD' ) {
					const th = el.querySelector( 'th' );
					const label = th
						? ( th.getAttribute( 'data-export-label' ) || th.textContent )
						: '';
					report += '\n### ' + label.trim() + ' ###\n\n';
				} else {
					el.querySelectorAll( 'tr' ).forEach( ( row ) => {
						const labelCell = row.querySelector( 'td:first-child' );
						if ( ! labelCell ) {
							return;
						}

						let fieldLabel =
							labelCell.getAttribute( 'data-export-label' ) ||
							labelCell.textContent;
						fieldLabel = fieldLabel.replace( /<[^>]*>/g, '' ).trim();

						const valueCell = row.querySelector( 'td:nth-child(3)' );
						if ( ! valueCell ) {
							return;
						}

						const clone = valueCell.cloneNode( true );
						clone.querySelectorAll( '.private' ).forEach( ( node ) =>
							node.remove()
						);
						clone.querySelectorAll( '.dashicons-yes' ).forEach( ( node ) =>
							node.replaceWith( '\u2714' )
						);
						clone
							.querySelectorAll( '.dashicons-no-alt, .dashicons-warning' )
							.forEach( ( node ) => node.replaceWith( '\u274C' ) );

						let value = clone.textContent.trim();
						const parts = value.split( ', ' );
						if ( parts.length > 1 ) {
							value = parts.join( '\n' );
						}
						report += fieldLabel + ': ' + value + '\n';
					} );
				}
			} );

			state.reportText = '`' + report + '`';
			state.reportGenerated = true;

			// Focus and select the textarea.
			requestAnimationFrame( () => {
				const textarea = document.querySelector(
					'#sr-debug-report textarea'
				);
				if ( textarea ) {
					textarea.value = state.reportText;
					textarea.focus();
					textarea.select();
				}
			} );
		},

		/**
		 * Copy the report for support.
		 */
		copyForSupport() {
			const { ref } = getElement();
			actions.copyToClipboard( state.reportText, ref );
		},

		/**
		 * Copy a redacted version for GitHub.
		 */
		copyForGitHub() {
			const { ref } = getElement();
			const redacted = actions.applyRedactions( state.reportText );
			const githubReport =
				'<details><summary>System Status Report</summary>\n\n``' +
				redacted +
				'``\n</details>';
			actions.copyToClipboard( githubReport, ref );
		},

		/**
		 * Download the report as a text file.
		 */
		downloadForSupport() {
			actions.downloadFile(
				state.reportText,
				actions.buildFilename( 'SystemStatusReport', 'txt' ),
				'text/plain'
			);
		},

		/**
		 * Download the AI-formatted report.
		 */
		*downloadForAi() {
			state.aiGenerating = true;

			try {
				const response = yield fetch(
					state.config.restUrl + '?format=ai',
					{
						method: 'GET',
						headers: { 'X-WP-Nonce': state.config.restNonce },
						credentials: 'same-origin',
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Network response was not ok' );
				}

				const data = yield response.text();
				actions.downloadFile(
					data,
					actions.buildFilename( 'WPSystemReport_AI', 'md' ),
					'text/markdown'
				);
			} catch ( error ) {
				/* eslint-disable-next-line no-console */
				console.error( 'WP System Report AI download failed:', error );
				/* eslint-disable-next-line no-alert */
				alert(
					state.i18n.aiFailed ||
						'Failed to generate AI report. Please try again.'
				);
			} finally {
				state.aiGenerating = false;
			}
		},

		/**
		 * Apply redactions for GitHub export.
		 *
		 * @param {string} text The raw report text.
		 * @return {string} Redacted report.
		 */
		applyRedactions( text ) {
			const redactions = [
				{ regex: /(Home URL:)[^\n]*/, replacement: '$1 [Redacted]' },
				{ regex: /(Site URL:)[^\n]*/, replacement: '$1 [Redacted]' },
				{
					regex: /(### Database ###\n)([\s\S]*?)(\n### |$)/,
					replacement: '$1\n[REDACTED]\n$3',
				},
			];

			redactions.forEach( ( r ) => {
				text = text.replace( r.regex, r.replacement );
			} );
			return text;
		},

		/**
		 * Copy text to clipboard and show feedback.
		 *
		 * @param {string}      text   Text to copy.
		 * @param {HTMLElement} button The button element.
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
		 * Show copy success indicator near a button.
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
		 * Download text content as a file.
		 *
		 * @param {string} content  File content.
		 * @param {string} filename File name.
		 * @param {string} mimeType MIME type.
		 */
		downloadFile( content, filename, mimeType ) {
			mimeType = mimeType || 'text/plain';
			const blob = new Blob( [ content ], { type: mimeType } );
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
		 * Build a filename with domain and timestamp.
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
} );
