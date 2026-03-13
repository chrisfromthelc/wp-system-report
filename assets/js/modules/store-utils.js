/**
 * WP System Report - Shared Utility Functions.
 *
 * Pure helper functions shared across Interactivity API stores.
 * These are imported by each tab-specific store module rather than
 * duplicated.
 *
 * @package SystemReport
 */

/**
 * Copy text to the clipboard.
 *
 * @param {string}   text      Text to copy.
 * @param {Function} onSuccess Callback on successful copy.
 * @param {Function} onError   Callback on failure.
 */
export function copyToClipboard( text, onSuccess, onError ) {
	if ( navigator.clipboard && navigator.clipboard.writeText ) {
		navigator.clipboard.writeText( text ).then( onSuccess, onError );
	} else {
		const textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild( textarea );
		textarea.select();
		try {
			document.execCommand( 'copy' );
			onSuccess();
		} catch ( e ) {
			onError( e );
		}
		document.body.removeChild( textarea );
	}
}

/**
 * Show a temporary "Copied!" indicator next to a button.
 *
 * @param {HTMLElement} button    The button element.
 * @param {string}     copiedMsg Translated "Copied!" label.
 */
export function showCopySuccess( button, copiedMsg ) {
	let indicator = button.nextElementSibling;
	if (
		! indicator ||
		! indicator.classList.contains( 'sr-copy-success' )
	) {
		indicator = document.createElement( 'span' );
		indicator.className = 'sr-copy-success';
		indicator.textContent = copiedMsg || 'Copied!';
		button.parentNode.insertBefore( indicator, button.nextSibling );
	}
	indicator.classList.add( 'visible' );
	setTimeout( () => indicator.classList.remove( 'visible' ), 2000 );
}

/**
 * Download text content as a file.
 *
 * @param {string} content  File content.
 * @param {string} filename File name.
 * @param {string} mimeType MIME type (default: text/plain).
 */
export function downloadFile( content, filename, mimeType ) {
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
}

/**
 * Build a timestamped filename.
 *
 * @param {string} prefix File prefix.
 * @param {string} ext    File extension.
 * @return {string} Full filename.
 */
export function buildFilename( prefix, ext ) {
	const domain = window.location.hostname;
	const datetime = new Date()
		.toISOString()
		.slice( 0, 19 )
		.replace( /:/g, '-' );
	return prefix + '_' + domain + '_' + datetime + '.' + ext;
}
