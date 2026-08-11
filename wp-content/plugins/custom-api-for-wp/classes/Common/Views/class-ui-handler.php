<?php
/**
 * This file deals with handling the plugin view flow logic.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Views;

use MO_CAW\Common\DB_Utils;
use MO_CAW\Common\Constants;
use MO_CAW\Common\Functionality\MO_User as Common_MO_User_Functionality;
use MO_CAW\common\Utils;

/**
 * This class deals with handling the plugin view flow.
 */
class UI_Handler {


	/**
	 * Current tab user is on.
	 *
	 * @var string
	 */
	public $tab;
	/**
	 * Action to be performed.
	 *
	 * @var string
	 */
	public $action;
	/**
	 * Current connection name.
	 *
	 * @var string
	 */
	public $connection_name;

	/**
	 * Class default constructor.
	 */
	public function __construct() {
		$this->tab             = ! empty( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required as input is not taken by user
		$this->action          = ! empty( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required as input is not taken by user.
		$this->connection_name = ! empty( $_GET['connection_name'] ) ? sanitize_text_field( wp_unslash( $_GET['connection_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required as input is not taken by user.

		// To restrict usage of the plugin without license verification.
		$plan_user = Constants::PLAN_NAMESPACE . '\Views\Display_Common';
		$user      = 'User verified';
		if ( 'User verified' !== $user && Constants::USER_ACCOUNT_TAB !== $this->tab ) {
			$this->tab             = Constants::USER_ACCOUNT_TAB;
			$this->action          = '';
			$this->connection_name = '';
		}
	}

	/**
	 * Function to help with selecting tab content.
	 *
	 * @return void
	 */
	private function display_tab_content() {

		$user_functionality = Constants::PLAN_NAMESPACE . '\Functionality\MO_User';

		if ( ! $user_functionality::does_user_has_license() && Constants::PRICING_PLAN_TAB !== $this->tab ) {
			$this->tab = Constants::USER_ACCOUNT_TAB;
		}
		switch ( $this->tab ) {
			case Constants::CUSTOM_API_TAB:
				$api_creation = $this->instance_creator( 'API_Creation' );
				$api_creation->display_api_creation_ui( $this->tab, $this->action );
				break;
			case Constants::CUSTOM_SQL_API_TAB:
				$sql_api_creation = $this->instance_creator( 'SQL_API_Creation' );
				$sql_api_creation->display_sql_api_creation_ui( $this->tab, $this->action );
				break;
			case Constants::CONNECT_EXTERNAL_API_TAB:
				$external_api_connection = $this->instance_creator( 'External_API_Connection' );
				$external_api_connection->display_external_api_connection_ui( $this->tab, $this->action );
				break;
			case Constants::PRICING_PLAN_TAB:
				License::display_license_plans();
				break;
			case Constants::USER_ACCOUNT_TAB:
				$external_api_connection = $this->instance_creator( 'MO_User' );
				$external_api_connection->display_user_account_ui( $this->tab, $this->action );
				break;
			default:
				$api_creation = $this->instance_creator( 'API_Creation' );
				$api_creation->display_api_creation_ui( $this->tab, $this->action );
		}
	}

	/**
	 * Function to help with displaying complete content of the plugin.
	 *
	 * @return void
	 */
	public function display_complete_content() {
		Display_Common::display_top_navbar();
		Display_Common::display_side_navbar( $this->tab );
		MO_User::display_support_popup();
		?>
		<div class="row ps-5 ms-4 me-1">
			<div>
				<?php Display_Common::display_notice(); ?>
			</div>
			<div class="col-md-8 ps-3" >
				<div class="my-3" >
					<?php $this->display_tab_content(); ?>
				</div>
			</div>
			<div class="col-md-4">
				<?php Display_Common::display_side_section(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX callback function to enable/disable an API.
	 *
	 * @return void
	 */
	public static function enable_disable_api() {
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : true;

		if ( Constants::GUI_ENDPOINT === $type ) {
			$nonce = 'mo_caw_custom_api_enable_disable_api';
		} elseif ( Constants::SQL_ENDPOINT === $type ) {
			$nonce = 'mo_caw_custom_sql_api_enable_disable_api';
		} elseif ( Constants::EXTERNAL_ENDPOINT === $type ) {
			$nonce = 'mo_caw_external_api_enable_disable_api';
		}
		if ( Utils::mo_caw_require_capability() ) {
			if ( isset( $_POST['nonce'] ) && check_ajax_referer( $nonce, 'nonce' ) ) {

				$api_name   = isset( $_POST['api-name'] ) ? sanitize_text_field( wp_unslash( $_POST['api-name'] ) ) : '';
				$method     = isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : '';
				$namespace  = isset( $_POST['namespace'] ) ? sanitize_text_field( wp_unslash( $_POST['namespace'] ) ) : '';
				$is_enabled = isset( $_POST['is-enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['is-enabled'] ) ) : true;
				$is_enabled = 'false' === $is_enabled ? false : true;
				if ( ! empty( $api_name ) ) {
					$table_configuration = array(
						'type'            => $type,
						'connection_name' => $api_name,
						'method'          => $method,
						'namespace'       => $namespace,
						'is_enabled'      => $is_enabled,
					);

					$response = DB_Utils::update_enable_of_endpoint( $table_configuration );

					if ( $response ) {
						wp_send_json_success( 'API ' . ( $is_enabled ? 'enabled' : 'disabled' ) . ' successfully', 200 );
					} else {
						wp_send_json_error( 'An error occurred ' . ( $is_enabled ? 'enabling' : 'disabling' ) . ' the API', 400 );
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
	 * Helps in creation of class object for views.
	 *
	 * @param array $class_name Class list without name-space to be loaded.
	 *
	 * @return object
	 */
	public function instance_creator( $class_name ) {
		$name_space     = Constants::PLAN_NAMESPACE . '\Views\\';
		$class_instance = $name_space . $class_name;
		$new_instance   = new $class_instance( $this->action );
		return $new_instance;
	}
}
