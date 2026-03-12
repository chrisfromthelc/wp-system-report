<?php
/**
 * Admin notice recommending the MCP Adapter plugin.
 *
 * Shown on the WP System Report admin page when the MCP Adapter
 * is not installed or active. Can be permanently dismissed.
 *
 * @package SystemReport
 */

defined( 'ABSPATH' ) || exit;

// Template variables are scoped to the including method, not truly global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$sr_dismiss_url = wp_nonce_url(
	add_query_arg( 'sr_dismiss_mcp_notice', '1' ),
	'sr_dismiss_mcp_notice'
);
?>
<div class="notice notice-info is-dismissible sr-mcp-notice">
	<p>
		<strong><?php esc_html_e( 'WP System Report — AI Integration Available', 'wp-system-report' ); ?></strong>
	</p>
	<p>
		<?php
		printf(
			/* translators: %s: link to MCP Adapter releases page */
			esc_html__( 'WP System Report registers abilities with the WordPress Abilities API. Install the %s to expose these abilities to AI agents via the Model Context Protocol (MCP).', 'wp-system-report' ),
			'<a href="https://github.com/WordPress/mcp-adapter/releases" target="_blank" rel="noopener noreferrer">'
				. esc_html__( 'MCP Adapter plugin', 'wp-system-report' )
				. '</a>'
		);
		?>
	</p>
	<p>
		<a href="<?php echo esc_url( $sr_dismiss_url ); ?>" class="sr-mcp-notice-dismiss">
			<?php esc_html_e( 'Don\'t show this again', 'wp-system-report' ); ?>
		</a>
	</p>
</div>
