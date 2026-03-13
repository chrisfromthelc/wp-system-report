/**
 * WP System Report - Fixes Tab Interactivity Store.
 *
 * @package SystemReport
 */

import { store, getElement } from '@wordpress/interactivity';

const { state, actions } = store( 'wp-system-report', {
	state: {
		get fixesLoadingVisible() {
			return state.fixes.isLoading;
		},
		get fixesErrorVisible() {
			return state.fixes.hasError;
		},
		get fixesEmptyVisible() {
			return state.fixes.loaded && ! state.fixes.hasFixers;
		},
		get fixesListVisible() {
			return state.fixes.loaded && state.fixes.hasFixers;
		},
		get confirmModalVisible() {
			return state.fixes.modalOpen;
		},
	},

	actions: {
		/**
		 * Get the risk badge CSS class.
		 *
		 * @param {string} riskLevel Risk level value.
		 * @return {string} CSS class name.
		 */
		getRiskBadgeClass( riskLevel ) {
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
		},

		/**
		 * Get the translated risk label.
		 *
		 * @param {string} riskLevel Risk level value.
		 * @return {string} Translated label.
		 */
		getRiskLabel( riskLevel ) {
			switch ( riskLevel ) {
				case 'low':
					return state.i18n.riskLow || 'Low';
				case 'medium':
					return state.i18n.riskMedium || 'Medium';
				case 'high':
					return state.i18n.riskHigh || 'High';
				default:
					return riskLevel;
			}
		},

		/**
		 * Capitalize the first letter.
		 *
		 * @param {string} str Input string.
		 * @return {string} Capitalized string.
		 */
		capitalize( str ) {
			return str.charAt( 0 ).toUpperCase() + str.slice( 1 );
		},

		/**
		 * Format a category slug into a display label.
		 *
		 * @param {string} category Category slug.
		 * @return {string} Display label.
		 */
		formatCategory( category ) {
			return category
				.split( '_' )
				.map( ( s ) => actions.capitalize( s ) )
				.join( ' ' );
		},

		/**
		 * Group fixers by category and store sorted groups.
		 *
		 * @param {Array} fixers Array of fixer objects.
		 * @return {Array} Array of { category, label, fixers } objects.
		 */
		groupByCategory( fixers ) {
			const groups = {};
			fixers.forEach( ( fixer ) => {
				const cat = fixer.category || 'general';
				if ( ! groups[ cat ] ) {
					groups[ cat ] = [];
				}
				groups[ cat ].push( fixer );
			} );

			return Object.keys( groups )
				.sort()
				.map( ( cat ) => ( {
					category: cat,
					label: actions.formatCategory( cat ),
					fixers: groups[ cat ],
				} ) );
		},

		/**
		 * Initialize the fixes tab.
		 */
		*initFixes() {
			yield actions.loadFixes();
		},

		/**
		 * Load all fixers from the REST API.
		 */
		*loadFixes() {
			state.fixes.isLoading = true;
			state.fixes.hasError = false;
			state.fixes.errorMessage = '';

			try {
				const response = yield fetch( state.config.fixesUrl, {
					headers: { 'X-WP-Nonce': state.config.restNonce },
					credentials: 'same-origin',
				} );

				if ( ! response.ok ) {
					const err = yield response.json().catch( () => ( {} ) );
					throw new Error(
						err.message || state.i18n.loadFailed || 'Failed to load fixes.'
					);
				}

				const envelope = yield response.json();
				const fixers =
					envelope.data && envelope.data.fixes
						? envelope.data.fixes
						: [];

				if ( fixers.length === 0 ) {
					state.fixes.hasFixers = false;
				} else {
					state.fixes.hasFixers = true;
					state.fixes.categories = actions.groupByCategory( fixers );
				}

				state.fixes.loaded = true;
			} catch ( error ) {
				state.fixes.hasError = true;
				state.fixes.errorMessage =
					error.message || state.i18n.loadFailed || 'Failed to load fixes.';
			} finally {
				state.fixes.isLoading = false;
			}
		},

		/**
		 * Handle click on a fixer's Run button.
		 *
		 * Uses the context to determine which fixer was clicked.
		 */
		handleRunFix() {
			const { ref } = getElement();
			const fixId = ref.getAttribute( 'data-fix-id' );
			if ( ! fixId ) {
				return;
			}

			const fixer = actions.findFixer( fixId );
			if ( ! fixer ) {
				return;
			}

			if ( fixer.requires_confirmation ) {
				actions.showConfirmModal( fixer );
			} else {
				actions.executeFix( fixId );
			}
		},

		/**
		 * Find a fixer by ID in the current categories.
		 *
		 * @param {string} fixId Fixer ID.
		 * @return {Object|null} Fixer object or null.
		 */
		findFixer( fixId ) {
			const categories = state.fixes.categories || [];
			for ( const group of categories ) {
				for ( const fixer of group.fixers ) {
					if ( fixer.id === fixId ) {
						return fixer;
					}
				}
			}
			return null;
		},

		/**
		 * Show the confirmation modal for a fixer.
		 *
		 * @param {Object} fixer Fixer data.
		 */
		showConfirmModal( fixer ) {
			state.fixes.pendingFixId = fixer.id;
			state.fixes.modalTitle =
				( state.i18n.confirmTitle || 'Confirm' ) + ': ' + fixer.label;
			state.fixes.modalMessage =
				state.i18n.confirmMessage || 'Are you sure you want to run this fix?';
			state.fixes.modalDescription = fixer.description;
			state.fixes.modalOpen = true;
			state.fixes.lastFocusedSelector = document.activeElement
				? '[data-fix-id="' + fixer.id + '"].sr-run-fix-btn'
				: null;

			// Focus the confirm button after the modal renders.
			requestAnimationFrame( () => {
				const confirmBtn = document.getElementById( 'sr-confirm-run' );
				if ( confirmBtn ) {
					confirmBtn.focus();
				}
			} );
		},

		/**
		 * Hide the confirmation modal and restore focus.
		 */
		hideConfirmModal() {
			const selectorToRestore = state.fixes.lastFocusedSelector;

			state.fixes.pendingFixId = null;
			state.fixes.modalOpen = false;
			state.fixes.modalTitle = '';
			state.fixes.modalMessage = '';
			state.fixes.modalDescription = '';

			if ( selectorToRestore ) {
				requestAnimationFrame( () => {
					const el = document.querySelector( selectorToRestore );
					if ( el && typeof el.focus === 'function' ) {
						el.focus();
					}
				} );
			}
			state.fixes.lastFocusedSelector = null;
		},

		/**
		 * Confirm and run the pending fix.
		 */
		confirmAndRun() {
			const fixId = state.fixes.pendingFixId;
			if ( fixId ) {
				actions.executeFix( fixId );
			}
		},

		/**
		 * Handle modal keyboard events (Escape to close, Tab trap).
		 */
		handleModalKeydown() {
			const { ref } = getElement();
			const event = window.event;

			if ( event.key === 'Escape' ) {
				event.preventDefault();
				actions.hideConfirmModal();
				return;
			}

			if ( event.key === 'Tab' ) {
				const focusable = ref.querySelectorAll(
					'button:not([disabled]), [href], input:not([disabled]), ' +
					'select:not([disabled]), textarea:not([disabled]), ' +
					'[tabindex]:not([tabindex="-1"])'
				);
				if ( ! focusable.length ) {
					return;
				}
				const first = focusable[ 0 ];
				const last = focusable[ focusable.length - 1 ];
				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
		},

		/**
		 * Execute a fixer via the REST API.
		 *
		 * @param {string} fixId Fixer ID.
		 */
		*executeFix( fixId ) {
			actions.hideConfirmModal();

			// Mark the fixer as running.
			actions.updateFixerState( fixId, {
				isRunning: true,
				result: null,
			} );

			let succeeded = false;

			try {
				const response = yield fetch(
					state.config.fixesUrl + '/' + fixId,
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': state.config.restNonce,
						},
						credentials: 'same-origin',
					}
				);

				if ( ! response.ok ) {
					const err = yield response.json().catch( () => ( {} ) );
					throw new Error(
						err.message || state.i18n.executeFailed || 'Fix execution failed.'
					);
				}

				const envelope = yield response.json();
				const data = envelope.data || {};
				const result = data.result || {};
				succeeded = true;

				// Build result display data.
				let noticeClass = 'notice-success';
				let statusLabel = state.i18n.success || 'Success';

				if ( ! result.success ) {
					noticeClass = 'notice-error';
					statusLabel = state.i18n.failed || 'Failed';
					succeeded = false;
				} else if ( ! data.applied ) {
					noticeClass = 'notice-info';
					statusLabel = state.i18n.nothingToFix || 'Nothing to fix';
				}

				actions.updateFixerState( fixId, {
					isRunning: false,
					btnDisabled: succeeded,
					result: {
						visible: true,
						noticeClass: 'notice ' + noticeClass + ' inline sr-result-notice',
						statusLabel: statusLabel + ':',
						message: result.message || '',
						hasBefore: !! result.before,
						hasAfter: !! result.after,
						hasDetails: !! result.before || !! result.after,
						beforeJson: result.before
							? JSON.stringify( result.before, null, 2 )
							: '',
						afterJson: result.after
							? JSON.stringify( result.after, null, 2 )
							: '',
						detailsLabel: state.i18n.resultDetails || 'Details',
						beforeLabel: ( state.i18n.before || 'Before' ) + ':',
						afterLabel: ( state.i18n.after || 'After' ) + ':',
					},
				} );

				// Update status to "good" on success.
				if ( succeeded ) {
					actions.updateFixerStatus( fixId, false );
				}
			} catch ( error ) {
				actions.updateFixerState( fixId, {
					isRunning: false,
					btnDisabled: false,
					result: {
						visible: true,
						noticeClass: 'notice notice-error inline sr-result-notice',
						statusLabel: ( state.i18n.failed || 'Failed' ) + ':',
						message:
							error.message ||
							state.i18n.executeFailed ||
							'Fix execution failed.',
						hasDetails: false,
						hasBefore: false,
						hasAfter: false,
						beforeJson: '',
						afterJson: '',
						detailsLabel: '',
						beforeLabel: '',
						afterLabel: '',
					},
				} );
			}
		},

		/**
		 * Update a fixer's runtime state (running, result, etc.).
		 *
		 * @param {string} fixId Fixer ID.
		 * @param {Object} patch State properties to merge.
		 */
		updateFixerState( fixId, patch ) {
			const categories = state.fixes.categories || [];
			for ( const group of categories ) {
				for ( let i = 0; i < group.fixers.length; i++ ) {
					if ( group.fixers[ i ].id === fixId ) {
						group.fixers[ i ] = {
							...group.fixers[ i ],
							...patch,
						};
						return;
					}
				}
			}
		},

		/**
		 * Update a fixer's can_fix status after a successful fix.
		 *
		 * @param {string}  fixId  Fixer ID.
		 * @param {boolean} canFix New can_fix value.
		 */
		updateFixerStatus( fixId, canFix ) {
			actions.updateFixerState( fixId, { can_fix: canFix } );
		},
	},

	callbacks: {
		/**
		 * Initialize the fixes tab on mount.
		 */
		initFixes() {
			actions.initFixes();
		},
	},
} );
