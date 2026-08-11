<?php
/**
 * This file handles form submissions related to SQL_API_Creation.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Settings;

use MO_CAW\Common\Constants;
use MO_CAW\Common\DB_Utils;
use MO_CAW\Common\Utils;

/**
 * This class deals with saving common Custom SQL API settings in database.
 */
class SQL_API_Creation {

	/**
	 * The SQL Endpoint configuration
	 *
	 * @var array
	 */
	protected $sql_endpoint_config;

	/**
	 * Class default constructor.
	 */
	public function __construct() {
		$this->form_action_identifier();
	}

	/**
	 * Verify nonce for standard Custom SQL API common settings.
	 *
	 * @return void
	 */
	private function form_action_identifier() {
		if ( Utils::mo_caw_require_capability() ) {
			if ( isset( $_REQUEST['MO_CAW_SQL_API_Creation_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_SQL_API_Creation_Nonce'] ) ), 'MO_CAW_SQL_API_Creation' ) ) {
				$this->save_settings( $_POST );
			} elseif ( isset( $_REQUEST['MO_CAW_SQL_API_Creation_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_SQL_API_Creation_Nonce'] ) ), 'MO_CAW_SQL_API_Creation_Delete' ) ) {
				$this->delete_settings( $_POST );
			} elseif ( isset( $_REQUEST['MO_CAW_SQL_API_Creation_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_SQL_API_Creation_Nonce'] ) ), 'MO_CAW_SQL_API_Creation_Export' ) ) {
				$this->export_settings( $_POST );
			}
			// The else condition is not required here as WordPress handles failure in nonce verification itself.
		}
	}
	/**
	 * Save Custom SQL API Common settings.
	 *
	 * @param  array $post The global $_POST.
	 * @return void
	 */
	private function save_settings( $post ) {
		self::set_settings( $post );

		if ( ! self::is_first( $this->sql_endpoint_config ) ) {
			DB_Utils::update_option( 'mo_caw_message', 'Oops! You can only create one SQL based custom API with the standard plan. Please <a class="text-danger fw-bolder" href="admin.php?page=custom_api_wp_settings&tab=pricing-plan">upgrade</a> to a higher plan to unlock this feature.' );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_DANGER );

			wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=custom-sql-api' );
			exit();
		} else {
			self::save_to_database();
		}

		self::redirect_after_save( $post );
	}

	/**
	 * Save Custom SQL API Common settings.
	 *
	 * @param  array $post The global $_POST.
	 * @return void
	 */
	protected function set_settings( $post ) {
		$this->sql_endpoint_config['type']            = Constants::SQL_ENDPOINT;
		$this->sql_endpoint_config['namespace']       = ! empty( $this->sql_endpoint_config['namespace'] ) ? substr( $this->sql_endpoint_config['namespace'], 0, 15 ) : 'mo/v1';
		$this->sql_endpoint_config['connection_name'] = isset( $post['mo-caw-custom-sql-api-name'] ) ? substr( sanitize_text_field( wp_unslash( $post['mo-caw-custom-sql-api-name'] ) ), 0, 25 ) : '';
		$this->sql_endpoint_config['is_enabled']      = isset( $post['mo-caw-custom-sql-api-is-enabled'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-custom-sql-api-is-enabled'] ) ) : true;
		$this->sql_endpoint_config['method']          = isset( $post['mo-caw-custom-sql-api-method'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-custom-sql-api-method'] ) ) : Constants::HTTP_GET;

		// Check if an endpoint with same parameters exist as GUI endpoint.
		$row_filter = array(
			'connection_name' => $this->sql_endpoint_config['connection_name'],
			'type'            => Constants::GUI_ENDPOINT,
			'method'          => $this->sql_endpoint_config['method'],
			'namespace'       => $this->sql_endpoint_config['namespace'],
		);

		$gui_endpoint_config = DB_Utils::get_configuration( $row_filter );

		if ( ! empty( $gui_endpoint_config ) ) {
			$this->save_in_session( Constants::GUI_ENDPOINT_ALREADY_EXISTS );
		} elseif ( isset( $_SESSION['MO_CAW_SQL_API_Creation_Form_Data'] ) ) {
			unset( $_SESSION['MO_CAW_SQL_API_Creation_Form_Data'] );
			session_destroy();
		}

		$configuration                = $this->sql_endpoint_config['configuration'] ?? array();
		$configuration['table']       = isset( $post['mo-caw-custom-sql-api-table'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-custom-sql-api-table'] ) ) : '';
		$configuration['sql_queries'] = isset( $configuration['sql_queries'] ) ? array_map( 'wp_unslash', ( $configuration['sql_queries'] ) ) : ( isset( $post['mo-caw-custom-sql-api-query'] ) ? (array) array_map( 'wp_unslash', ( $post['mo-caw-custom-sql-api-query'] ) )[0] : array() );

		$response                          = $configuration['response'] ?? array();
		$response['response_type']         = isset( $response['response_type'] ) ? sanitize_text_field( wp_unslash( $response['response_type'] ) ) : Constants::DEFAULT;
		$response['response_content_type'] = isset( $response['response_content_type'] ) ? sanitize_text_field( wp_unslash( $response['response_content_type'] ) ) : Constants::JSON;

		$configuration['response']                  = $response;
		$this->sql_endpoint_config['configuration'] = $configuration;
	}

	/**
	 * Save the configuration to database.
	 *
	 * @return boolean
	 */
	protected function save_to_database() {
		if ( DB_Utils::update_configuration( $this->sql_endpoint_config ) ) {
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
		// Check if it is test mode or not and redirect accordingly.
		$test_mode = $post['mo-caw-custom-sql-api-test-mode'] ?? false;

		if ( filter_var( $test_mode, FILTER_VALIDATE_BOOLEAN ) ) {
			wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=custom-sql-api&action=edit&api-name=' . $this->sql_endpoint_config['connection_name'] . '&method=' . $this->sql_endpoint_config['method'] . '&namespace=' . $this->sql_endpoint_config['namespace'] . '&test-mode=' . $test_mode . '&_wpnonce=' . wp_create_nonce( 'MO_CAW_SQL_API_Creation_Edit_Nonce' ), 302 );
			exit();
		} else {
			wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=custom-sql-api&action=view&api-name=' . $this->sql_endpoint_config['connection_name'] . '&method=' . $this->sql_endpoint_config['method'] . '&namespace=' . $this->sql_endpoint_config['namespace'] . '&_wpnonce=' . wp_create_nonce( 'MO_CAW_SQL_API_Creation_View_Nonce' ), 302 );
			exit();
		}
	}


	/**
	 * Delete Custom SQL API Common settings.
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
			'type'            => Constants::SQL_ENDPOINT,
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

		wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=custom-sql-api', 302 );
		exit();
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

		$_SESSION['MO_CAW_SQL_API_Creation_Form_Data'] = $this->sql_endpoint_config;

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		wp_safe_redirect( $referer );
		exit();
	}

	/**
	 * Export Custom SQL API Common settings.
	 *
	 * @param  array $post The global $_POST.
	 * @return void
	 */
	protected function export_settings( $post ) {
		// TODO: Pending Integration.
	}

	/**
	 * Checks if the current connect is the first connection.
	 *
	 * @param array $current_configuration Configuration fo the current connection.
	 * @return boolean
	 */
	private static function is_first( $current_configuration ) {
		$existing_configuration = DB_Utils::get_configuration( array( 'type' => Constants::SQL_ENDPOINT ) );

		$first_api_configuration = array();

		if ( ! empty( $existing_configuration ) ) {
			$first_api_configuration = $existing_configuration[0];

			$is_first = $first_api_configuration['namespace'] === $current_configuration['namespace'] && $first_api_configuration['method'] === $current_configuration['method'] && $first_api_configuration['connection_name'] === $current_configuration['connection_name'] ? true : false;
		} else {
			$is_first = true;
		}

		return $is_first;
	}
}
