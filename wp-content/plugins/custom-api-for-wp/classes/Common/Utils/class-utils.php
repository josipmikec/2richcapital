<?php
/**
 * This file deals with common functionality logic.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common;

use MO_CAW\Common\DB_Utils;
use MO_CAW\Common\Constants;

/**
 * This Class deals with common functions required by complete plugin.
 */
class Utils {

	const STANDARD_PLAN              = -1;
	const STANDARD_PLAN_DISPLAY_NAME = 'Standard plan';

	/**
	 * Get correct Plugin Level.
	 *
	 * @return int
	 * */
	public static function get_version() {
		return self::STANDARD_PLAN;
	}

	/**
	 * Get current plugin plan display name.
	 *
	 * @return int
	 * */
	public static function get_plugin_display_name() {
		return self::STANDARD_PLAN_DISPLAY_NAME;
	}

	/**
	 * Get all the details related to plugin version as json response.
	 *
	 * @return void
	 */
	public static function get_plugin_version_details() {
		$utils                            = Constants::PLAN_NAMESPACE . '\Utils';
		$version_details['version_level'] = $utils::get_version();
		$version_details['plan_name']     = Constants::PLAN_NAME;

		wp_send_json( $version_details );
	}

	/**
	 * Checks if class exist and if not gives a Common's class name instead.
	 *
	 * @param string $class_namespace Namespace of class.
	 * @param string $class_name      Only class name.
	 *
	 * @return string
	 * */
	public static function validate_class_name( $class_namespace, $class_name ) {

		$complete_class_name = $class_namespace . $class_name;
		$return_class_name   = $complete_class_name;

		if ( ! class_exists( $complete_class_name ) ) {
			$pattern                = '/(?<=MO_CAW\\\)[^\\\]+(?=\\\)/';
			$replacement            = 'Common';
			$common_class_namespace = preg_replace( $pattern, $replacement, $class_namespace );

			$complete_class_name_common = $common_class_namespace . $class_name;

			if ( class_exists( $complete_class_name_common ) ) {
				$return_class_name = $complete_class_name_common;
			}
		}

		return $return_class_name;
	}

	/**
	 * Guard: Ensure the user has required capability or block with error.
	 *
	 * @param string $capability The capability required.
	 */
	public static function mo_caw_require_capability( $capability = 'manage_options' ) {
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( 'You do not have enough permissions to perform this action.', 403 );
		}
		return true;
	}

	/**
	 * Valid html
	 *
	 * Helper function for escaping.
	 *
	 * @param array $args HTML to add to valid args.
	 *
	 * @return array valid html.
	 **/
	public static function get_valid_html( $args = array() ) {
		$retval = array(
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
			'a'      => array(
				'href'   => array(),
				'target' => array(),
			),
		);
		if ( ! empty( $args ) ) {
			return array_merge( $args, $retval );
		}
		return $retval;
	}


	/**
	 * Replace file path slashes with directory separator.
	 *
	 * @param array|string $file_name_list List of file names or a single file name.
	 *
	 * @return array|string
	 */
	public static function replace_directory_separator( $file_name_list ) {
		if ( is_array( $file_name_list ) ) {
			$curated_names = array();
			foreach ( $file_name_list as $file_name ) {
				// Replace all slashes with the correct directory separator.
				$curated_names[] = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $file_name );
			}
			$file_name_list = $curated_names;
		} elseif ( is_string( $file_name_list ) ) {
			// Replace all slashes with the correct directory separator.
			$file_name_list = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $file_name_list );
		}

		return $file_name_list;
	}


	/**
	 * Get Version number
	 */
	public static function get_version_number() {
		$file_data      = get_file_data( \MO_CUSTOM_API_DIR . '/custom-api-for-wordpress.php', array( 'Version' ), 'plugin' );
		$plugin_version = $file_data[0] ?? '1.0.0';
		return $plugin_version;
	}

	/**
	 * This function converts XML data to JSON data.
	 *
	 * @param string $data Holds the value for API response in XML format.
	 *
	 * @return string
	 */
	public static function convert_xml_to_json( $data ) {
		$xml_object = new \SimpleXMLElement( $data );
		$namespaces = $xml_object->getDocNamespaces( true );
		if ( count( $namespaces ) > 0 ) {
			$returned_response = $xml_object->children( $namespaces['soap'] )->Body->children();
		} else {
			$returned_response = simplexml_load_string( $data, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_DTDATTR | LIBXML_DTDLOAD | LIBXML_XINCLUDE | LIBXML_SCHEMA_CREATE );
		}
		return wp_json_encode( $returned_response );
	}

	/**
	 * Runs on plugin activation, will be used to initialize option structures and default values.
	 *
	 * @return void
	 */
	public static function run_on_plugin_activation() {
		DB_Utils::create_custom_tables();
	}

	/**
	 * Runs on plugin deactivation, will be used to destroy option structures and default values.
	 *
	 * @return void
	 */
	public static function run_on_plugin_deactivation() {

		$option_name = base64_encode( 'mo_caw_user_details' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Not used for code obfuscation.
		DB_Utils::delete_option( $option_name );
		DB_Utils::delete_option( 'mo_caw_user_display_details' );
	}
	/**
	 * Organize an array of endpoints by their namespaces and remove the 'namespace' key.
	 *
	 * This function takes an array of endpoints and organizes them into a new array
	 * where each key represents a unique namespace, and the associated value is an
	 * array of endpoints for that namespace without the 'namespace' key.
	 *
	 * @param  array $organize_endpoints The array of endpoints to organize.
	 * @return array An array organized by namespaces with 'namespace' key removed.
	 */
	public static function organize_endpoints_by_namespace( $organize_endpoints ) {
		$namespaces = array();
		$endpoints  = array();

		foreach ( $organize_endpoints as $endpoint ) {
			$namespace = $endpoint['namespace'];

			// Check if the namespace is not already in the $namespaces array.
			if ( ! in_array( $namespace, $namespaces, true ) ) {
				array_push( $namespaces, $namespace );
			}

			// Remove the 'namespace' key from the endpoint array.
			unset( $endpoint['namespace'] );

			// Add the endpoint to the $endpoints array with the namespace removed.
			$endpoints[ $namespace ][] = $endpoint;
		}
		return $endpoints;
	}

	/**
	 * Recursively sanitizes a nested array.
	 *
	 * This function sanitizes each value in the nested array recursively.
	 * If a value is a scalar (non-array), it is sanitized directly.
	 * If a value is an array, the function is called recursively to sanitize it.
	 *
	 * @param array $array_to_sanitize The array to sanitize (passed by reference).
	 * @return void
	 */
	public static function sanitize_nested_array( &$array_to_sanitize ) {
		// If $array_to_sanitize is not an array sanitize scalar value directly and return.
		if ( ! is_array( $array_to_sanitize ) ) {
			$array_to_sanitize = sanitize_text_field( $array_to_sanitize );
			return;
		}

		foreach ( $array_to_sanitize as $key => &$value ) {
			// If the value is an array, sanitize it recursively.
			if ( is_array( $value ) ) {
				self::sanitize_nested_array( $value );
			} else {
				// If the value is not an array, sanitize it directly.
				$array_to_sanitize[ $key ] = sanitize_text_field( $value );
			}
		}
	}

	/**
	 * Encode a given string.
	 *
	 * @param string $str String to encode.
	 * @return string
	 */
	public static function miniorange_encoder( $str ) {
		return $str;
	}

	/**
	 * Decode a given string.
	 *
	 * @param string $str String to decode.
	 * @return string
	 */
	public static function miniorange_decoder( $str ) {
		return $str;
	}

	/**
	 * Function to actually send requests
	 *
	 * @param array  $additional_headers Additional headers to send with default headers.
	 * @param bool   $override_headers   self explanatory.
	 * @param string $field_string       Field String.
	 * @param array  $additional_args    Additional args to send with default headers.
	 * @param bool   $override_args      self explanatory.
	 * @param string $url                URL to send request to.
	 */
	public static function send_request_to_miniorange( $additional_headers = false, $override_headers = false, $field_string = '', $additional_args = false, $override_args = false, $url = '' ) {
		$headers  = array(
			'Content-Type'  => Constants::JSON_HEADER_NAME,
			'charset'       => 'UTF - 8',
			'Authorization' => 'Basic',
		);
		$headers  = ( $override_headers && $additional_headers ) ? $additional_headers : array_unique( array_merge( $headers, $additional_headers ) );
		$args     = array(
			'method'      => \strtoupper( Constants::HTTP_POST ),
			'body'        => $field_string,
			'timeout'     => '20',
			'redirection' => '5',
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => $headers,
			'sslverify'   => true,
		);
		$args     = ( $override_args ) ? $additional_args : array_unique( array_merge( $args, $additional_args ), SORT_REGULAR );
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			return $error_message;
		}
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * This function sends the custom or default response for Custom GET API.
	 *
	 * @param array      $data Contains the SQL response.
	 * @param array|bool $custom_response Contains the custom response array.
	 *
	 * @return void
	 */
	public static function send_custom_api_response( $data, $custom_response ) {
		$status_code = $data['status_code'] ?? 200;
		if ( ! $custom_response ) {
			$data = $data['data'] ?? $data;
			wp_send_json( $data, $status_code );
		}
		$response = self::get_dynamic_response( $data );
		wp_send_json( $response, $status_code );
	}

	/**
	 * This function generates the dynamic response for Custom GET|POST|PUT|DELETE API.
	 *
	 * @param array $data Contains the SQL response.
	 * @return array
	 */
	public static function get_dynamic_response( $data ) {
		return $data;
	}

	/**
	 * Function to verify and curate request body received in the custom API call request.
	 *
	 * @param string $content_type Current request content type.
	 * @param string $body         Raw request body.
	 *
	 * @return array
	 */
	public static function get_custom_api_curated_body( $content_type, $body ) {
		$json_decoded_body = json_decode( $body, true );
		if ( Constants::X_WWW_HEADER_NAME === $content_type ) {
			$body = explode( '&', $body );

			$curated_body = array();
			foreach ( $body as $key_val ) {
				$key_val = explode( '=', $key_val );
				if ( ! empty( $key_val[1] ) ) {
					$curated_body[ $key_val[0] ] = $key_val[1];
				}
			}
			$body = $curated_body;
		} elseif ( Constants::JSON_HEADER_NAME === $content_type && json_last_error() === JSON_ERROR_NONE ) {
			$body = $json_decoded_body;
		} else {
			$body = array();

			$error_response = array(
				'status'            => Constants::ERROR,
				'code'              => 400,
				'error'             => Constants::INVALID_FORMAT,
				'error_description' => 'Required body parameters are missing or not passed in the correct format.',
			);

			wp_send_json( $error_response, 400 );
		}

		return $body;
	}
}
