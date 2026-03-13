/**
 * WP System Report - Report Tab Interactivity Store.
 *
 * @package SystemReport
 */

import { store, getElement } from '@wordpress/interactivity';
import {
	copyToClipboard,
	showCopySuccess,
	downloadFile,
	buildFilename,
} from './store-utils.js';

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
				? state.i18n.generating
				: state.i18n.downloadAi;
		},
		get copyErrorVisible() {
			return state.copyError;
		},
		get aiErrorVisible() {
			return !! state.aiError;
		},
	},
	actions: {
		/**
		 * Generate the plain-text report by scraping DOM tables.
		 *
		 * Stores plain text without markdown formatting; call sites
		 * apply formatting (backtick wrapping, code fences) as needed.
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

			state.reportText = report;
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
		 * Copy the report for support (backtick-wrapped for inline code).
		 */
		copyForSupport() {
			const { ref } = getElement();
			state.copyError = false;
			const text = '`' + state.reportText + '`';
			copyToClipboard(
				text,
				() => showCopySuccess( ref, state.i18n.copied ),
				() => {
					state.copyError = true;
				}
			);
		},

		/**
		 * Copy a redacted version for GitHub (fenced code block).
		 */
		copyForGitHub() {
			const { ref } = getElement();
			state.copyError = false;
			const redacted = actions.applyRedactions( state.reportText );
			const githubReport =
				'<details><summary>System Status Report</summary>\n\n```\n' +
				redacted +
				'\n```\n</details>';
			copyToClipboard(
				githubReport,
				() => showCopySuccess( ref, state.i18n.copied ),
				() => {
					state.copyError = true;
				}
			);
		},

		/**
		 * Download the report as a text file.
		 */
		downloadForSupport() {
			downloadFile(
				state.reportText,
				buildFilename( 'SystemStatusReport', 'txt' ),
				'text/plain'
			);
		},

		/**
		 * Download the AI-formatted report.
		 */
		*downloadForAi() {
			state.aiGenerating = true;
			state.aiError = '';

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
				downloadFile(
					data,
					buildFilename( 'WPSystemReport_AI', 'md' ),
					'text/markdown'
				);
			} catch ( error ) {
				/* eslint-disable-next-line no-console */
				console.error( 'WP System Report AI download failed:', error );
				state.aiError = error.message || state.i18n.aiFailed;
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
	},
} );
