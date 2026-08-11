<?php
/**
 * This file handles form submissions related to External_API_Connection.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Settings;

use MO_CAW\Common\DB_Utils;
use MO_CAW\Common\Constants;
use MO_CAW\Common\Utils;

/**
 * This class deals with saving common External API settings in database.
 */
class External_API_Connection {
	/**
	 * The External Endpoint configuration
	 *
	 * @var array
	 */
	protected $external_endpoint_config;
	/**
	 * Class default constructor.
	 */
	public function __construct() {
		$this->form_action_identifier();
	}

	/**
	 * Verify nonce for standard External API common settings.
	 *
	 * @return void
	 */
	private function form_action_identifier() {
		if ( Utils::mo_caw_require_capability() ) {
			if ( isset( $_REQUEST['MO_CAW_External_API_Connection_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_External_API_Connection_Nonce'] ) ), 'MO_CAW_External_API_Connection' ) ) {
				$this->save_settings( $_POST );
			} elseif ( isset( $_REQUEST['MO_CAW_External_API_Connection_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_External_API_Connection_Nonce'] ) ), 'MO_CAW_External_API_Connection_Delete' ) ) {
				$this->delete_settings( $_POST );
			} elseif ( isset( $_REQUEST['MO_CAW_External_API_Connection_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_External_API_Connection_Nonce'] ) ), 'MO_CAW_External_API_Connection_Export' ) ) {
				$this->export_settings( $_POST );
			}
		}
		// The else condition is not required here as WordPress handles failure in nonce verification itself.
	}

	/**
	 * Save External API Common settings.
	 *
	 * @param  array $post The global $_POST.
	 * @return void
	 */
	private function save_settings( $post ) {
		self::set_settings( $post );
		self::save_to_database();
		self::redirect_after_save( $post );
	}

	/**
	 * Set settings from post request to $external_endpoint_config, that stores connection configuration.
	 *
	 * @param array $post The global $_POST.
	 * @return void
	 */
	protected function set_settings( $post ) {
		$this->external_endpoint_config['type']            = Constants::EXTERNAL_ENDPOINT;
		$this->external_endpoint_config['connection_name'] = isset( $post['mo-caw-external-api-name'] ) ? substr( sanitize_text_field( wp_unslash( $post['mo-caw-external-api-name'] ) ), 0, 25 ) : '';
		$this->external_endpoint_config['is_enabled']      = isset( $post['mo-caw-external-api-is-enabled'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-external-api-is-enabled'] ) ) : true;
		$this->external_endpoint_config['method']          = isset( $post['mo-caw-external-api-method'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-external-api-method'] ) ) : Constants::HTTP_GET;

		$this->external_endpoint_config['api_type']            = $this->external_endpoint_config['api_type'] ?? Constants::SIMPLE_API_EXTERNAL_API_TYPE;
		$this->external_endpoint_config['is_used_by']          = $this->external_endpoint_config['is_used_by'] ?? array();
		$this->external_endpoint_config['chained_connections'] = $this->external_endpoint_config['chained_connections'] ?? array();

		$configuration             = $this->external_endpoint_config['configuration'] ?? array();
		$configuration['endpoint'] = isset( $post['mo-caw-external-api-endpoint'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-external-api-endpoint'] ) ) : '';

		$configuration['authorization']['auth_type']   = isset( $post['mo-caw-external-api-request-auth-type'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-external-api-request-auth-type'] ) ) : Constants::NO_AUTHORIZATION;
		$configuration['authorization']['auth_config'] = array();

		$authorization_header_key   = '';
		$authorization_header_value = '';
		switch ( $configuration['authorization']['auth_type'] ) {
			case Constants::BASIC_AUTHORIZATION:
				$username = isset( $post['mo-caw-external-api-basic-authorization-username'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-external-api-basic-authorization-username'] ) ) : '';
				$password = isset( $post['mo-caw-external-api-basic-authorization-password'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-external-api-basic-authorization-password'] ) ) : '';

				if ( empty( $username ) || empty( $password ) ) {
					$configuration['authorization']['auth_type'] = Constants::NO_AUTHORIZATION;
					break;
				}

				$configuration['authorization']['auth_config'] = array(
					$username => $password,
				);

				$authorization_header_key   = 'Authorization';
				$authorization_header_value = 'Basic ' . base64_encode( $username . ':' . $password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Creating Basic Authorization Headers.
				break;
			case Constants::BEARER_TOKEN:
				$bearer_token = isset( $post['mo-caw-external-api-bearer-token-token'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-external-api-bearer-token-token'] ) ) : '';

				if ( empty( $bearer_token ) ) {
					$configuration['authorization']['auth_type'] = Constants::NO_AUTHORIZATION;
					break;
				}

				$configuration['authorization']['auth_config'] = $bearer_token;

				$authorization_header_key   = 'Authorization';
				$authorization_header_value = 'Bearer ' . $bearer_token;
				break;
			case Constants::API_KEY_AUTHENTICATION:
				$authorization_header_key   = isset( $post['mo-caw-external-api-api-key-authentication-key'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-external-api-api-key-authentication-key'] ) ) : '';
				$authorization_header_value = isset( $post['mo-caw-external-api-api-key-authentication-value'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-external-api-api-key-authentication-value'] ) ) : '';

				if ( empty( $authorization_header_key ) || empty( $authorization_header_value ) ) {
					$configuration['authorization']['auth_type'] = Constants::NO_AUTHORIZATION;
					break;
				}

				$configuration['authorization']['auth_config'] = array(
					$authorization_header_key => $authorization_header_value,
				);
				break;
			default:
				break;
		}

		$header_key   = isset( $post['mo-caw-external-api-headers-key'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post['mo-caw-external-api-headers-key'] ) ) : array();
		$header_value = isset( $post['mo-caw-external-api-headers-val'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post['mo-caw-external-api-headers-val'] ) ) : array();

		$old_authorization_type = isset( $post['mo-caw-old-authorization-type'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-old-authorization-type'] ) ) : Constants::NO_AUTHORIZATION;
		if ( Constants::NO_AUTHORIZATION !== $old_authorization_type ) {
			unset( $header_key[0] );
			unset( $header_value[0] );
		}

		if ( ! empty( $authorization_header_key ) && ! empty( $authorization_header_value ) ) {
			$has_empty_key   = in_array( '', $header_key, true );
			$has_empty_value = in_array( '', $header_value, true );

			if ( $has_empty_key || $has_empty_value ) {
				$header_key   = array_filter( $header_key );
				$header_value = array_filter( $header_value );
			}

			array_unshift( $header_key, $authorization_header_key );
			array_unshift( $header_value, $authorization_header_value );
		}

		$configuration['header'] = array_combine( $header_key, $header_value );

		$configuration['body']['request_type'] = isset( $post['mo-caw-external-api-request-body'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-external-api-request-body'] ) ) : Constants::NO_BODY;

		switch ( $configuration['body']['request_type'] ) {
			case Constants::X_WWW_FORM_URLENCODED:
				$body_keys   = isset( $post['mo-caw-external-api-x-www-form-urlencoded-body-key'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post['mo-caw-external-api-x-www-form-urlencoded-body-key'] ) ) : array();
				$body_values = isset( $post['mo-caw-external-api-x-www-form-urlencoded-body-value'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post['mo-caw-external-api-x-www-form-urlencoded-body-value'] ) ) : array();

				$configuration['body']['request_value'] = array_combine( $body_keys, $body_values );
				break;
			case Constants::JSON:
				$json_body = isset( $post['mo-caw-external-api-json-body'] ) ? sanitize_textarea_field( wp_unslash( $post['mo-caw-external-api-json-body'] ) ) : '';

				$configuration['body']['request_value'] = json_decode( wp_json_encode( $json_body ), true, JSON_PRETTY_PRINT );
				break;
			case Constants::GRAPH_QL:
				$graphql_body = isset( $post['mo-caw-external-api-graphql-body'] ) ? sanitize_textarea_field( wp_unslash( $post['mo-caw-external-api-graphql-body'] ) ) : '';

				$configuration['body']['request_value'] = $graphql_body;
				break;
			case Constants::XML:
				$xml_body = isset( $post['mo-caw-external-api-xml-body'] ) ? wp_unslash( $post['mo-caw-external-api-xml-body'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitizing removes the XML tags.

				$configuration['body']['request_value'] = $xml_body;
				break;
			default:
				break;
		}
		$this->external_endpoint_config['configuration'] = $configuration;
	}

	/**
	 * Save the configuration to database.
	 *
	 * @return boolean
	 */
	protected function save_to_database() {
		if ( DB_Utils::update_configuration( $this->external_endpoint_config ) ) {
			DB_Utils::update_option( 'mo_caw_message', Constants::SAVE_SUCCESS );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_SUCCESS );
			return true;
		} else {
			DB_Utils::update_option( 'mo_caw_message', Constants::SAVE_ERROR );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_DANGER );
			return false;
		}
	}

	/**
	 * Check if it is test mode or not and redirect accordingly.
	 *
	 * @param array $post The global $_POST.
	 *
	 * @return void
	 */
	protected function redirect_after_save( $post ) {
		$test_mode = $post['mo-caw-external-api-test-mode'] ?? false;

		if ( filter_var( $test_mode, FILTER_VALIDATE_BOOLEAN ) ) {
			wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=connect-external-api&action=edit&api-name=' . $this->external_endpoint_config['connection_name'] . '&method=' . $this->external_endpoint_config['method'] . '&api-type=' . $this->external_endpoint_config['api_type'] . '&test-mode=' . $test_mode . '&output-format=json&_wpnonce=' . wp_create_nonce( 'MO_CAW_External_API_Connection_Edit_Nonce' ), 302 );
			exit();
		} else {
			wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=connect-external-api&action=edit&api-name=' . $this->external_endpoint_config['connection_name'] . '&method=' . $this->external_endpoint_config['method'] . '&api-type=' . $this->external_endpoint_config['api_type'] . '&_wpnonce=' . wp_create_nonce( 'MO_CAW_External_API_Connection_Edit_Nonce' ), 302 );
			exit();
		}
	}

	/**
	 * Delete Custom GUI API Common settings.
	 *
	 * @param  array $post The global $_POST.
	 * @return void
	 */
	protected function delete_settings( $post ) {
		$api_name = isset( $post['api-name'] ) ? sanitize_text_field( wp_unslash( $post['api-name'] ) ) : '';
		$method   = isset( $post['method'] ) ? sanitize_text_field( wp_unslash( $post['method'] ) ) : '';

		$table_configuration = array(
			'connection_name' => $api_name,
			'type'            => Constants::EXTERNAL_ENDPOINT,
			'method'          => $method,
		);

		if ( DB_Utils::delete_configuration( $table_configuration ) ) {
			DB_Utils::update_option( 'mo_caw_message', Constants::DELETION_SUCCESS );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_SUCCESS );
		} else {
			DB_Utils::update_option( 'mo_caw_message', Constants::DELETION_ERROR );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_DANGER );
		}

		wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=connect-external-api', 302 );
		exit();
	}

	/**
	 * Export Custom GUI API Common settings.
	 *
	 * @param  array $post The global $_POST.
	 * @return void
	 */
	protected function export_settings( $post ) {
		// TODO: Pending Integration.
	}
}
