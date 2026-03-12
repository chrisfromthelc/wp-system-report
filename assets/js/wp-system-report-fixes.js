/**
 * WP System Report - Fixes Tab JavaScript (Vanilla, no jQuery)
 *
 * @package SystemReport
 */
( function () {
	'use strict';

	var config = window.systemReportFixes || {};
	var pendingFixId = null;
	var lastFocusedElement = null;

	/**
	 * Fetch wrapper with nonce header.
	 *
	 * @param {string} url     Request URL.
	 * @param {Object} options Fetch options.
	 * @return {Promise} Fetch promise.
	 */
	function apiFetch( url, options ) {
		if ( ! config.fixesUrl || ! config.restNonce ) {
			return Promise.reject( new Error( config.i18n.loadFailed || 'Configuration error.' ) );
		}
		options = options || {};
		options.headers = options.headers || {};
		options.headers[ 'X-WP-Nonce' ] = config.restNonce;
		options.credentials = 'same-origin';
		return fetch( url, options );
	}

	/**
	 * Safely parse a JSON response, falling back to a text-based error.
	 *
	 * @param {Response} response Fetch response.
	 * @param {string}   fallback Fallback error message.
	 * @return {Promise} Rejects with an Error containing the parsed or fallback message.
	 */
	function rejectWithResponseError( response, fallback ) {
		return response.json()
			.then( function ( err ) {
				throw new Error( err.message || fallback );
			} )
			.catch( function ( parseErr ) {
				if ( parseErr instanceof SyntaxError ) {
					throw new Error( fallback );
				}
				throw parseErr;
			} );
	}

	/**
	 * Create a <span> element with a dashicon and text.
	 *
	 * @param {string} iconClass Dashicon class name (e.g. "dashicons-warning").
	 * @param {string} text      Text to display beside the icon.
	 * @return {DocumentFragment} Fragment containing the icon span and text node.
	 */
	function createIconText( iconClass, text ) {
		var fragment = document.createDocumentFragment();
		var icon = document.createElement( 'span' );
		icon.className = 'dashicons ' + iconClass;
		fragment.appendChild( icon );
		fragment.appendChild( document.createTextNode( ' ' + text ) );
		return fragment;
	}

	/**
	 * Create a label element with a <strong> prefix and text content.
	 *
	 * @param {string} label   The bold label text (will be escaped via textContent).
	 * @param {string} message The message text (will be escaped via textContent).
	 * @return {DocumentFragment} Fragment containing the strong element and text.
	 */
	function createStrongMessage( label, message ) {
		var fragment = document.createDocumentFragment();
		var strong = document.createElement( 'strong' );
		strong.textContent = label;
		fragment.appendChild( strong );
		fragment.appendChild( document.createTextNode( ' ' + message ) );
		return fragment;
	}

	/**
	 * Get the risk badge CSS class for a risk level.
	 *
	 * @param {string} riskLevel Risk level value.
	 * @return {string} CSS class name.
	 */
	function getRiskBadgeClass( riskLevel ) {
		switch ( riskLevel ) {
			case 'low':
				return 'sr-risk-low';
			case 'medium':
				return 'sr-risk-medium';
			case 'high':
				return 'sr-risk-high';
			default:
				return 'sr-risk-low';
		}
	}

	/**
	 * Get the translated risk label.
	 *
	 * @param {string} riskLevel Risk level value.
	 * @return {string} Translated label.
	 */
	function getRiskLabel( riskLevel ) {
		switch ( riskLevel ) {
			case 'low':
				return config.i18n.riskLow;
			case 'medium':
				return config.i18n.riskMedium;
			case 'high':
				return config.i18n.riskHigh;
			default:
				return riskLevel;
		}
	}

	/**
	 * Group fixers by category.
	 *
	 * @param {Array} fixers Array of fixer objects.
	 * @return {Object} Object keyed by category.
	 */
	function groupByCategory( fixers ) {
		var groups = {};
		fixers.forEach( function ( fixer ) {
			var cat = fixer.category || 'general';
			if ( ! groups[ cat ] ) {
				groups[ cat ] = [];
			}
			groups[ cat ].push( fixer );
		} );
		return groups;
	}

	/**
	 * Capitalize the first letter of a string.
	 *
	 * @param {string} str Input string.
	 * @return {string} Capitalized string.
	 */
	function capitalize( str ) {
		return str.charAt( 0 ).toUpperCase() + str.slice( 1 );
	}

	/**
	 * Format a category slug into a display label.
	 *
	 * @param {string} category Category slug.
	 * @return {string} Display label.
	 */
	function formatCategory( category ) {
		return category.split( '_' ).map( capitalize ).join( ' ' );
	}

	/**
	 * Render a single fixer card.
	 *
	 * @param {Object} fixer Fixer data object.
	 * @return {HTMLElement} The card element.
	 */
	function renderFixerCard( fixer ) {
		var card = document.createElement( 'div' );
		card.className = 'sr-fixer-card card';
		card.setAttribute( 'data-fix-id', fixer.id );

		// Header row: label + risk badge.
		var header = document.createElement( 'div' );
		header.className = 'sr-fixer-card-header';

		var title = document.createElement( 'h3' );
		title.className = 'sr-fixer-card-title';
		title.textContent = fixer.label;
		header.appendChild( title );

		var riskBadge = document.createElement( 'span' );
		riskBadge.className = 'sr-badge sr-risk-badge ' + getRiskBadgeClass( fixer.risk_level );
		riskBadge.textContent = getRiskLabel( fixer.risk_level );
		header.appendChild( riskBadge );

		card.appendChild( header );

		// Description.
		var desc = document.createElement( 'p' );
		desc.className = 'sr-fixer-card-description';
		desc.textContent = fixer.description;
		card.appendChild( desc );

		// Status + action row.
		var actions = document.createElement( 'div' );
		actions.className = 'sr-fixer-card-actions';

		// Status indicator.
		var statusEl = document.createElement( 'span' );
		statusEl.className = 'sr-fixer-status';
		if ( fixer.can_fix ) {
			statusEl.appendChild( createIconText( 'dashicons-warning', config.i18n.issuesDetected ) );
			statusEl.classList.add( 'sr-fixer-status-warning' );
		} else {
			statusEl.appendChild( createIconText( 'dashicons-yes-alt', config.i18n.noIssues ) );
			statusEl.classList.add( 'sr-fixer-status-good' );
		}
		actions.appendChild( statusEl );

		// Run button.
		var runBtn = document.createElement( 'button' );
		runBtn.type = 'button';
		runBtn.className = 'button button-primary sr-run-fix-btn';
		runBtn.textContent = config.i18n.runFix;
		runBtn.setAttribute( 'data-fix-id', fixer.id );
		runBtn.disabled = ! fixer.can_fix;

		if ( fixer.can_fix ) {
			runBtn.addEventListener( 'click', function () {
				handleRunFix( fixer );
			} );
		}

		actions.appendChild( runBtn );
		card.appendChild( actions );

		// Result area (hidden by default).
		var resultArea = document.createElement( 'div' );
		resultArea.className = 'sr-fixer-result';
		resultArea.id = 'sr-result-' + fixer.id;
		resultArea.style.display = 'none';
		card.appendChild( resultArea );

		return card;
	}

	/**
	 * Handle the "Run Fix" button click.
	 *
	 * @param {Object} fixer The fixer data object.
	 */
	function handleRunFix( fixer ) {
		if ( fixer.requires_confirmation ) {
			showConfirmModal( fixer );
		} else {
			executeFix( fixer.id );
		}
	}

	/**
	 * Show the confirmation modal.
	 *
	 * @param {Object} fixer The fixer data object.
	 */
	function showConfirmModal( fixer ) {
		pendingFixId = fixer.id;
		lastFocusedElement = document.activeElement;

		var modal = document.getElementById( 'sr-confirm-modal' );
		var titleEl = document.getElementById( 'sr-confirm-modal-title' );
		var messageEl = document.getElementById( 'sr-confirm-modal-message' );
		var descEl = document.getElementById( 'sr-confirm-modal-description' );

		if ( titleEl ) {
			titleEl.textContent = config.i18n.confirmTitle + ': ' + fixer.label;
		}
		if ( messageEl ) {
			messageEl.textContent = config.i18n.confirmMessage;
		}
		if ( descEl ) {
			descEl.textContent = fixer.description;
		}
		if ( modal ) {
			modal.style.display = 'block';
			// Move focus into the dialog.
			var confirmBtn = document.getElementById( 'sr-confirm-run' );
			if ( confirmBtn ) {
				confirmBtn.focus();
			} else {
				modal.focus();
			}
		}
	}

	/**
	 * Hide the confirmation modal and restore focus.
	 */
	function hideConfirmModal() {
		pendingFixId = null;
		var modal = document.getElementById( 'sr-confirm-modal' );
		if ( modal ) {
			modal.style.display = 'none';
		}
		// Restore focus to the element that opened the modal.
		if ( lastFocusedElement && typeof lastFocusedElement.focus === 'function' ) {
			lastFocusedElement.focus();
		}
		lastFocusedElement = null;
	}

	/**
	 * Execute a fixer via the REST API.
	 *
	 * @param {string} fixId The fixer ID to execute.
	 */
	function executeFix( fixId ) {
		hideConfirmModal();

		var card = document.querySelector( '[data-fix-id="' + fixId + '"].sr-fixer-card' );
		var btn = card ? card.querySelector( '.sr-run-fix-btn' ) : null;
		var resultArea = document.getElementById( 'sr-result-' + fixId );
		var succeeded = false;

		if ( btn ) {
			btn.disabled = true;
			btn.textContent = config.i18n.running;
		}

		// Clear previous result.
		if ( resultArea ) {
			resultArea.style.display = 'none';
			resultArea.textContent = '';
		}

		apiFetch( config.fixesUrl + '/' + fixId, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return rejectWithResponseError( response, config.i18n.executeFailed );
				}
				return response.json();
			} )
			.then( function ( envelope ) {
				var data = envelope.data || {};
				var result = data.result || {};
				succeeded = true;

				showResult( fixId, result, data.applied );

				// Refresh the status indicator.
				if ( card ) {
					var statusEl = card.querySelector( '.sr-fixer-status' );
					if ( statusEl ) {
						statusEl.textContent = '';
						statusEl.appendChild( createIconText( 'dashicons-yes-alt', config.i18n.noIssues ) );
						statusEl.className = 'sr-fixer-status sr-fixer-status-good';
					}
				}
			} )
			.catch( function ( error ) {
				showResult( fixId, {
					success: false,
					message: error.message || config.i18n.executeFailed,
				}, false );
			} )
			.finally( function () {
				if ( btn ) {
					btn.textContent = config.i18n.runFix;
					// Keep disabled only on success; re-enable on failure for retry.
					btn.disabled = succeeded;
				}
			} );
	}

	/**
	 * Show the result of a fixer execution.
	 *
	 * @param {string}  fixId   The fixer ID.
	 * @param {Object}  result  The result object.
	 * @param {boolean} applied Whether the fix was applied.
	 */
	function showResult( fixId, result, applied ) {
		var resultArea = document.getElementById( 'sr-result-' + fixId );
		if ( ! resultArea ) {
			return;
		}

		resultArea.textContent = '';
		resultArea.style.display = 'block';

		// Status notice.
		var notice = document.createElement( 'div' );
		if ( result.success ) {
			notice.className = 'notice notice-success inline sr-result-notice';
		} else {
			notice.className = 'notice notice-error inline sr-result-notice';
		}

		var statusLabel = result.success ? config.i18n.success : config.i18n.failed;
		var p = document.createElement( 'p' );
		p.appendChild( createStrongMessage( statusLabel + ':', result.message || '' ) );
		notice.appendChild( p );
		resultArea.appendChild( notice );

		// If not applied (nothing to fix), mark it differently.
		if ( ! applied && result.success ) {
			notice.className = 'notice notice-info inline sr-result-notice';
			p.textContent = '';
			p.appendChild( createStrongMessage( config.i18n.nothingToFix + ':', result.message || '' ) );
		}

		// Before/After details (collapsible).
		if ( result.before || result.after ) {
			var details = document.createElement( 'details' );
			details.className = 'sr-result-details';

			var summary = document.createElement( 'summary' );
			summary.textContent = config.i18n.resultDetails;
			details.appendChild( summary );

			var detailsContent = document.createElement( 'div' );
			detailsContent.className = 'sr-result-details-content';

			if ( result.before ) {
				var beforeBlock = document.createElement( 'div' );
				beforeBlock.className = 'sr-result-snapshot';
				var beforeLabel = document.createElement( 'strong' );
				beforeLabel.textContent = config.i18n.before + ':';
				beforeBlock.appendChild( beforeLabel );
				var beforePre = document.createElement( 'pre' );
				beforePre.textContent = JSON.stringify( result.before, null, 2 );
				beforeBlock.appendChild( beforePre );
				detailsContent.appendChild( beforeBlock );
			}

			if ( result.after ) {
				var afterBlock = document.createElement( 'div' );
				afterBlock.className = 'sr-result-snapshot';
				var afterLabel = document.createElement( 'strong' );
				afterLabel.textContent = config.i18n.after + ':';
				afterBlock.appendChild( afterLabel );
				var afterPre = document.createElement( 'pre' );
				afterPre.textContent = JSON.stringify( result.after, null, 2 );
				afterBlock.appendChild( afterPre );
				detailsContent.appendChild( afterBlock );
			}

			details.appendChild( detailsContent );
			resultArea.appendChild( details );
		}
	}

	/**
	 * Load and render all fixers.
	 */
	function loadFixes() {
		var loadingEl = document.getElementById( 'sr-fixes-loading' );
		var errorEl = document.getElementById( 'sr-fixes-error' );
		var emptyEl = document.getElementById( 'sr-fixes-empty' );
		var listEl = document.getElementById( 'sr-fixes-list' );

		apiFetch( config.fixesUrl )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return rejectWithResponseError( response, config.i18n.loadFailed );
				}
				return response.json();
			} )
			.then( function ( envelope ) {
				if ( loadingEl ) {
					loadingEl.style.display = 'none';
				}

				var fixers = ( envelope.data && envelope.data.fixes ) ? envelope.data.fixes : [];

				if ( fixers.length === 0 ) {
					if ( emptyEl ) {
						emptyEl.style.display = 'block';
					}
					return;
				}

				if ( listEl ) {
					listEl.style.display = 'block';
					listEl.textContent = '';
				}

				// Group by category and render.
				var groups = groupByCategory( fixers );
				var categoryOrder = Object.keys( groups ).sort();

				categoryOrder.forEach( function ( category ) {
					var section = document.createElement( 'div' );
					section.className = 'sr-fixes-category';

					var heading = document.createElement( 'h2' );
					heading.className = 'sr-fixes-category-heading';
					heading.textContent = formatCategory( category );
					section.appendChild( heading );

					groups[ category ].forEach( function ( fixer ) {
						section.appendChild( renderFixerCard( fixer ) );
					} );

					listEl.appendChild( section );
				} );
			} )
			.catch( function ( error ) {
				if ( loadingEl ) {
					loadingEl.style.display = 'none';
				}
				if ( errorEl ) {
					errorEl.style.display = 'block';
					var msgEl = document.getElementById( 'sr-fixes-error-message' );
					if ( msgEl ) {
						msgEl.textContent = error.message || config.i18n.loadFailed;
					}
				}
			} );
	}

	/**
	 * Initialize event listeners.
	 */
	function init() {
		loadFixes();

		// Confirmation modal: confirm button.
		var confirmBtn = document.getElementById( 'sr-confirm-run' );
		if ( confirmBtn ) {
			confirmBtn.addEventListener( 'click', function () {
				if ( pendingFixId ) {
					executeFix( pendingFixId );
				}
			} );
		}

		// Confirmation modal: cancel button.
		var cancelBtn = document.getElementById( 'sr-confirm-cancel' );
		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', hideConfirmModal );
		}

		// Confirmation modal: backdrop click.
		var backdrop = document.querySelector( '.sr-confirm-modal-backdrop' );
		if ( backdrop ) {
			backdrop.addEventListener( 'click', hideConfirmModal );
		}

		// Modal keyboard handling: Escape closes, Tab traps focus.
		var modal = document.getElementById( 'sr-confirm-modal' );
		if ( modal ) {
			modal.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					hideConfirmModal();
					return;
				}
				if ( e.key === 'Tab' ) {
					var focusable = modal.querySelectorAll(
						'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
					);
					if ( ! focusable.length ) {
						return;
					}
					var first = focusable[ 0 ];
					var last = focusable[ focusable.length - 1 ];
					if ( e.shiftKey && document.activeElement === first ) {
						e.preventDefault();
						last.focus();
					} else if ( ! e.shiftKey && document.activeElement === last ) {
						e.preventDefault();
						first.focus();
					}
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
