<?php
/**
 * Fixer registry.
 *
 * @package SystemReport
 */

namespace SystemReport;

defined( 'ABSPATH' ) || exit;

/**
 * Central registry for fixer instances.
 *
 * Manages registration and retrieval of all available fixers.
 * Follows the same pattern as the collector registry in
 * Report_Generator to ensure consistency across the plugin.
 */
class Fixer_Registry {

	/**
	 * Registered fixers, keyed by fixer ID.
	 *
	 * @var Fixer[]
	 */
	private array $fixers = array();

	/**
	 * Register a fixer instance.
	 *
	 * If a fixer with the same ID is already registered, a _doing_it_wrong()
	 * notice is triggered and the existing fixer is replaced by the new instance.
	 *
	 * @param Fixer $fixer Fixer instance to register.
	 */
	public function register( Fixer $fixer ): void {
		$id = $fixer->get_id();

		if ( isset( $this->fixers[ $id ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: fixer ID */
					esc_html__( 'A fixer with the ID "%s" is already registered. The existing fixer will be replaced.', 'wp-system-report' ),
					esc_html( $id )
				),
				'1.0.0'
			);
		}

		$this->fixers[ $id ] = $fixer;
	}

	/**
	 * Retrieve a single fixer by its ID.
	 *
	 * @param string $id Fixer slug identifier.
	 * @return Fixer|null The fixer instance, or null if not found.
	 */
	public function get( string $id ): ?Fixer {
		return $this->get_all()[ $id ] ?? null;
	}

	/**
	 * Get all registered fixers.
	 *
	 * Applies the 'wp_system_report_fixers' filter to allow third-party
	 * plugins to add, remove, or replace registered fixers.
	 *
	 * @return Fixer[] Associative array of fixer ID => Fixer instance.
	 */
	public function get_all(): array {
		/**
		 * Filter the registered fixers.
		 *
		 * @param Fixer[] $fixers Associative array of fixer ID => Fixer instance.
		 */
		return apply_filters( 'wp_system_report_fixers', $this->fixers );
	}

	/**
	 * Get all fixers belonging to a specific category.
	 *
	 * @param string $category Category slug (e.g. 'performance', 'database').
	 * @return Fixer[] Array of matching Fixer instances.
	 */
	public function get_by_category( string $category ): array {
		return array_filter(
			$this->get_all(),
			static function ( Fixer $fixer ) use ( $category ): bool {
				return $category === $fixer->get_category();
			}
		);
	}

	/**
	 * Check whether a fixer with the given ID is registered.
	 *
	 * @param string $id Fixer slug identifier.
	 * @return bool True if a fixer with that ID exists.
	 */
	public function has( string $id ): bool {
		return isset( $this->get_all()[ $id ] );
	}
}
