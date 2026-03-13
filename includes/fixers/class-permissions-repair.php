<?php
/**
 * Filesystem permissions repair fixer.
 *
 * @package SystemReport
 */

namespace SystemReport\Fixers;

use SystemReport\Fixer;
use SystemReport\Fix_Result;
use SystemReport\Risk_Level;

defined( 'ABSPATH' ) || exit;

/**
 * Repairs filesystem permissions on critical WordPress directories.
 *
 * Detects and remediates common permission issues:
 *
 * - World-writable directories: the "others write" bit (0o002) is set,
 *   allowing any system user to write files — a serious security risk.
 * - Non-writable directories: critical directories like `wp-content/uploads`
 *   are not writable by the web server, preventing uploads and updates.
 *
 * All changes are reversible: permissions can be restored to any value
 * by the server administrator. The fixer targets the standard WordPress
 * directory permission mode of 0755 (owner rwx, group rx, others rx).
 */
class Permissions_Repair implements Fixer {

	/**
	 * Target permission mode for directories.
	 *
	 * Standard WordPress directory permissions: owner read/write/execute,
	 * group read/execute, others read/execute.
	 *
	 * @var int
	 */
	private const TARGET_DIR_PERMS = 0755;

	/**
	 * Injected target directories for testing.
	 *
	 * When null, the default WordPress directories are used.
	 *
	 * @var array<int, array{label: string, path: string, must_be_writable: bool}>|null
	 */
	private ?array $target_directories;

	/**
	 * Constructor.
	 *
	 * @param array<int, array{label: string, path: string, must_be_writable: bool}>|null $target_directories Optional directory list for testing.
	 */
	public function __construct( ?array $target_directories = null ) {
		$this->target_directories = $target_directories;
	}

	/**
	 * Get the unique slug identifier.
	 *
	 * @return string Fixer ID.
	 */
	public function get_id(): string {
		return 'permissions_repair';
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string Translated label.
	 */
	public function get_label(): string {
		return __( 'Filesystem Permissions Repair', 'wp-system-report' );
	}

	/**
	 * Get the fixer description.
	 *
	 * @return string Translated description.
	 */
	public function get_description(): string {
		return __( 'Corrects world-writable directories and restores writability to critical WordPress directories by setting permissions to 0755.', 'wp-system-report' );
	}

	/**
	 * Get the risk level.
	 *
	 * @return Risk_Level Risk level.
	 */
	public function get_risk_level(): Risk_Level {
		return Risk_Level::Medium;
	}

	/**
	 * Get the category.
	 *
	 * @return string Category slug.
	 */
	public function get_category(): string {
		return 'filesystem';
	}

	/**
	 * Check if any permission repairs can be applied.
	 *
	 * @return bool True when at least one directory has problematic permissions.
	 */
	public function can_fix(): bool {
		foreach ( $this->get_target_directories() as $dir ) {
			$problem = $this->check_directory( $dir );
			if ( null !== $problem ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Execute the permission repair.
	 *
	 * @return Fix_Result Result with before/after snapshots.
	 */
	public function fix(): Fix_Result {
		$before = $this->capture_state();

		if ( 0 === $before['problem_count'] ) {
			return Fix_Result::success(
				__( 'All directory permissions are correct. No repairs needed.', 'wp-system-report' )
			);
		}

		$applied = array();
		$errors  = array();

		foreach ( $before['problems'] as $label => $problem ) {
			$path = $problem['path'];

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod,WordPress.PHP.NoSilencedErrors.Discouraged -- Direct chmod is intentional: WP_Filesystem requires FTP credentials on many hosts and is unsuitable for automated fixers. We check the return value explicitly and report failures gracefully.
			$result = @chmod( $path, self::TARGET_DIR_PERMS );

			if ( $result ) {
				$applied[] = sprintf(
					/* translators: 1: directory label, 2: original permission mode */
					__( '%1$s corrected from %2$s to 0755', 'wp-system-report' ),
					$label,
					$problem['current_perms']
				);
			} else {
				$errors[] = sprintf(
					/* translators: 1: directory label, 2: directory path */
					__( 'Failed to change permissions on %1$s (%2$s) — the directory may be owned by a different user.', 'wp-system-report' ),
					$label,
					$path
				);
			}
		}

		// Clear PHP's stat cache so fileperms() returns the updated values.
		clearstatcache();

		$after = $this->capture_state();

		if ( empty( $applied ) && ! empty( $errors ) ) {
			return Fix_Result::failure(
				__( 'Could not repair any directory permissions.', 'wp-system-report' ),
				$errors
			);
		}

		$message = implode( '; ', $applied ) . '.';
		if ( ! empty( $errors ) ) {
			$message .= ' ' . implode( '; ', $errors );
		}

		return Fix_Result::success( $message, $before, $after );
	}

	/**
	 * Capture the current permission state.
	 *
	 * @return array<string, mixed> State snapshot.
	 */
	private function capture_state(): array {
		$directories = array();
		$problems    = array();

		foreach ( $this->get_target_directories() as $dir ) {
			$label = $dir['label'];
			$path  = $dir['path'];

			$perms    = $this->get_permissions_string( $path );
			$writable = $this->is_writable( $path );

			$directories[ $label ] = array(
				'path'        => $path,
				'label'       => $label,
				'permissions' => $perms,
				'writable'    => $writable,
			);

			$problem = $this->check_directory( $dir );
			if ( null !== $problem ) {
				$problems[ $label ] = $problem;
			}
		}

		return array(
			'directories'   => $directories,
			'problems'      => $problems,
			'problem_count' => count( $problems ),
		);
	}

	/**
	 * Get the list of target directories to check and repair.
	 *
	 * @return array<int, array{label: string, path: string, must_be_writable: bool}> Target directories.
	 */
	private function get_target_directories(): array {
		if ( null !== $this->target_directories ) {
			return $this->target_directories;
		}

		$dirs = array(
			array(
				'label'            => 'WordPress Root',
				'path'             => ABSPATH,
				'must_be_writable' => false,
			),
			array(
				'label'            => 'wp-content',
				'path'             => WP_CONTENT_DIR,
				'must_be_writable' => true,
			),
			array(
				'label'            => 'Plugins',
				'path'             => WP_PLUGIN_DIR,
				'must_be_writable' => true,
			),
			array(
				'label'            => 'Themes',
				'path'             => get_theme_root(),
				'must_be_writable' => true,
			),
		);

		// Add uploads directory.
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['basedir'] ) ) {
			$dirs[] = array(
				'label'            => 'Uploads',
				'path'             => $upload_dir['basedir'],
				'must_be_writable' => true,
			);
		}

		// Add MU plugins directory if defined.
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$dirs[] = array(
				'label'            => 'MU Plugins',
				'path'             => WPMU_PLUGIN_DIR,
				'must_be_writable' => false,
			);
		}

		return $dirs;
	}

	/**
	 * Check a directory for permission problems.
	 *
	 * @param array{label: string, path: string, must_be_writable: bool} $dir Directory definition.
	 * @return array{path: string, is_world_writable: bool, is_not_writable: bool, current_perms: string}|null Problem details, or null if no problem.
	 */
	private function check_directory( array $dir ): ?array {
		$path = $dir['path'];

		if ( ! is_dir( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		$is_world_writable = $this->is_world_writable( $path );
		$is_not_writable   = $dir['must_be_writable'] && ! $this->is_writable( $path );

		if ( ! $is_world_writable && ! $is_not_writable ) {
			return null;
		}

		return array(
			'path'              => $path,
			'is_world_writable' => $is_world_writable,
			'is_not_writable'   => $is_not_writable,
			'current_perms'     => $this->get_permissions_string( $path ),
		);
	}

	/**
	 * Check if a path is world-writable.
	 *
	 * @param string $path Directory path.
	 * @return bool True if the "others write" bit is set.
	 */
	private function is_world_writable( string $path ): bool {
		$perms = $this->get_permissions( $path );

		return false !== $perms && ( $perms & 0002 );
	}

	/**
	 * Check if a path is writable by the web server.
	 *
	 * @param string $path Directory path.
	 * @return bool True if the path is writable.
	 */
	private function is_writable( string $path ): bool {
		return wp_is_writable( $path );
	}

	/**
	 * Get the raw permission integer for a path.
	 *
	 * @param string $path Directory path.
	 * @return int|false Permission integer, or false on failure.
	 */
	private function get_permissions( string $path ): false|int {
		if ( ! is_readable( $path ) ) {
			return false;
		}

		return fileperms( $path );
	}

	/**
	 * Get the octal permission string for a path (e.g. '755').
	 *
	 * @param string $path Directory path.
	 * @return string Octal permission string, or 'unknown' on failure.
	 */
	private function get_permissions_string( string $path ): string {
		$perms = $this->get_permissions( $path );

		if ( false === $perms ) {
			return 'unknown';
		}

		return decoct( $perms & 0777 );
	}
}
