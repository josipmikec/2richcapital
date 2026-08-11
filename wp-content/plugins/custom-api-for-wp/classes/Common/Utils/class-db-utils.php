<?php
/**
 * This file deals with database functionality logic.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */


namespace MO_CAW\Common;

use MO_CAW\Common\Utils;

/**
 * This Class deals with common functions required by complete plugin.
 */
class DB_Utils {
	/**
	 * Store the value in the wp_options table.
	 *
	 * @param string $key   option key.
	 * @param string $value option value.
	 *
	 * @return boolean
	 */
	public static function update_option( $key, $value ) {
		$return_value = ( is_multisite() ) ? update_site_option( $key, $value ) : update_option( $key, $value );
		return $return_value;
	}

	/**
	 * Get the value from the wp_options table.
	 *
	 * @param string $key     Option key.
	 * @param bool   $default Return value if option doesn't exist.
	 *
	 * @return mixed
	 */
	public static function get_option( $key, $default = false ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Passed as function parameter.
		$option_value = ( is_multisite() ) ? get_site_option( $key, $default ) : get_option( $key, $default );
		return $option_value;
	}

	/**
	 * Delete the key and value from the wp_options table.
	 *
	 * @param  string $key option key.
	 * @return boolean
	 */
	public static function delete_option( $key ) {
		$return_value = ( is_multisite() ) ? delete_site_option( $key ) : delete_option( $key );
		return $return_value;
	}

	/**
	 * Function to create custom tables.
	 *
	 * @return void
	 */
	public static function create_custom_tables() {
		global $wpdb;
		// External API Configuration table.
		$wpdb->query( $wpdb->prepare( "CREATE TABLE IF NOT EXISTS %1smo_external_api_config ( ID INT AUTO_INCREMENT PRIMARY KEY, connection_name VARCHAR(25) NOT NULL, is_enabled BOOLEAN DEFAULT true, method ENUM('get', 'put', 'post', 'patch', 'delete') NOT NULL, configuration LONGTEXT,  type ENUM('external_endpoint') DEFAULT 'external_endpoint' NOT NULL, api_type ENUM('simple_api', 'chain_api') DEFAULT 'simple_api' NOT NULL, chained_connections LONGTEXT, is_used_by LONGTEXT, UNIQUE KEY unique_connection (connection_name, method))", $wpdb->prefix ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange

		// Custom endpoint Configuration table.
		$wpdb->query( $wpdb->prepare( "CREATE TABLE IF NOT EXISTS %1smo_custom_endpoint_config ( ID INT AUTO_INCREMENT PRIMARY KEY, connection_name VARCHAR(25) NOT NULL, namespace VARCHAR(15) DEFAULT 'mo/v1', is_enabled BOOLEAN DEFAULT true, method ENUM('get', 'put', 'post', 'delete') NOT NULL, configuration LONGTEXT, type ENUM('gui_endpoint', 'sql_endpoint') NOT NULL, UNIQUE KEY unique_connection (connection_name, namespace, method ) )", $wpdb->prefix ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Add or update a configuration in plugin.
	 *
	 * @param array $table_configuration Complete table data for the connection.
	 *                                   Above param includes.
	 *                                   - type            - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint).
	 *                                   - connection_name - Connection name.
	 *                                   - method          - Connection method.
	 *                                   - configuration   - Connection additional configurations.
	 *                                   . Additional for external .
	 *                                   - chained_connections - connection type.
	 *                                   - is_loop - connection type.
	 *                                   - is_used_by - connection type.
	 *                                   . additional for GUI and SQL .
	 *                                   - namespace - connection namespace.
	 *
	 * @return boolean
	 */
	public static function update_configuration( $table_configuration ) {
		global $wpdb;

		$type            = $table_configuration['type'] ?? '';
		$connection_name = $table_configuration['connection_name'] ?? '';

		$method = $table_configuration['method'] ?? '';

		if ( Constants::EXTERNAL_ENDPOINT === $type ) {

			$api_type            = $table_configuration['api_type'] ?? Constants::SIMPLE_API_EXTERNAL_API_TYPE;
			$chained_connections = $table_configuration['chained_connections'] ?? array();
			$chained_connections = maybe_serialize( $chained_connections );

			$is_used_by = $table_configuration['is_used_by'];
			$is_used_by = maybe_serialize( $is_used_by );

			$configuration = $table_configuration['configuration'];
			$configuration = maybe_serialize( $configuration );

			$response = $wpdb->query( $wpdb->prepare( "INSERT INTO `%1smo_external_api_config` ( `connection_name`, `type`, `api_type`, `method`, `configuration`, `chained_connections`, `is_used_by` ) VALUES ( '%1s', '%1s', '%1s', '%1s', '%1s', '%1s', '%1s' ) ON DUPLICATE KEY UPDATE `api_type` = VALUES(`api_type`), `configuration` = VALUES(`configuration`), `chained_connections` = VALUES(`chained_connections`), `is_used_by` = VALUES(`is_used_by`)", $wpdb->prefix, $connection_name, $type, $api_type, $method, $configuration, $chained_connections, $is_used_by ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		} elseif ( Constants::GUI_ENDPOINT === $type || Constants::SQL_ENDPOINT === $type ) {

			$namespace = $table_configuration['namespace'];

			$configuration = $table_configuration['configuration'];
			$configuration = maybe_serialize( $configuration );

			$response = $wpdb->query( $wpdb->prepare( "INSERT INTO `%1smo_custom_endpoint_config` ( `namespace`, `connection_name`, `type`, `method`, `configuration` ) VALUES ( '%1s', '%1s', '%1s', '%1s', '%1s' ) ON DUPLICATE KEY UPDATE `configuration` = VALUES(`configuration`)", $wpdb->prefix, $namespace, $connection_name, $type, $method, $configuration ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		} else {
			$response = false;
		}

		if ( ! $wpdb->last_error ) {
			$response = true;
		}

		return $response;
	}


	/**
	 * Function to update is_enabled check
	 *
	 * @param array $table_configuration Complete table data for the connection.
	 *                                   Above param includes.
	 *                                   - type            - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint).
	 *                                   - connection_name - Connection name.
	 *                                   - is_enabled      - Boolean flag to know if the connection is active for use.
	 *                                   - method          - Connection method.
	 *                                   - configuration   - Connection additional configurations.
	 *                                   . additional for GUI and SQL .
	 *                                   - namespace - connection namespace.
	 *
	 * @return int
	 */
	public static function update_enable_of_endpoint( $table_configuration ) {
		global $wpdb;
		$connection_name = $table_configuration['connection_name'];
		$type            = $table_configuration['type'];
		$is_enabled      = $table_configuration['is_enabled'] ? 'true' : 'false';
		$method          = $table_configuration['method'];
		if ( Constants::EXTERNAL_ENDPOINT === $type ) {

			$response = $wpdb->query( $wpdb->prepare( 'UPDATE `%1smo_external_api_config` SET `is_enabled` = %1s WHERE `connection_name` = %s AND `type` = %s AND `method` = %s', $wpdb->prefix, $is_enabled, $connection_name, $type, $method ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		} elseif ( Constants::GUI_ENDPOINT === $type || Constants::SQL_ENDPOINT === $type ) {

			$namespace = $table_configuration['namespace'];
			$response  = $wpdb->query( $wpdb->prepare( 'UPDATE `%1smo_custom_endpoint_config` SET `is_enabled` = %1s WHERE `connection_name` = %s AND `type` = %s AND `method` = %s AND `namespace` = %s', $wpdb->prefix, $is_enabled, $connection_name, $type, $method, $namespace ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		} else {
			$response = false;
		}

		return $response;
	}

	/**
	 * Get configurations for a specific configuration type.
	 *
	 * @param array $row_filter    Values to filter row on.
	 *                                   Above param includes.
	 *                                   - type            - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint). Always Required
	 *                                   - namespace       - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint). Required for getting single connection details Except for external_endpoint
	 *                                   - connection_name - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint). Required for getting single connection details
	 *                                   - method          - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint). Required for getting single connection details
	 *                                   - any other row filter( column name => column value).
	 *
	 * @param array $column_filter Values to filter column on.
	 *                                   Above param includes name of the columns to return.
	 *
	 * @return array
	 */
	public static function get_configuration( $row_filter, $column_filter = array() ) {
		global $wpdb;

		$configuration_type = $row_filter['type'];

		// Return array.
		$configurations = array();

		// Creating query condition.
		unset( $row_filter['type'] );
		// Build WHERE clause with prepared statements.
		$where_clauses = array( 'type = %s' );
		$where_values  = array( $configuration_type );

		foreach ( $row_filter as $column_name => $column_value ) {
			$where_clauses[] = '`' . esc_sql( $column_name ) . '` = %s';
			$where_values[]  = $column_value;
		}
		$query_condition = ' WHERE ' . implode( ' AND ', $where_clauses );

		// Setting columns to return.
		if ( ! empty( $column_filter ) ) {
			$column_names = array();
			foreach ( $column_filter as $column_name ) {
				$column_names[] = '`' . esc_sql( $column_name ) . '`';
			}
			$column_names = implode( ', ', $column_names );
		} else {
			$column_names = '*';
		}

		// Prepare and execute query based on configuration type.
		if ( Constants::EXTERNAL_ENDPOINT === $configuration_type ) {
			$table = $wpdb->prefix . 'mo_external_api_config';
			$query = self::build_prepared_select_query(
				$table,
				$column_names,
				$query_condition,
				$where_values
			);
			$rows  = $wpdb->get_results( $query );
		} elseif ( Constants::GUI_ENDPOINT === $configuration_type || Constants::SQL_ENDPOINT === $configuration_type ) {
			$table = $wpdb->prefix . 'mo_custom_endpoint_config';
			$query = self::build_prepared_select_query(
				$table,
				$column_names,
				$query_condition,
				$where_values
			);
			$rows  = $wpdb->get_results( $query );
		}

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $index => $row ) {
				$row              = (array) $row;
				$unserialized_row = array();
				foreach ( $row as $column_name => $column_value ) {
					$column_value                     = maybe_unserialize( $column_value );
					$unserialized_row[ $column_name ] = $column_value;
				}
				$configurations[ $index ] = $unserialized_row;
			}
		}

		return $configurations;
	}

	/**
	 * Delete a plugin configuration of a configuration_type.
	 *
	 * @param array $row_filter    Values to filter row on.
	 *                                   Above param includes.
	 *                                   - type            - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint). Always Required
	 *                                   - namespace       - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint). Required for getting single connection details Except for external_endpoint
	 *                                   - connection_name - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint). Required for getting single connection details
	 *                                   - method          - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint). Required for getting single connection details
	 *                                   - any other row filter( column name => column value).
	 *
	 * @return int
	 */
	public static function delete_configuration( $row_filter ) {

		global $wpdb;

		$configuration_type = $row_filter['type'];

		// Return array.
		$configurations = array();

		// Creating query condition.
		unset( $row_filter['type'] );
		$query_condition = " WHERE  `type` = '" . esc_sql( $configuration_type ) . "'";
		foreach ( $row_filter as $column_name => $column_value ) {
			$query_condition = $query_condition . ' AND `' . esc_sql( $column_name ) . "` = '" . esc_sql( $column_value ) . "'";
		}

		if ( Constants::EXTERNAL_ENDPOINT === $configuration_type ) {
			$row = $wpdb->query( 'DELETE FROM `' . esc_sql( $wpdb->prefix ) . 'mo_external_api_config` ' . $query_condition ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		} elseif ( Constants::GUI_ENDPOINT === $configuration_type || Constants::SQL_ENDPOINT === $configuration_type ) {

			$row = $wpdb->query( 'DELETE FROM `' . esc_sql( $wpdb->prefix ) . 'mo_custom_endpoint_config` ' . $query_condition ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
		return $row;
	}

	/**
	 * Get all namespaces for a specific configuration type.
	 *
	 * @param array $table_configuration Complete table data for the connection.
	 *                                   Above param includes.
	 *                                   - type            - Connection type (gui_endpoint, 'sql_endpoint, external_endpoint).
	 *
	 * @return array
	 */
	public static function get_all_namespaces( $table_configuration ) {
		global $wpdb;

		$configuration_type = $table_configuration['type'];
		$column_names       = '`namespace`';
		$query_condition    = ''; // No WHERE clause.
		$where_values       = array();

		if ( Constants::EXTERNAL_ENDPOINT === $configuration_type ) {
			$table = $wpdb->prefix . 'mo_external_endpoint_config';
		} elseif ( Constants::GUI_ENDPOINT === $configuration_type || Constants::SQL_ENDPOINT === $configuration_type ) {
			$table = $wpdb->prefix . 'mo_custom_endpoint_config';
		}
		$query = self::build_prepared_select_query(
			$table,
			$column_names,
			$query_condition,
			$where_values
		);
		$rows  = $wpdb->get_results( $query );

		return $rows;
	}

	/**
	 * Get all column names.
	 *
	 * @param  string $table_name The selected table name.
	 * @return array
	 */
	public static function get_all_column_names( $table_name ) {
		global $wpdb;
		$column_names = array();
		$column_names = $wpdb->get_col( $wpdb->prepare( 'DESCRIBE %1s', $table_name ), 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder -- Quotes not required here as it's a table name.
		return $column_names;
	}
	/**
	 * Build a prepared SELECT SQL query for a given table with specified columns, conditions, and parameters.
	 *
	 * @param string $table           The name of the database table (unprefixed). Will be sanitized internally.
	 * @param string $column_names    Comma-separated list of column names to select (e.g., '*' or '`id`, `name`').
	 * @param string $query_condition SQL WHERE clause string (e.g., ' WHERE id = %d AND status = %s').
	 * @param array  $where_values    Array of values to bind to the placeholders in the WHERE clause.
	 *
	 * @return string The prepared SQL query string ready to be executed.
	 */
	public static function build_prepared_select_query( $table, $column_names, $query_condition, $where_values ) {
		global $wpdb;

		// Ensure the table name and column names are properly escaped.
		$sanitized_table           = esc_sql( $table );
		$sanitized_columns         = $column_names;
		$sanitized_query_condition = $query_condition;

		// Build the SQL query string with sanitized identifiers.
		$sql = "SELECT {$sanitized_columns} FROM `{$sanitized_table}`{$sanitized_query_condition}";

		// Prepare only the values, not identifiers.
		if ( ! empty( $where_values ) ) {
			// Unpack $where_values as individual arguments for $wpdb->prepare().
			return call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $where_values ) );
		} else {
			return $sql;
		}
	}


	/**
	 * AJAX callback function to get all column names for a table.
	 *
	 * @return void
	 */
	public static function get_table_columns() {
		if ( Utils::mo_caw_require_capability() ) {
			if ( isset( $_GET['nonce'] ) && check_ajax_referer( 'mo_caw_get_columns_nonce', 'nonce' ) ) {
				if ( isset( $_GET['table'] ) ) {
					$table_name   = sanitize_text_field( wp_unslash( $_GET['table'] ) );
					$column_names = self::get_all_column_names( $table_name );
					wp_send_json_success( $column_names, 200 );
				} else {
					wp_send_json_error( 'Invalid table name', 400 );
				}
			} else {
				wp_send_json_error( 'Invalid nonce', 400 );
			}
		}
	}
}
