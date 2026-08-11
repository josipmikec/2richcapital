<?php
/**
 * This file deals with SQL API Creation feature functionality logic.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

// TODO: We can create a common utility package, let’s sat REST utility which can register both the endpoint as well as making a API call based on method, param, body.

namespace MO_CAW\Common\Functionality;

use MO_CAW\Common\Utils;
use MO_CAW\Common\DB_Utils;
use MO_CAW\Common\Constants;

/**
 * Class deals with SQL API Creation feature functionality.
 */
class SQL_API_Creation {


	/**
	 * Class default constructor.
	 */
	public function __construct() {
		$current_hook = current_action();

		if ( Constants::REST_API_INIT_HOOK === $current_hook ) {
			$this->rest_init_functionalities();
		}
	}

	/**
	 * All functionality to be called/executed on rest_api_init.
	 *
	 * @return void
	 */
	protected function rest_init_functionalities() {
		// Code for 'rest_api_init' action.
		$sql_endpoints = DB_Utils::get_configuration( array( 'type' => Constants::SQL_ENDPOINT ) );
		if ( isset( $sql_endpoints[0] ) ) {
			$this->register_rest_routes( $sql_endpoints[0], $this );
		}
	}

	/**
	 * Function to register REST endpoint.
	 *
	 * @param string $sql_endpoint           Endpoint to register.
	 * @param object $function_call_variable Class object to be used for callback function.
	 *
	 * @return void
	 */
	protected function register_rest_routes( $sql_endpoint, $function_call_variable ) {

		// Registering Custom SQL endpoints.

		$namespace = $sql_endpoint['namespace'];

		$args['endpoint_configuration'] = $sql_endpoint;

		register_rest_route(
			$namespace,
			$sql_endpoint['connection_name'],
			array(
				'methods'             => \strtoupper( $sql_endpoint['method'] ),
				'callback'            => array( $function_call_variable, 'sql_endpoint_callback' ),
				'args'                => $args,
				'user'                => wp_get_current_user(),
				'permission_callback' => array( $function_call_variable, 'authenticate_request' ),
			)
		);
	}

	/**
	 * Function to verify if the correct user is accessing the SQL API endpoint.
	 *
	 * @param object $request API Call Request.
	 *
	 * @return boolean|void
	 */
	public function authenticate_request( $request ) {
		return API_Security::authorize_custom_api_request( $request );
	}

	/**
	 * Responds To SQL API Calls.
	 *
	 * @param object $request API Call Request.
	 *
	 * @return void|string returned from run_sql_query_function OR returns error in JSON format.
	 */
	public function sql_endpoint_callback( $request ) {

		$attributes = $request->get_attributes();
		$params     = $request->get_params();
		$body       = $request->get_body();
		$headers    = $request->get_headers();
		$method     = $request->get_method();

		$endpoint_details = $attributes['args']['endpoint_configuration'];
		$endpoint_config  = $endpoint_details['configuration'];

		$sql_query = $endpoint_config['sql_queries'][0];

		$custom_response = ! empty( $endpoint_config['response']['response_content']['success'] ) ? json_decode( $endpoint_config['response']['response_content']['success'], true ) : false;

		if ( ! $endpoint_details['is_enabled'] ) {
			$response = array(
				'status'            => Constants::ERROR,
				'code'              => 403,
				'error'             => Constants::ENDPOINT_DEACTIVATED,
				'error_description' => Constants::API_DISABLED,
			);
			wp_send_json( $response, 403 );
		}

		if ( \strtoupper( Constants::HTTP_GET ) === $method || \strtoupper( Constants::HTTP_DELETE ) === $method ) {

			$error_response = array(
				'status'            => Constants::ERROR,
				'code'              => 400,
				'error'             => Constants::INVALID_FORMAT,
				'error_description' => 'Required arguments are missing or not passed in the correct format.',
			);

			$result = $this->run_sql_query( $params, $sql_query, $error_response, $method );

			if ( Constants::ERROR === $result['status'] ) {
				$custom_response = ! empty( $endpoint_config['response']['response_content']['error'] ) ? json_decode( $endpoint_config['response']['response_content']['error'], true ) : false;
			}

			Utils::send_custom_api_response( $result, $custom_response );

		} elseif ( \strtoupper( Constants::HTTP_POST ) === $method || \strtoupper( Constants::HTTP_PUT ) === $method ) {
			$error_response = array(
				'status'            => Constants::ERROR,
				'code'              => 400,
				'error'             => Constants::INVALID_FORMAT,
				'error_description' => 'Required body parameters are missing or not passed in the correct format.',
			);

			$content_type = ( $headers['content_type'][0] ) ?? '';

			$body = Utils::get_custom_api_curated_body( $content_type, $body );

			if ( empty( $body ) ) {
				wp_send_json( $error_response, 400 );
			}

			$result = $this->run_sql_query( $body, $sql_query, $error_response, $method );

			if ( Constants::ERROR === $result['status'] ) {
				$custom_response = ! empty( $endpoint_config['response']['response_content']['error'] ) ? json_decode( $endpoint_config['response']['response_content']['error'], true ) : false;
			}

			Utils::send_custom_api_response( $result, $custom_response );

		} else {
			$error_response = array(
				'status'            => Constants::ERROR,
				'code'              => 400,
				'error'             => Constants::INVALID_FORMAT,
				'error_description' => 'Requested method is not registered using CUSTOM API for WP plugin.',
			);

			wp_send_json( $error_response, 400 );
		}
	}

	/**
	 * Function to run SQL query end send corresponding response.
	 *
	 * @param array  $dynamic_values Values retrieved from API request.
	 * @param string $sql_query      Raw SQL query to be executed.
	 * @param array  $error_response Default error response.
	 * @param string $method         Request method.
	 *
	 * @return string  In JSON format.
	 */
	private function run_sql_query( $dynamic_values, $sql_query, $error_response, $method ) {

		$sql_query = $this->replace_dynamic_values( $sql_query, $dynamic_values, $error_response );

		return $this->execute_query( $sql_query, $method );
	}

	/**
	 * Function to replace values of the custom params.
	 *
	 * @param string $sql_query      Raw SQL query to be executed.
	 * @param array  $dynamic_values Values retrieved from API request.
	 * @param array  $error_response Default error response.
	 *
	 * @return string
	 */
	protected function replace_dynamic_values( $sql_query, $dynamic_values, $error_response ) {
		global $wpdb;
		if ( empty( $dynamic_values ) || ! is_array( $dynamic_values ) ) {
			return $sql_query;
		}
		$pattern = '/{{([A-Za-z0-9-_]+)}}/';
		preg_match_all( $pattern, $sql_query, $matches );
		$params                = array();
		$expected_placeholders = 0;
		foreach ( $matches[1] as $param_name ) {
			++$expected_placeholders;
			if ( isset( $dynamic_values[ $param_name ] ) ) {
				$params[]  = $dynamic_values[ $param_name ];
				$sql_query = str_replace( '{{' . $param_name . '}}', '%s', $sql_query ); // Use placeholder.
			} else {
				wp_send_json( $error_response, 400 );
			}
		}
		$actual_placeholders = substr_count( $sql_query, '%s' );
		if ( count( $params ) !== $actual_placeholders || $expected_placeholders !== $actual_placeholders ) {
			wp_send_json( $error_response, 400 );
		}

		// Use wpdb->prepare to safely insert values.
		$prepared_query = $wpdb->prepare( $sql_query, ...$params );

		return $prepared_query;
	}

	/**
	 * Function to actually execute SQL query.
	 *
	 * @param string $sql_query Finalized SQL query to be executed.
	 * @param string $method    Request method.
	 *
	 * @return string|int
	 */
	protected function execute_query( $sql_query, $method ) {
		global $wpdb;

		$result = array();

		if ( ! empty( $sql_query ) ) {
			if ( \strtoupper( Constants::HTTP_GET ) === $method ) {
				$result['data'] = $wpdb->get_results( $sql_query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL queries are already prepared and sanitized in the parent function, and there is nonce verification, as well as administrator, check while accepting the queries from the user.
			} else {
				$result['data'] = $wpdb->query( $sql_query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery -- SQL queries are already prepared and sanitized in the parent function, and there is nonce verification, as well as administrator, check while accepting the queries from the user.
			}
		}

		if ( $wpdb->last_error ) {
			$result['status']      = Constants::ERROR;
			$result['status_code'] = 400;
			$result['data']        = $wpdb->last_error;
		} else {
			$result['status']      = Constants::SUCCESS;
			$result['status_code'] = 200;
		}

		return $result;
	}
}
