<?php
/**
 * AI context file generator.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Generates a `.ai-context.md` file summarising site configuration.
 *
 * The file is written to the WordPress root directory and provides a
 * static, filesystem-based summary of the site's configuration for AI
 * agents that need context without executing REST endpoints or abilities.
 *
 * The file is automatically regenerated whenever a full system report
 * is generated via the admin UI or REST API, and can also be generated
 * on demand via the WP-CLI or a REST endpoint.
 *
 * The generated file never contains sensitive data: database credentials,
 * salts, and other secrets are excluded.
 */
class AI_Context_Generator {

	/**
	 * Default filename for the context file.
	 *
	 * @var string
	 */
	public const FILENAME = '.ai-context.md';

	/**
	 * Report generator instance.
	 */
	private Report_Generator $report_generator;

	/**
	 * Constructor.
	 *
	 * @param Report_Generator $report_generator Report generator instance.
	 */
	public function __construct( Report_Generator $report_generator ) {
		$this->report_generator = $report_generator;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * Hooks into the report generation lifecycle so the context file
	 * is refreshed whenever a report is produced.
	 */
	public function register_hooks(): void {
		add_action( 'wp_system_report_generated', array( $this, 'on_report_generated' ), 10, 1 );
	}

	/**
	 * Handle the report generated action.
	 *
	 * Called by the Report_Generator after a full report is produced.
	 * Writes the context file synchronously during the current request.
	 *
	 * @param array $report_data Full report data.
	 */
	public function on_report_generated( array $report_data ): void {
		$this->write_context_file( $report_data );
	}

	/**
	 * Generate and write the context file.
	 *
	 * If no report data is provided, generates a fresh report first.
	 *
	 * @param array|null $report_data Optional pre-generated report data.
	 * @return bool True on successful write, false on failure.
	 */
	public function write_context_file( ?array $report_data = null ): bool {
		if ( null === $report_data ) {
			$report_data = $this->report_generator->generate();
		}

		$content = $this->render( $report_data );
		$path    = $this->get_file_path();

		/**
		 * Filter the AI context file path.
		 *
		 * @param string $path The absolute path to the context file.
		 */
		$path = apply_filters( 'wp_system_report_ai_context_path', $path );

		// Ensure the directory is writable.
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
			return false;
		}

		// Use WP_Filesystem for safe file writing.
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return false;
		}

		$result = $wp_filesystem->put_contents( $path, $content, FS_CHMOD_FILE );

		if ( $result ) {
			/**
			 * Fires after the AI context file is successfully written.
			 *
			 * @param string $path    The file path that was written.
			 * @param string $content The file content that was written.
			 */
			do_action( 'wp_system_report_ai_context_written', $path, $content );
		}

		return (bool) $result;
	}

	/**
	 * Delete the context file if it exists.
	 *
	 * @return bool True if deleted or didn't exist, false on failure.
	 */
	public function delete_context_file(): bool {
		$path = $this->get_file_path();

		if ( ! file_exists( $path ) ) {
			return true;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return false;
		}

		return $wp_filesystem->delete( $path );
	}

	/**
	 * Check whether the context file exists.
	 *
	 * @return bool True if the file exists.
	 */
	public function file_exists(): bool {
		return file_exists( $this->get_file_path() );
	}

	/**
	 * Get the last-modified timestamp of the context file.
	 *
	 * @return int|null Unix timestamp or null if file doesn't exist.
	 */
	public function get_last_modified(): ?int {
		$path = $this->get_file_path();

		if ( ! file_exists( $path ) ) {
			return null;
		}

		$mtime = filemtime( $path );

		return false === $mtime ? null : $mtime;
	}

	/**
	 * Get the absolute path to the context file.
	 *
	 * @return string Absolute file path.
	 */
	public function get_file_path(): string {
		return ABSPATH . self::FILENAME;
	}

	/**
	 * Render the context file content.
	 *
	 * Produces a concise markdown summary of the site's configuration
	 * suitable for inclusion in an AI agent's context window.
	 *
	 * @param array $report_data Full report data from Report_Generator.
	 * @return string Rendered markdown content.
	 */
	private function render( array $report_data ): string {
		$output  = $this->render_header();
		$output .= $this->render_site_overview( $report_data );
		$output .= $this->render_environment( $report_data );
		$output .= $this->render_issues_summary( $report_data );
		$output .= $this->render_plugins_summary( $report_data );
		$output .= $this->render_theme_summary( $report_data );
		$output .= $this->render_footer();

		/**
		 * Filter the rendered AI context file content.
		 *
		 * @param string $output      The rendered markdown content.
		 * @param array  $report_data The full report data.
		 */
		return (string) apply_filters( 'wp_system_report_ai_context_content', $output, $report_data );
	}

	/**
	 * Render the file header with instructions for AI agents.
	 *
	 * @return string Header markdown.
	 */
	private function render_header(): string {
		$now = gmdate( 'Y-m-d H:i:s' ) . ' UTC';

		return "# AI Context: WordPress Site Configuration\n\n"
			. '> Auto-generated by WP System Report v' . WP_SYSTEM_REPORT_VERSION . "\n"
			. "> Last updated: {$now}\n"
			. "> This file provides site context for AI agents. Do not edit manually.\n\n"
			. "---\n\n";
	}

	/**
	 * Render the site overview section.
	 *
	 * @param array $report_data Full report data.
	 * @return string Overview markdown.
	 */
	private function render_site_overview( array $report_data ): string {
		$output = "## Site Overview\n\n";

		$output .= '- **URL**: ' . get_option( 'home' ) . "\n";
		$output .= '- **Name**: ' . get_option( 'blogname' ) . "\n";
		$output .= '- **WordPress**: ' . get_bloginfo( 'version' ) . "\n";
		$output .= '- **PHP**: ' . phpversion() . "\n";
		$output .= '- **Multisite**: ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";

		// Extract database version from report if available.
		$db_section = $report_data['database'] ?? null;
		if ( $db_section && ! empty( $db_section['fields'] ) ) {
			foreach ( $db_section['fields'] as $field ) {
				if ( 'Server Version' === ( $field['export_label'] ?? $field['label'] ) ) {
					$output .= '- **Database**: ' . $field['value'] . "\n";
					break;
				}
			}
		}

		// Extract web server from report.
		$server_section = $report_data['server_environment'] ?? null;
		if ( $server_section && ! empty( $server_section['fields'] ) ) {
			foreach ( $server_section['fields'] as $field ) {
				if ( 'Web Server' === ( $field['export_label'] ?? $field['label'] ) ) {
					$output .= '- **Web Server**: ' . $field['value'] . "\n";
					break;
				}
			}
		}

		return $output . "\n";
	}

	/**
	 * Render the environment section with key configuration details.
	 *
	 * @param array $report_data Full report data.
	 * @return string Environment markdown.
	 */
	private function render_environment( array $report_data ): string {
		$output = "## Environment\n\n";

		// Key configuration items to extract.
		$key_items = array(
			'wordpress_environment' => array(
				'Home URL',
				'Site URL',
				'WP Debug',
				'WP Memory Limit',
				'Permalink Structure',
				'Language',
				'Timezone',
			),
			'server_environment'    => array(
				'PHP Memory Limit',
				'PHP Max Execution Time',
				'PHP Max Input Variables',
				'cURL Version',
				'HTTPS',
			),
			'database'              => array(
				'Server Version',
				'Database Charset',
				'Table Prefix',
			),
		);

		foreach ( $key_items as $section_id => $labels ) {
			$section = $report_data[ $section_id ] ?? null;
			if ( ! $section ) {
				continue;
			}

			if ( empty( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {
				if ( ! empty( $field['private'] ) ) {
					continue;
				}

				$field_label = $field['export_label'] ?? $field['label'];
				if ( in_array( $field_label, $labels, true ) ) {
					$output .= '- **' . $field_label . '**: ' . $field['value'] . "\n";
				}
			}
		}

		return $output . "\n";
	}

	/**
	 * Render the issues summary.
	 *
	 * Lists all warnings and critical issues for quick reference.
	 *
	 * @param array $report_data Full report data.
	 * @return string Issues summary markdown.
	 */
	private function render_issues_summary( array $report_data ): string {
		$critical = array();
		$warnings = array();

		foreach ( $report_data as $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {
				if ( ! empty( $field['private'] ) ) {
					continue;
				}

				$status = ! empty( $field['status'] ) ? $field['status'] : 'info';

				if ( 'critical' === $status ) {
					$critical[] = $field['label'] . ': ' . $field['value'];
				} elseif ( 'warning' === $status ) {
					$warnings[] = $field['label'] . ': ' . $field['value'];
				}
			}
		}

		if ( empty( $critical ) && empty( $warnings ) ) {
			return "## Issues\n\nNo warnings or critical issues detected.\n\n";
		}

		$output = "## Issues\n\n";

		if ( ! empty( $critical ) ) {
			$output .= "### Critical\n\n";
			foreach ( $critical as $issue ) {
				$output .= "- {$issue}\n";
			}
			$output .= "\n";
		}

		if ( ! empty( $warnings ) ) {
			$output .= "### Warnings\n\n";
			foreach ( $warnings as $issue ) {
				$output .= "- {$issue}\n";
			}
			$output .= "\n";
		}

		return $output;
	}

	/**
	 * Render the plugins summary.
	 *
	 * Lists active plugins with version numbers. Inactive plugins are
	 * counted but not enumerated to save tokens.
	 *
	 * @param array $report_data Full report data.
	 * @return string Plugins summary markdown.
	 */
	private function render_plugins_summary( array $report_data ): string {
		$output = "## Plugins\n\n";

		// Active plugins.
		$active_section = $report_data['active_plugins'] ?? null;
		if ( $active_section && ! empty( $active_section['fields'] ) ) {
			$count   = count( $active_section['fields'] );
			$output .= "### Active ({$count})\n\n";

			foreach ( $active_section['fields'] as $field ) {
				if ( ! empty( $field['private'] ) ) {
					continue;
				}
				$output .= '- ' . $field['label'] . ': ' . $field['value'] . "\n";
			}
			$output .= "\n";
		}

		// Inactive plugins — count only.
		$inactive_section = $report_data['inactive_plugins'] ?? null;
		if ( $inactive_section && ! empty( $inactive_section['fields'] ) ) {
			$count   = count( $inactive_section['fields'] );
			$output .= "### Inactive\n\n";
			$output .= "{$count} inactive plugin(s) installed.\n\n";
		}

		// Drop-ins and MU plugins.
		$dropin_section = $report_data['dropins_mu_plugins'] ?? null;
		if ( $dropin_section && ! empty( $dropin_section['fields'] ) ) {
			$output .= "### Drop-ins & MU-Plugins\n\n";
			foreach ( $dropin_section['fields'] as $field ) {
				if ( ! empty( $field['private'] ) ) {
					continue;
				}
				$output .= '- ' . $field['label'] . ': ' . $field['value'] . "\n";
			}
			$output .= "\n";
		}

		return $output;
	}

	/**
	 * Render the theme summary.
	 *
	 * @param array $report_data Full report data.
	 * @return string Theme summary markdown.
	 */
	private function render_theme_summary( array $report_data ): string {
		$section = $report_data['theme_info'] ?? null;

		if ( ! $section || empty( $section['fields'] ) ) {
			return '';
		}

		$output = "## Theme\n\n";

		foreach ( $section['fields'] as $field ) {
			if ( ! empty( $field['private'] ) ) {
				continue;
			}
			$output .= '- **' . $field['label'] . '**: ' . $field['value'] . "\n";
		}

		return $output . "\n";
	}

	/**
	 * Render the file footer.
	 *
	 * @return string Footer markdown.
	 */
	private function render_footer(): string {
		return "---\n\n"
			. '_Generated by [WP System Report](https://github.com/chrisfromthelc/wp-system-report). '
			. "For full diagnostics, use the REST API at `/wp-json/wp-system-report/v1/report`._\n";
	}
}
