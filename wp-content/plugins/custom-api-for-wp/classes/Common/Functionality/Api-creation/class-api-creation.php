<?php
/**
 * This file deals with Custom API Creation feature functionality logic.
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
 * Class deals with Custom API Creation feature functionality.
 */
class API_Creation {
	/**
	 * Class default constructor.
	 */
	public function __construct() {

		$current_hook = current_action();

		// Check which action is running.
		if ( Constants::REST_API_INIT_HOOK === $current_hook ) {
			// Code for 'rest_api_init' action.
			$this->rest_init_functionalities();
		}
	}

	/**
	 * All functionality to be called/executed on rest_api_init.
	 *
	 * @return void
	 */
	private function rest_init_functionalities() {

		$this->register_custom_endpoints(); // Registering Custom endpoints.
	}

	/**
	 * Function to register Custom API endpoints.
	 *
	 * @return void
	 */
	protected function register_custom_endpoints() {
		$custom_endpoints = DB_Utils::get_configuration( array( 'type' => Constants::GUI_ENDPOINT ) );

		foreach ( $custom_endpoints as $custom_endpoint ) {
			$namespace     = $custom_endpoint['namespace'];
			$route         = $custom_endpoint['connection_name'];
			$configuration = $custom_endpoint['configuration'];

			if ( ! empty( $configuration['value_specific_filter']['filter_details'] ) ) {

				$filter_details = $configuration['value_specific_filter']['filter_details'];

				$route = $route . '/(?P<' . $filter_details[0]['parameter'] . '>\S+)';
			}
			$args['endpoint_configuration'] = $custom_endpoint;

			register_rest_route(
				$namespace,
				$route,
				array(
					'methods'             => \strtoupper( Constants::HTTP_GET ),
					'callback'            => array( $this, 'custom_endpoint_callback' ),
					'args'                => $args,
					'user'                => wp_get_current_user(),
					'permission_callback' => array( Constants::PLAN_NAMESPACE . '\Functionality\API_Security', 'authorize_custom_api_request' ),
				)
			);
		}
	}


	/**
	 * Responds To Custom API Calls.
	 *
	 * @param object $request API Call Request.
	 *
	 * @return void
	 */
	public function custom_endpoint_callback( $request ) {
		global $wpdb;

		$attributes = $request->get_attributes();
		$method     = $request->get_method();

		$endpoint_details = $attributes['args']['endpoint_configuration'];
		$endpoint_config  = $endpoint_details['configuration'];

		$custom_response_config = ! empty( $endpoint_config['response']['response_content']['success'] ) ? json_decode( $endpoint_config['response']['response_content']['success'], true ) : false;

		if ( ! $endpoint_details['is_enabled'] ) {
			$response = array(
				'status'            => Constants::ERROR,
				'code'              => 403,
				'error'             => Constants::ENDPOINT_DEACTIVATED,
				'error_description' => Constants::API_DISABLED,
			);
			wp_send_json( $response, 403 );
		}

		if ( \strtoupper( Constants::HTTP_GET ) === $method ) {
			$final_get_query = 'SELECT ' . implode( ',', array_map( 'esc_sql', $endpoint_config['request_columns'] ) ) . ' FROM ' . esc_sql( $endpoint_config['table'] );
			$filter_details  = $endpoint_config['value_specific_filter']['filter_details'] ?? array();
			$where_values    = array();

			if ( ! empty( $filter_details ) ) {
				$param_name        = $filter_details[0]['parameter'];
				$column1_value     = $request->get_param( $param_name );
				$column1_value     = esc_sql( urldecode( $column1_value ) );
				$column1_condition = esc_sql( $filter_details[0]['condition'] );
				$final_get_query   = $final_get_query . ' WHERE ' . esc_sql( $filter_details[0]['column'] );

				if ( gettype( $column1_value ) === 'string' ) {
					if ( 'like' === $column1_condition ) {
						$column1_condition = ' LIKE ';
						$column1_value     = '%' . $column1_value . '%';
					} elseif ( 'not-like' === $column1_condition ) {
						$column1_condition = ' NOT LIKE ';
						$column1_value     = '%' . $column1_value . '%';
					}
				}
				$final_get_query .= esc_sql( $column1_condition ) . ' %s';
				$where_values[]   = $column1_value;
			}
			$my_rows = $wpdb->get_results( $wpdb->prepare( $final_get_query, $where_values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Query is now properly prepared
		}

		if ( $wpdb->last_error ) {
			$custom_response_config = ! empty( $endpoint_config['response']['response_content']['error'] ) ? json_decode( $endpoint_config['response']['response_content']['error'], true ) : false;
			$result['status']       = Constants::BAD_REQUEST;
			$result['status_code']  = 400;
			$result['data']         = $wpdb->last_error;

		} else {
			$result['status']      = Constants::SUCCESS;
			$result['status_code'] = 200;
			$result['data']        = $my_rows;

		}

		Utils::send_custom_api_response( $result, $custom_response_config );
	}
}
