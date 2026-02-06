<?php
/**
 * Database collector.
 *
 * @package SystemReport
 */

namespace SystemReport\Collectors;

defined( 'ABSPATH' ) || exit;

/**
 * Collects database tables, sizes, and engine information.
 */
class Database extends Abstract_Collector {

	/**
	 * Get the collector ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'database';
	}

	/**
	 * Get the collector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Database', 'system-report' );
	}

	/**
	 * Get the collector description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Database tables, sizes, and engine information.', 'system-report' );
	}

	/**
	 * Get the collector priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return 30;
	}

	/**
	 * Collect the data.
	 *
	 * @return array
	 */
	public function collect() {
		global $wpdb;

		$data = array();

		// Database Prefix.
		$prefix        = $wpdb->prefix;
		$prefix_status = 'good';
		if ( strlen( $prefix ) > 20 ) {
			$prefix_status = 'warning';
		}

		$data[] = $this->make_field(
			__( 'Database Prefix', 'system-report' ),
			$prefix,
			array( 'status' => $prefix_status )
		);

		// Database Charset.
		$charset = $this->get_constant_value( 'DB_CHARSET', 'utf8' );
		$data[]  = $this->make_field(
			__( 'Database Charset', 'system-report' ),
			$charset
		);

		// Database Collation.
		$collate = $this->get_constant_value( 'DB_COLLATE', $wpdb->collate );
		$data[]  = $this->make_field(
			__( 'Database Collation', 'system-report' ),
			$collate ? $collate : __( 'Default', 'system-report' )
		);

		// Max Allowed Packet.
		$max_allowed_packet = $wpdb->get_var( 'SELECT @@max_allowed_packet' );
		$data[]             = $this->make_field(
			__( 'Max Allowed Packet', 'system-report' ),
			$max_allowed_packet ? $this->format_size( $max_allowed_packet ) : __( 'Unknown', 'system-report' )
		);

		// Get all tables from information_schema.
		$database_name = $this->get_constant_value( 'DB_NAME' );
		$tables_query  = $wpdb->prepare(
			'SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = %s',
			$database_name
		);

		$tables = $wpdb->get_results( $tables_query );

		// Core WordPress tables.
		$core_tables = array(
			'commentmeta',
			'comments',
			'links',
			'options',
			'postmeta',
			'posts',
			'termmeta',
			'terms',
			'term_relationships',
			'term_taxonomy',
			'usermeta',
			'users',
		);

		$wp_core_tables = array();
		$other_tables   = array();
		$total_size     = 0;

		if ( $tables ) {
			foreach ( $tables as $table ) {
				$table_size  = $table->DATA_LENGTH + $table->INDEX_LENGTH;
				$total_size += $table_size;

				// Check if this is a core WordPress table.
				$table_without_prefix = str_replace( $wpdb->prefix, '', $table->TABLE_NAME );
				if ( in_array( $table_without_prefix, $core_tables, true ) ) {
					$wp_core_tables[] = array(
						'name'   => $table->TABLE_NAME,
						'engine' => $table->ENGINE,
						'rows'   => $table->TABLE_ROWS,
						'size'   => $table_size,
					);
				} else {
					$other_tables[] = array(
						'name'   => $table->TABLE_NAME,
						'engine' => $table->ENGINE,
						'rows'   => $table->TABLE_ROWS,
						'size'   => $table_size,
					);
				}
			}
		}

		// Total Database Size.
		$data[] = $this->make_field(
			__( 'Total Database Size', 'system-report' ),
			$this->format_size( $total_size )
		);

		// WordPress Core Tables.
		foreach ( $wp_core_tables as $table ) {
			$table_info = sprintf(
				/* translators: 1: Engine, 2: Rows, 3: Size */
				__( 'Engine: %1$s | Rows: %2$s | Size: %3$s', 'system-report' ),
				$table['engine'],
				number_format_i18n( $table['rows'] ),
				$this->format_size( $table['size'] )
			);

			$data[] = $this->make_field(
				$table['name'],
				$table_info
			);
		}

		// Other Tables.
		foreach ( $other_tables as $table ) {
			$table_info = sprintf(
				/* translators: 1: Engine, 2: Rows, 3: Size */
				__( 'Engine: %1$s | Rows: %2$s | Size: %3$s', 'system-report' ),
				$table['engine'],
				number_format_i18n( $table['rows'] ),
				$this->format_size( $table['size'] )
			);

			$data[] = $this->make_field(
				$table['name'],
				$table_info
			);
		}

		return $data;
	}
}
