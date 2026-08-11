<?php
/**
 * This file deals with miniOrange User functionality logic.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Functionality;

use MO_CAW\Common\Constants;
use MO_CAW\Common\DB_Utils;

/**
 * Class deals with miniOrange User feature functionality.
 */
class MO_User {
	/**
	 * Class constructor.
	 */
	public function __construct() {
	}

	/**
	 * Check if user has a verified license.
	 *
	 * @return boolean
	 */
	public static function does_user_has_license() {
		return true;
	}

	/**
	 * Check if the user is logged in with miniOrange.
	 *
	 * @return boolean
	 */
	public static function is_user_logged_in() {
		$utils          = Constants::PLAN_NAMESPACE . '\Utils';
		$option_name    = base64_encode( 'mo_caw_user_details' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Not used for code obfuscation.
		$user_details   = DB_Utils::get_option( $option_name, array() );
		$array_key      = $utils::miniorange_encoder( 'mo_caw_customer_logged_in' );
		$user_logged_in = ( $user_details[ $array_key ] ) ?? false;
		if ( $user_logged_in ) {
			$user_logged_in = $utils::miniorange_decoder( $user_logged_in );
		}
		return 'Logged in' === $user_logged_in ? true : false;
	}

	/**
	 * Send contact us email via API.
	 *
	 * @param string $email   Email of the admin.
	 * @param string $phone   Phone of the admin.
	 * @param string $query   Query of the admin.
	 * @param string $subject Email subject.
	 * @return string JSON response.
	 */
	public static function contact_us( $email, $phone, $query, $subject ) {
		global $current_user;
		$utils = Constants::PLAN_NAMESPACE . '\Utils';

		wp_get_current_user();

		$option_name  = base64_encode( 'mo_caw_user_details' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Not used for code obfuscation.
		$user_details = DB_Utils::get_option( $option_name, array() );

		$customer_id      = '';
		$customer_api_key = '';

		if ( $user_details ) {
			$customer_id      = $utils::miniorange_encoder( 'mo_caw_customer_id' );
			$customer_api_key = $utils::miniorange_encoder( 'mo_caw_customer_api_key' );
		}

		$customer_key           = ! empty( $user_details[ $customer_id ] ) ? $utils::miniorange_decoder( $user_details[ $customer_id ] ) : Constants::DEFAULT_CUSTOMER_KEY;
		$api_key                = ! empty( $user_details[ $customer_api_key ] ) ? $utils::miniorange_decoder( $user_details[ $customer_api_key ] ) : Constants::DEFAULT_API_KEY;
		$current_time_in_millis = time();
		$url                    = Constants::HOST_NAME . Constants::SEND_NOTIFICATION_API;
		$string_to_hash         = $customer_key . $current_time_in_millis . $api_key;
		$hash_value             = hash( 'sha512', $string_to_hash );
		$from_email             = $email;
		$version                = $utils::get_version_number();
		$query                  = '[Custom API for WP : ' . $version . '] ' . $query;
		$server                 = isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : '';
		$content                = '<div >Hello, <br><br>First Name :' . $current_user->user_firstname . '<br><br>Last  Name :' . $current_user->user_lastname . '   <br><br>Company :<a href="' . $server . '" target="_blank" >' . $server . '</a><br><br>Phone Number :' . $phone . '<br><br>Email :<a href="mailto:' . $from_email . '" target="_blank">' . $from_email . '</a><br><br>Query :' . $query . '</div>';
		$fields                 = array(
			'customerKey' => $customer_key,
			'sendEmail'   => true,
			'email'       => array(
				'customerKey' => $customer_key,
				'fromEmail'   => $from_email,
				'bccEmail'    => 'info@xecurify.com',
				'fromName'    => 'miniOrange',
				'toEmail'     => 'apisupport@xecurify.com',
				'toName'      => 'apisupport@xecurify.com',
				'subject'     => $subject,
				'content'     => $content,
			),
		);

		$field_string             = wp_json_encode( $fields, JSON_UNESCAPED_SLASHES );
		$headers                  = array( 'Content-Type' => Constants::JSON_HEADER_NAME );
		$headers['Customer-Key']  = $customer_key;
		$headers['Timestamp']     = $current_time_in_millis;
		$headers['Authorization'] = $hash_value;

		return $utils::send_request_to_miniorange(
			$headers,
			true,
			$field_string,
			array(),
			false,
			$url
		);
	}

	/**
	 * Verify and create customer account.
	 *
	 * @param string $email     Customer account email.
	 * @param string $password  Customer account password.
	 * @return string
	 */
	public static function create_and_store_customer( $email, $password ) {
		$create_customer_response = self::create_customer( $email, $password );
		$decoded_response         = json_decode( $create_customer_response, true );

		if ( json_last_error() === JSON_ERROR_NONE ) {
			$create_customer_response = $decoded_response;
		}

		$response = array();
		if ( is_array( $create_customer_response ) && isset( $create_customer_response['status'] ) ) {
			if ( Constants::SUCCESS_STATUS === $create_customer_response['status'] ) {
				$response = $create_customer_response;
			} elseif ( Constants::TRANSACTION_LIMIT_EXCEEDED_STATUS === $create_customer_response['status'] ) {
				$response['message'] = 'Too many attempts. Please try after some time.';
			} elseif ( Constants::CUSTOMER_USERNAME_ALREADY_EXISTS_STATUS === $create_customer_response['status'] ) {
				$response['message'] = 'User already exists with this email. Please try with a different email.';
			} else {
				$response['message'] = $create_customer_response['message'];
			}
		} else {
			$response['message'] = 'Customer registration failed. Please try again.';
		}
		return wp_json_encode( $response );
	}

	/**
	 * Send request to create customer to miniOrange.
	 *
	 * @param string $email     Customer account email.
	 * @param string $password  Customer account password.
	 * @return string
	 */
	public static function create_customer( $email, $password ) {
		$utils = Constants::PLAN_NAMESPACE . '\Utils';

		$url    = Constants::HOST_NAME . Constants::CUSTOMER_ADD_API;
		$fields = array(
			'email'          => $email,
			'password'       => $password,
			'areaOfInterest' => 'Custom Api WP',
		);

		$field_string = wp_json_encode( $fields );
		return $utils::send_request_to_miniorange(
			array(),
			false,
			$field_string,
			array(),
			false,
			$url
		);
	}

	/**
	 * Verify and store customer details.
	 *
	 * @param string $email     Customer account email.
	 * @param string $password  Customer account password.
	 * @return string
	 */
	public static function verify_and_store_customer( $email, $password ) {
		$utils = Constants::PLAN_NAMESPACE . '\Utils';

		$verify_customer_response = self::get_customer_key( $email, $password );
		$decoded_response         = json_decode( $verify_customer_response, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$verify_customer_response = $decoded_response;
		}
		if ( is_array( $verify_customer_response ) && isset( $verify_customer_response['status'] ) && Constants::SUCCESS_STATUS === $verify_customer_response['status'] ) {
			$option_name = base64_encode( 'mo_caw_user_details' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Not used for code obfuscation.
			DB_Utils::delete_option( $option_name );
			$user_details = array();
			$key_name     = base64_encode( 'mo_caw_customer_token' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Not used for code obfuscation.

			$user_details[ $key_name ] = base64_encode( $verify_customer_response['token'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Not used for code obfuscation.
			DB_Utils::update_option( $option_name, $user_details );

			// Storing required customer details in DB.
			$array_key   = array( 'mo_caw_customer_email', 'mo_caw_customer_logged_in', 'mo_caw_customer_id', 'mo_caw_customer_api_key' );
			$array_value = array( $email, 'Logged in', $verify_customer_response['id'], $verify_customer_response['apiKey'] );
			$count       = count( $array_key );
			for ( $i = 0; $i < $count; $i++ ) {
				$user_details[ $utils::miniorange_encoder( $array_key[ $i ] ) ] = $utils::miniorange_encoder( $array_value[ $i ] );
			}

			DB_Utils::update_option( $option_name, $user_details );

			// For user details page purpose.
			DB_Utils::update_option(
				'mo_caw_user_display_details',
				array(
					'user_email' => $email,
					'user_id'    => $verify_customer_response['id'],
				)
			);
		}
		return wp_json_encode( $verify_customer_response );
	}

	/**
	 * Function to retrieve customer key from API.
	 *
	 * @param string $email    Customer account email.
	 * @param string $password Customer account password.
	 *
	 * @return string
	 */
	public static function get_customer_key( $email, $password ) {
		$utils = Constants::PLAN_NAMESPACE . '\Utils';

		$url          = Constants::HOST_NAME . Constants::CUSTOMER_KEY_API;
		$fields       = array(
			'email'    => $email,
			'password' => $password,
		);
		$field_string = wp_json_encode( $fields );
		return $utils::send_request_to_miniorange(
			array(),
			false,
			$field_string,
			array(),
			false,
			$url
		);
	}
}
