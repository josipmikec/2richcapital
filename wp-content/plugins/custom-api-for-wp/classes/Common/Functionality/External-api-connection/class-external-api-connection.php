<?php
/**
 * This file deals with External API Creation feature functionality logic.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Functionality;

use Exception;
use MO_CAW\Common\Constants;
use MO_CAW\Common\DB_Utils;
use MO_CAW\Common\Utils;


/**
 * Class deals with External API Creation feature functionality.
 */
class External_API_Connection {

	/**
	 * Class default constructor.
	 */
	public function __construct() {

		$current_hook = current_action();

		// Check which action is running.
		if ( Constants::INIT_HOOK === $current_hook ) {
			// Code for 'rest_api_init' action.
			$this->init_functionalities();
		}
	}

	/**
	 * All functionality to be called/executed on wp_init.
	 *
	 * @return void
	 */
	protected function init_functionalities() {

		$external_api_class = Utils::validate_class_name( Constants::PLAN_NAMESPACE . '\Functionality\\', 'External_API_Connection' );

		// TODO : return statements are not ye handled properly for this filter. It must return JSON encoded response for all cases, for errors it should return json encoded string.
		/**
		 * Filter to make API calls.
		 *
		 * @param array $connection_name  API request Name.
		 * @param array $dynamic_body     Additional API request body.
		 * @param array $dynamic_header   Additional API request headers.
		 * @param array $dynamic_endpoint New API request endpoint.
		 */
		add_filter( 'ExternalApiHook', array( $external_api_class, 'external_api_initiate' ), 10, 4 );

		/**
		 * Filter to make API calls.
		 *
		 * @param array $request_arguments Request arguments will contain two required keys which are api_name, and request_method.
		 */
		add_filter( 'mo_caw_execute_external_api', array( $external_api_class, 'handle_api_execution_request' ), 10, 3 );
	}

	/**
	 * Handles execution of External API for new hook.
	 *
	 * @param  string $api_name Connection name for which the API call will be made.
	 * @param  string $request_method Request method type of the api connection which needs to be executed.
	 * @param  string $api_request_arguments Additional request arguments that will be sent for API request like headers, body, url, timeout, cookies etc (Optional).
	 *
	 * @throws Exception Throws an exception in case of executing an API.
	 *
	 * @return void|string
	 */
	public static function handle_api_execution_request( $api_name, $request_method, $api_request_arguments = array() ) {
		return self::execute_external_api( $api_name, $request_method, $api_request_arguments );
	}

	/**
	 * Function to initiate external API execution.
	 *
	 * @param string $api_name  Name of the connection to executed.
	 * @param array  $dynamic_body     Body parameter if passed explicitly (Optional).
	 * @param array  $dynamic_header   Header parameter if passed explicitly (Optional).
	 * @param string $dynamic_endpoint Endpoint if passed explicitly (Optional).
	 *
	 * @return string
	 */
	public static function external_api_initiate( $api_name, $dynamic_body = array(), $dynamic_header = array(), $dynamic_endpoint = '' ) {

		$api_config = self::get_api_connection_configuration( $api_name );

		$request_method = $api_config['method'];

		$api_request_arguments                      = array();
		$api_request_arguments['api_configuration'] = $api_config;
		$api_request_arguments['request_url']       = ! empty( $dynamic_endpoint ) ? $dynamic_endpoint : '';
		$api_request_arguments['request_headers']   = ! empty( $dynamic_header ) ? $dynamic_header : array();
		$api_request_arguments['request_body']      = ! empty( $dynamic_body ) ? $dynamic_body : array();

		return self::execute_external_api( $api_name, $request_method, $api_request_arguments );
	}

	/**
	 * Handles execution of External API.
	 *
	 * @param  string $api_name Connection name for which the API call will be made.
	 * @param  string $request_method Request method type of the api connection which needs to be executed.
	 * @param  string $api_request_arguments Additional request arguments that will be sent for API request like headers, body, url, timeout, cookies etc (Optional).
	 *
	 * @throws Exception Throws an exception in case of executing an API.
	 *
	 * @return void|string
	 */
	public static function execute_external_api( $api_name, $request_method, $api_request_arguments = array() ) {

		try {

			$api_config = empty( $api_request_arguments['api_configuration'] ) ? self::get_api_connection_configuration( $api_name, $request_method ) : $api_request_arguments['api_configuration'];

			if ( ! $api_config['is_enabled'] ) {
				throw new Exception( Constants::API_DISABLED );
			}

			$endpoint_config = $api_config['configuration'];

			// URL.
			$request_url = self::get_url( $api_request_arguments, $endpoint_config );

			// HEADERS.
			$request_headers = self::get_headers( $api_request_arguments, $endpoint_config );

			// BODY.
			$request_body = self::get_body( $api_request_arguments, $endpoint_config );

			// API Request arguments.
			$remote_request_arguments['method']  = strtoupper( $api_config['method'] );
			$remote_request_arguments['headers'] = $request_headers;
			$remote_request_arguments['body']    = $request_body;
			$remote_request_arguments['timeout'] = ! empty( $api_request_arguments['timeout'] ) ? $api_request_arguments['timeout'] : 120;

			// API CALL.
			$api_response = wp_remote_request( $request_url, $remote_request_arguments );

			// API response.
			if ( is_wp_error( $api_response ) ) {
				throw new Exception( $api_response->get_error_message() );
			}

			return ( isset( $endpoint_config['body']['request_type'] ) && Constants::XML === $endpoint_config['body']['request_type'] ) ? Utils::convert_xml_to_json( $api_response['body'] ) : $api_response['body'];

		} catch ( Exception $e ) {
			return wp_json_encode(
				array(
					'error'    => Constants::EXTERNAL_API_EXCEPTION_PREFIX . $e->getMessage(),
					'mo_error' => true,
				)
			);
		}
	}

	/**
	 * The function returns the request URL, using the provided URL or the default URL from the API
	 * configuration if the provided URL is empty.
	 *
	 * @param array $additional_arguments The `request_url` parameter is the URL that is passed as an argument to the
	 * `get_url` function. It represents the URL that needs to be retrieved or accessed.
	 * @param array $endpoint_config The `endpoint_config` parameter is an array that contains the configuration settings
	 * for the API. It likely includes information such as the API's base URL, request header,
	 * and other settings.
	 *
	 * @return string Final request URL for the API request.
	 */
	public static function get_url( $additional_arguments, $endpoint_config ) {
		if ( empty( $additional_arguments['request_url'] ) ) {
			$request_url = $endpoint_config['endpoint'];
		} else {
			$request_url = $additional_arguments['request_url'];
		}
		return $request_url;
	}

	/**
	 * The function `get_headers` returns an array of request headers based on the provided request headers
	 * and API configuration.
	 *
	 * @param array $additional_arguments An array containing the headers for the API request.
	 * @param array $endpoint_config An array containing the configuration settings for the API. It includes the
	 * headers and body type for the request.
	 *
	 * @return array Final Request headers for the API request.
	 */
	public static function get_headers( $additional_arguments, $endpoint_config ) {

		if ( empty( $additional_arguments['request_headers'] ) ) {
			$request_headers = $endpoint_config['header'];
		} else {
			try {
				$request_headers = array_merge( $endpoint_config['header'], $additional_arguments['request_headers'] );
			} catch ( Exception $e ) {
				wp_send_json_error( Constants::EXTERNAL_API_EXCEPTION_PREFIX . Constants::INVALID_HEADERS_FORMAT, 400 );
			}
		}

		$request_body_type = $endpoint_config['body']['request_type'];

		switch ( $request_body_type ) {
			case Constants::XML:
				$request_headers['Content-Type'] = Constants::XML_HEADER_NAME;
				break;
			case Constants::JSON:
				$request_headers['Content-Type'] = Constants::JSON_HEADER_NAME;
				break;
			case Constants::X_WWW_FORM_URLENCODED:
				$request_headers['Content-Type'] = Constants::X_WWW_HEADER_NAME;
				break;
			case Constants::GRAPH_QL:
				$request_headers['Content-Type'] = Constants::JSON_HEADER_NAME;
				break;
		}

		return $request_headers;
	}

	/**
	 * The function "get_body" returns the request body, either from the input parameter or from the API
	 * configuration.
	 *
	 * @param array $additional_arguments This parameter is the body of the HTTP request. It is the data
	 * that will be sent in the body of the request to the API.
	 * @param array $endpoint_config The `endpoint_config` parameter is an array that contains the configuration settings
	 * for the API. It likely includes information such as the API endpoint, headers, and the request body.
	 *
	 * @return string|array Final Request Body for the API request.
	 */
	public static function get_body( $additional_arguments, $endpoint_config ) {

		if ( empty( $additional_arguments['request_body'] ) ) {
			$request_body = $endpoint_config['body']['request_value'] ?? '';
		} else {
			$request_body = $additional_arguments['request_body'];
		}

		return $request_body;
	}

	/**
	 * AJAX callback function to get API response of the external API and return is requested format.
	 *
	 * @return void
	 */
	public function get_api_response() {
		if ( Utils::mo_caw_require_capability() ){
			if ( isset( $_POST['nonce'] ) && check_ajax_referer( 'mo_caw_external_api_get_response', 'nonce' ) ) {
				if ( isset( $_POST['api-name'] ) ) {
					$api_name      = sanitize_text_field( wp_unslash( $_POST['api-name'] ) );
					$api_method    = isset( $_POST['api-method'] ) ? sanitize_text_field( wp_unslash( $_POST['api-method'] ) ) : '';
					$output_format = isset( $_POST['output-format'] ) ? sanitize_text_field( wp_unslash( $_POST['output-format'] ) ) : Constants::JSON;

					$response = self::execute_external_api( $api_name, $api_method );

					switch ( $output_format ) {
						case Constants::JSON:
							if ( json_decode( $response ) ) {
								wp_send_json_success( json_decode( $response, true, JSON_PRETTY_PRINT ), 200 );
							} else {
								wp_send_json_error( 'Invalid JSON response', 400 );
							}
							break;
						case Constants::TABLE:
							$html  = '';
							$html  = '<table id="mo-caw-test-configuration" class="table table-bordered text-center">
											<tr class="table-light"><th>Attribute Name</th><th>Attribute Value</th></tr>';
							$html .= self::generate_api_response_table_rows( '', json_decode( $response ) );
							$html .= '</table>';
							wp_send_json_success( $html, 200 );
							break;
						case Constants::RAW:
							wp_send_json( $response );
							break;
					}
				} else {
					wp_send_json_error( 'Invalid API name', 400 );
				}
			} else {
				wp_send_json_error( 'Invalid nonce', 400 );
			}
		}
	}

	/**
	 * Maps attributes so that data can be displayed in tabular format.
	 *
	 * @param string $nested_prefix        Prefix/parent keys to be represented in the tabular display.
	 * @param array  $api_response_details API response.
	 */
	public static function generate_api_response_table_rows( $nested_prefix, $api_response_details ) {
		$api_response_key = array();

		$html = '';

		foreach ( $api_response_details as $key => $resource ) {
			if ( is_array( $resource ) || is_object( $resource ) ) {
				if ( ! empty( $nested_prefix ) ) {
					$nested_prefix .= '->';
				}
				$html         .= self::generate_api_response_table_rows( $nested_prefix . $key, $resource );
				$nested_prefix = rtrim( $nested_prefix, '->' );
			} else {
				$completekey = '';
				$html       .= '<tr><td>';
				if ( ! empty( $nested_prefix ) ) {
					$html       .= esc_html( $nested_prefix ) . '->';
					$completekey = $nested_prefix . '->';
				}
				if ( is_bool( $resource ) ) {
					$resource = $resource ? 'true' : 'false';
				}
				$html       .= esc_html( $key ) . '</td><td>' . esc_html( $resource ) . '</td></tr>';
				$completekey = $completekey . $key;

				array_push( $api_response_key, $completekey );
			}
		}

		return $html;
	}


	/**
	 * Fetch API configuration from DB.
	 *
	 * @param string $api_name API name for which the configuration is requested.
	 * @param bool   $request_method Whether to fetch the configuration on the basis of method column in DB.
	 *
	 * @throws Exception Throws an exception in case of executing an API.
	 *
	 * @return array
	 */
	public static function get_api_connection_configuration( $api_name, $request_method = false ) {

		try {

			$request_arguments = array(
				'type'            => Constants::EXTERNAL_ENDPOINT,
				'connection_name' => $api_name,
				'method'          => $request_method,
				'api_type'        => Constants::SIMPLE_API_EXTERNAL_API_TYPE,
			);

			if ( empty( $request_method ) ) {
				unset( $request_arguments['method'] );
			}

			$api_config = DB_Utils::get_configuration( $request_arguments )[0];

			if ( empty( $api_config ) ) {
				throw new Exception( Constants::EXTERNAL_API_NAME_NOT_FOUND );
			}

			return $api_config;

		} catch ( Exception $e ) {
			wp_send_json_error( Constants::EXTERNAL_API_EXCEPTION_PREFIX . $e->getMessage(), 400 );
		}
	}
}
