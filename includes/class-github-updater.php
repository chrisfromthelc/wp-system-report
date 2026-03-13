<?php
/**
 * GitHub-based plugin updater.
 *
 * Uses the modern Update URI plugin header (WordPress 5.8+) to check
 * GitHub Releases for newer versions and serve updates.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Handles checking GitHub for plugin updates and serving update info.
 */
class GitHub_Updater {

	/**
 * Plugin slug.
 */
	private string $plugin_slug = 'wp-system-report';

	/**
 * Plugin basename (e.g. wp-system-report/wp-system-report.php).
 */
	private string $plugin_basename;

	/**
 * GitHub repository in owner/repo format.
 */
	private string $repo = 'chrisfromthelc/wp-system-report';

	/**
 * GitHub Releases API URL.
 */
	private string $api_url = 'https://api.github.com/repos/chrisfromthelc/wp-system-report/releases/latest';

	/**
 * Transient cache key for remote release data.
 */
	private string $cache_key = 'sr_github_update';

	/**
 * Cache TTL in seconds (12 hours).
 */
	private int $cache_ttl = 43200;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_basename = plugin_basename( $plugin_file );

		add_filter( 'update_plugins_github.com', array( $this, 'check_update' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_update_cache' ), 10, 0 );
	}

	/**
	 * Check for a plugin update via the Update URI filter.
	 *
	 * @param array|false $update     The plugin update data. Default false.
	 * @param array       $plugin_data Plugin headers (Name, Version, Update URI, etc.).
	 * @param string      $plugin_file Plugin basename relative to plugins directory.
	 * @param string[]    $locales     Installed locales to look up translations for.
	 * @return array|false Update data array on success, false if no update available.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $locales required by filter signature.
	public function check_update( $update, array $plugin_data, string $plugin_file, array $locales ) {
		if ( $plugin_file !== $this->plugin_basename ) {
			return $update;
		}

		$release = $this->get_remote_release();

		if ( ! $release ) {
			return false;
		}

		$remote_version  = ltrim( $release['tag_name'] ?? '', 'v' );
		$current_version = $plugin_data['Version'] ?? '0.0.0';

		if ( ! version_compare( $current_version, $remote_version, '<' ) ) {
			return false;
		}

		$package = $this->get_release_asset_url( $release );

		return array(
			'slug'    => $this->plugin_slug,
			'version' => $remote_version,
			'url'     => $release['html_url'] ?? '',
			'package' => $package,
		);
	}

	/**
	 * Provide plugin information for the "View details" modal.
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Installation API.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Plugin info object on match, passthrough otherwise.
	 */
	public function plugin_info( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_remote_release();

		if ( ! $release ) {
			return $result;
		}

		$remote_version = ltrim( $release['tag_name'] ?? '', 'v' );

		$info                = new \stdClass();
		$info->name          = 'WP System Report';
		$info->slug          = $this->plugin_slug;
		$info->version       = $remote_version;
		$info->author        = '<a href="https://github.com/chrisfromthelc">Christopher Smith</a>';
		$info->homepage      = 'https://github.com/' . $this->repo;
		$info->requires      = '6.2';
		$info->tested        = $release['tested_wp'] ?? get_bloginfo( 'version' );
		$info->requires_php  = '8.1';
		$info->download_link = $this->get_release_asset_url( $release );
		$info->trunk         = $this->get_release_asset_url( $release );
		$info->last_updated  = $release['published_at'] ?? '';

		$info->sections = array(
			'description' => 'Comprehensive WordPress system status report with AI-optimized export. No WooCommerce required.',
			'changelog'   => $this->format_changelog( $release['body'] ?? '' ),
		);

		return $info;
	}

	/**
	 * Clear the cached remote release data.
	 */
	public function clear_update_cache(): void {
		delete_transient( $this->cache_key );
		delete_transient( $this->cache_key . '_failed' );
	}

	/**
	 * Fetch the latest release from GitHub, with transient caching.
	 *
	 * @return array|false Release data array on success, false on failure.
	 */
	private function get_remote_release() {
		$cached = get_transient( $this->cache_key );

		if ( false !== $cached ) {
			// Validate cached data structure before using it.
			if ( is_array( $cached ) && ! empty( $cached['tag_name'] ) ) {
				return $cached;
			}
			// Invalid cache structure — delete and re-fetch.
			delete_transient( $this->cache_key );
		}

		// Skip API call if a recent failure is cached.
		if ( false !== get_transient( $this->cache_key . '_failed' ) ) {
			return false;
		}

		$response = wp_remote_get(
			$this->api_url,
			array(
				'headers'             => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'wp-system-report/' . WP_SYSTEM_REPORT_VERSION,
				),
				'timeout'             => 10,
				'limit_response_size' => 1048576, // 1 MB max.
			)
		);

		if ( is_wp_error( $response ) ) {
			// Cache failures briefly to avoid repeated requests.
			set_transient( $this->cache_key . '_failed', 1, 30 * MINUTE_IN_SECONDS );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			// Cache non-200 responses (e.g. rate-limit 403) to avoid repeated failed requests.
			set_transient( $this->cache_key . '_failed', 1, 30 * MINUTE_IN_SECONDS );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return false;
		}

		set_transient( $this->cache_key, $data, $this->cache_ttl );

		return $data;
	}

	/**
	 * Get the download URL for the release zip asset.
	 *
	 * Falls back to the GitHub zipball URL if no asset is attached.
	 *
	 * @param array $release Release data from the GitHub API.
	 * @return string Download URL.
	 */
	private function get_release_asset_url( array $release ): string {
		// Prefer attached zip asset named wp-system-report.zip.
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] )
					&& str_ends_with( $asset['name'] ?? '', '.zip' )
					&& $this->is_valid_github_url( $asset['browser_download_url'] )
				) {
					return $asset['browser_download_url'];
				}
			}
		}

		// Fallback to GitHub's auto-generated zipball.
		$zipball = $release['zipball_url'] ?? '';

		if ( '' !== $zipball && ! $this->is_valid_github_url( $zipball ) ) {
			return '';
		}

		return $zipball;
	}

	/**
	 * Validate that a URL points to a known GitHub domain.
	 *
	 * @param string $url URL to validate.
	 * @return bool True if the URL points to github.com or api.github.com.
	 */
	private function is_valid_github_url( string $url ): bool {
		return str_starts_with( $url, 'https://github.com/' )
			|| str_starts_with( $url, 'https://api.github.com/' );
	}

	/**
	 * Format the release body as HTML for the changelog section.
	 *
	 * @param string $body Raw release body (typically markdown).
	 * @return string Sanitized HTML changelog.
	 */
	private function format_changelog( string $body ): string {
		if ( empty( $body ) ) {
			return '<p>No changelog provided.</p>';
		}

		// Convert basic markdown to HTML.
		$html = esc_html( $body );

		return nl2br( $html );
	}
}
