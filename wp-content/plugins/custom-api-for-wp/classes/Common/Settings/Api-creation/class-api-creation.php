<?php
/**
 * This file handles form submissions related to API_Creation.
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
 * This class deals with saving common Custom GUI API settings in database.
 */
class API_Creation {


	/**
	 * The GUI Endpoint configuration
	 *
	 * @var array
	 */
	protected $gui_endpoint_config;
	/**
	 * Class default constructor.
	 */
	public function __construct() {
		$this->form_action_identifier();
	}

	/**
	 * Verify nonce for standard Custom GUI API common settings.
	 *
	 * @return void
	 */
	private function form_action_identifier() {
		if ( Utils::mo_caw_require_capability() ) {
			if ( isset( $_REQUEST['MO_CAW_API_Creation_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_API_Creation_Nonce'] ) ), 'MO_CAW_API_Creation' ) ) {
				$this->save_settings( $_POST );
			} elseif ( isset( $_REQUEST['MO_CAW_API_Creation_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_API_Creation_Nonce'] ) ), 'MO_CAW_API_Creation_Delete' ) ) {
				$this->delete_settings( $_POST );
			} elseif ( isset( $_REQUEST['MO_CAW_API_Creation_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_API_Creation_Nonce'] ) ), 'MO_CAW_API_Creation_Export' ) ) {
				$this->export_settings( $_POST );
			}
			// The else condition is not required here as WordPress handles failure in nonce verification itself.
		}
	}

	/**
	 * Save Custom GUI API Common settings.
	 *
	 * @param  array $post The global $_POST.
	 * @return void
	 */
	protected function save_settings( $post ) {
		$this->gui_endpoint_config['type']            = Constants::GUI_ENDPOINT;
		$this->gui_endpoint_config['namespace']       = ! empty( $this->gui_endpoint_config['namespace'] ) ? substr( $this->gui_endpoint_config['namespace'], 0, 15 ) : 'mo/v1';
		$this->gui_endpoint_config['connection_name'] = isset( $post['mo-caw-custom-api-name'] ) ? substr( sanitize_text_field( wp_unslash( $post['mo-caw-custom-api-name'] ) ), 0, 25 ) : '';
		$this->gui_endpoint_config['is_enabled']      = isset( $post['mo-caw-custom-api-is-enabled'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-custom-api-is-enabled'] ) ) : true;
		$this->gui_endpoint_config['method']          = $this->gui_endpoint_config['method'] ?? Constants::HTTP_GET;

		// Check if an endpoint with same parameters exist as SQL endpoint.
		$row_filter = array(
			'connection_name' => $this->gui_endpoint_config['connection_name'],
			'type'            => Constants::SQL_ENDPOINT,
			'method'          => $this->gui_endpoint_config['method'],
			'namespace'       => $this->gui_endpoint_config['namespace'],
		);

		$sql_endpoint_config = DB_Utils::get_configuration( $row_filter );

		if ( ! empty( $sql_endpoint_config ) ) {
			$this->save_in_session( Constants::SQL_ENDPOINT_ALREADY_EXISTS );
		}

		$configuration                    = $this->gui_endpoint_config['configuration'] ?? array();
		$configuration['table']           = isset( $post['mo-caw-custom-api-table'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-custom-api-table'] ) ) : '';
		$configuration['request_columns'] = isset( $post['mo-caw-custom-api-columns'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post['mo-caw-custom-api-columns'] ) ) : array();

		$response                          = $configuration['response'] ?? array();
		$response['response_type']         = isset( $response['response_type'] ) ? sanitize_text_field( wp_unslash( $response['response_type'] ) ) : Constants::DEFAULT;
		$response['response_content_type'] = isset( $response['response_content_type'] ) ? sanitize_text_field( wp_unslash( $response['response_content_type'] ) ) : Constants::JSON;
		$response['response_content']      = isset( $response['response_content'] ) ? array_map( 'sanitize_text_field', wp_unslash( $response['response_content'] ) ) : array();

		$value_specific_filter           = $configuration['value_specific_filter'] ?? array();
		$value_specific_filter_column    = isset( $post['mo-caw-custom-api-specific-filter-column'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post['mo-caw-custom-api-specific-filter-column'] ) ) : array();
		$value_specific_filter_condition = isset( $post['mo-caw-custom-api-specific-filter-condition'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post['mo-caw-custom-api-specific-filter-condition'] ) ) : array();
		$value_specific_filter_parameter = isset( $post[ 'mo-caw-custom-api-specific-filter-parameter-' . $this->gui_endpoint_config['method'] ] ) ? array_map( 'sanitize_text_field', wp_unslash( $post[ 'mo-caw-custom-api-specific-filter-parameter-' . $this->gui_endpoint_config['method'] ] ) ) : array();

		if ( ! ( empty( $value_specific_filter_column[0] ) && empty( $value_specific_filter_condition[0] ) ) ) {
			$filter_details['column']    = $value_specific_filter_column[0];
			$filter_details['condition'] = $value_specific_filter_condition[0];

			if ( Constants::HTTP_POST !== $this->gui_endpoint_config['method'] ) {				
				$filter_details['parameter'] = $value_specific_filter_parameter[0];
			}

			$value_specific_filter['filter_details'] = $value_specific_filter['filter_details'] ?? array();
			array_unshift( $value_specific_filter['filter_details'], $filter_details );
		} elseif ( count( $value_specific_filter_column ) > 1 ) {
			// When filter_details is greater than 1 but the first array is empty, others should be adjusted accordingly.
			$value_specific_filter['filter_details'] = array_values( $value_specific_filter['filter_details'] );
			array_shift( $value_specific_filter['filter_relation'] );
		}

		$configuration['response']                  = $response;
		$configuration['value_specific_filter']     = $value_specific_filter;
		$this->gui_endpoint_config['configuration'] = $configuration;

		if ( empty( $configuration['request_columns'] ) ) {
			$this->save_in_session( Constants::NO_REQUEST_COLUMN_SELECTED );
		}
		if ( count( $value_specific_filter_parameter ) !== count( array_unique( $value_specific_filter_parameter ) ) ) {
			$this->save_in_session( Constants::DUPLICATE_PARAMETER_NAME );
		}
		foreach ( $value_specific_filter_parameter as $param ) {
			if ( is_numeric( $param ) ) {
				$this->save_in_session( Constants::NO_NUMERIC_PARAMETER_NAME );
			}
		}
		if ( isset( $_SESSION['MO_CAW_API_Creation_Form_Data'] ) ) {
			unset( $_SESSION['MO_CAW_API_Creation_Form_Data'] );
			session_destroy();
		}

		if ( DB_Utils::update_configuration( $this->gui_endpoint_config ) ) {
			DB_Utils::update_option( 'mo_caw_message', Constants::SAVE_SUCCESS );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_SUCCESS );
		} else {
			DB_Utils::update_option( 'mo_caw_message', Constants::SAVE_ERROR );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_DANGER );
		}

		// Check if it is test mode or not and redirect accordingly.
		$test_mode = $post['mo-caw-custom-api-test-mode'] ?? false;

		if ( filter_var( $test_mode, FILTER_VALIDATE_BOOLEAN ) ) {
			wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=custom-api&action=edit&api-name=' . $this->gui_endpoint_config['connection_name'] . '&method=' . $this->gui_endpoint_config['method'] . '&namespace=' . $this->gui_endpoint_config['namespace'] . '&test-mode=' . $test_mode . '&_wpnonce=' . wp_create_nonce( 'MO_CAW_API_Creation_Edit_Nonce' ), 302 );
			exit();
		} else {
			wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=custom-api&action=view&api-name=' . $this->gui_endpoint_config['connection_name'] . '&method=' . $this->gui_endpoint_config['method'] . '&namespace=' . $this->gui_endpoint_config['namespace'] . '&_wpnonce=' . wp_create_nonce( 'MO_CAW_API_Creation_View_Nonce' ), 302 );
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
		$api_name  = isset( $post['api-name'] ) ? sanitize_text_field( wp_unslash( $post['api-name'] ) ) : '';
		$method    = isset( $post['method'] ) ? sanitize_text_field( wp_unslash( $post['method'] ) ) : '';
		$namespace = isset( $post['namespace'] ) ? sanitize_text_field( wp_unslash( $post['namespace'] ) ) : '';

		$table_configuration = array(
			'connection_name' => $api_name,
			'type'            => Constants::GUI_ENDPOINT,
			'method'          => $method,
			'namespace'       => $namespace,
		);

		if ( DB_Utils::delete_configuration( $table_configuration ) ) {
			DB_Utils::update_option( 'mo_caw_message', Constants::DELETION_SUCCESS );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_SUCCESS );
		} else {
			DB_Utils::update_option( 'mo_caw_message', Constants::DELETION_ERROR );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_DANGER );
		}

		wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=custom-api', 302 );
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

	/**
	 * Save data in session temporarily and throw warning.
	 *
	 * @param string $warning_message The warning message to display.
	 * @return void
	 */
	private function save_in_session( $warning_message ) {
		DB_Utils::update_option( 'mo_caw_message', $warning_message );
		DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_WARNING );

		$_SESSION['MO_CAW_API_Creation_Form_Data'] = $this->gui_endpoint_config;
		$row_filter = array(
			'connection_name' => $this->gui_endpoint_config['connection_name'],
			'type'            => Constants::GUI_ENDPOINT,
			'method'          => $this->gui_endpoint_config['method'],
			'namespace'       => $this->gui_endpoint_config['namespace'],
		);
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( DB_Utils::get_configuration( $row_filter ) ) {
			wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=custom-api&action=edit&api-name=' . $this->gui_endpoint_config['connection_name'] . '&method=' . $this->gui_endpoint_config['method'] . '&namespace=' . $this->gui_endpoint_config['namespace'] . '&_wpnonce=' . wp_create_nonce( 'MO_CAW_API_Creation_Edit_Nonce' ), 302 );
			unset( $_SESSION['MO_CAW_API_Creation_Form_Data'] );
			exit();
		} else {
			wp_safe_redirect( $referer );
			exit();
		}
	}
}
