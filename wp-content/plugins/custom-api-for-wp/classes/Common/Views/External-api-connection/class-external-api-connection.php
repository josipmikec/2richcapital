<?php
/**
 * This file handles display content for to External_API_Connection.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Views;

use MO_CAW\Common\DB_Utils;
use MO_CAW\Common\Constants;
use MO_CAW\Common\Utils;

/**
 * This class deals with rendering common views for External API.
 */
class External_API_Connection {

	/**
	 * API Type
	 *
	 * @var string
	 */
	private $type = Constants::EXTERNAL_ENDPOINT;
	/**
	 * Name of the API.
	 *
	 * @var string
	 */
	private $api_name = '';
	/**
	 * API Endpoint.
	 *
	 * @var string
	 */
	private $endpoint = '';
	/**
	 * API request method.
	 *
	 * @var string
	 */
	private $method = Constants::HTTP_GET;
	/**
	 * The APIs on which current API depends.
	 *
	 * @var array
	 */
	private $dependent_apis = array();
	/**
	 * Roles allowed to access the API.
	 *
	 * @var array
	 */
	private $blocked_roles = array();
	/**
	 * Simple or chain connection.
	 *
	 * @var array
	 */
	private $api_type = '';
	/**
	 * External API authorization details.
	 *
	 * @var array
	 */
	private $authorization = array();
	/**
	 * External API request headers.
	 *
	 * @var array
	 */
	private $header = array();
	/**
	 * External API request body.
	 *
	 * @var array
	 */
	private $body = array();
	/**
	 * Post API call actions.
	 *
	 * @var array
	 */
	private $subsequent_actions = array();
	/**
	 * Cron details.
	 *
	 * @var array
	 */
	private $cron = array();
	/**
	 * Shortcode details.
	 *
	 * @var array
	 */
	private $shortcode_settings = array();
	/**
	 * Complete configuration of the API connection.
	 *
	 * @var string
	 */
	private $external_endpoint_config = array();
	/**
	 * Disable UI components.
	 *
	 * @var string
	 */
	protected $license_status = '';

	/**
	 * Default class constructor
	 *
	 * @param string $action Current tab action.
	 */
	public function __construct( $action ) {
		$this->type = Constants::EXTERNAL_ENDPOINT;
		if ( isset( $_GET['_wpnonce'] ) && check_admin_referer( 'MO_CAW_External_API_Connection_' . ucfirst( $action ) . '_Nonce', '_wpnonce' ) ) {
			$this->api_type = isset( $_GET['api-type'] ) ? sanitize_text_field( wp_unslash( $_GET['api-type'] ) ) : Constants::SIMPLE_API_EXTERNAL_API_TYPE;
			if ( Constants::EDIT === $action || Constants::VIEW === $action || Constants::TEST === $action || Constants::DELETE === $action ) {
				$this->api_name = isset( $_GET['api-name'] ) ? sanitize_text_field( wp_unslash( $_GET['api-name'] ) ) : $this->api_name;
				$this->method   = isset( $_GET['method'] ) ? sanitize_text_field( wp_unslash( $_GET['method'] ) ) : $this->method;

				$row_filter = array(
					'connection_name' => $this->api_name,
					'type'            => $this->type,
					'method'          => $this->method,
				);

				$this->external_endpoint_config = DB_Utils::get_configuration( $row_filter )[0] ?? array();

				$this->endpoint           = $this->external_endpoint_config['configuration']['endpoint'] ?? $this->endpoint;
				$this->dependent_apis     = $this->external_endpoint_config['configuration']['dependent_apis'] ?? $this->dependent_apis;
				$this->blocked_roles      = $this->external_endpoint_config['configuration']['blocked_roles'] ?? $this->blocked_roles;
				$this->authorization      = $this->external_endpoint_config['configuration']['authorization'] ?? $this->authorization;
				$this->header             = $this->external_endpoint_config['configuration']['header'] ?? $this->header;
				$this->body               = $this->external_endpoint_config['configuration']['body'] ?? $this->body;
				$this->subsequent_actions = $this->external_endpoint_config['configuration']['subsequent_actions'] ?? $this->subsequent_actions;
				$this->cron               = $this->external_endpoint_config['configuration']['cron_settings'] ?? $this->cron;
				$this->shortcode_settings = $this->external_endpoint_config['configuration']['shortcode_settings'] ?? $this->shortcode_settings;
			}
		}
		// The else condition is not required here as WordPress handles failure in nonce verification itself.
	}

	/**
	 * Display the content for External API as per the action.
	 *
	 * @param  string $tab    Active tab name.
	 * @param  string $action Tab current action.
	 * @return void
	 */
	public function display_external_api_connection_ui( $tab, $action ) {
		switch ( $action ) {
			case Constants::ADD:
				$this->display_external_api_connection_add_or_edit( $action );
				break;
			case Constants::EDIT:
				$this->display_external_api_connection_add_or_edit( $action );
				break;
			case Constants::TEST:
				$this->display_external_api_connection_add_or_edit( $action );
				break;
			case 'export':
				$this->display_external_connection_export();
				break;
			default:
				$this->display_external_connection_all_config();
				break;
		}
	}

	/**
	 * Display all custom APIs config.
	 *
	 * @return void
	 */
	private function display_external_connection_all_config() {
		$row_filter = array(
			'type' => $this->type,
		);

		$external_endpoints = DB_Utils::get_configuration( $row_filter );
		?>
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">Connected External APIs</h6>
			<div class="d-grid gap-2 d-md-block">
				<button class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4 mo-caw-element-to-toggle mo-caw-light-mode <?php echo esc_attr( $this->license_status ); ?>" method="button" data-bs-toggle="modal" data-bs-target="#" aria-hidden="true" hidden>Export Postman Collection</button>
				<button class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4 <?php echo esc_attr( $this->license_status ); ?>" type="button" data-bs-toggle="modal" data-bs-target="#mo-caw-external-api-type-modal">Connect External API</button>
			</div>
			<div class="modal fade mo-caw-export-modal" id="mo-caw-external-api-export-modal" tabindex="-1" aria-labelledby="mo-caw-external-api-export-modal-label" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered justify-content-center">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="mo-caw-external-api-export-modal-label">Export Postman Collection</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<h6 class="mo-caw-element-to-toggle mo-caw-light-mode">Select namespaces to export</h6>
							<div class="form-check d-flex align-items-center justify-content-start p-2">
								<input class="form-check-input m-0 bg-white me-2 mo-caw-select-all-checkbox" type="checkbox" value="" id="mo-caw-external-api-export-select-all" data-target="mo-caw-external-api-export">
								<label class="form-check-label" for="mo-caw-external-api-export-select-all">Select All</label>
							</div>
							<?php foreach ( $external_endpoints as $key => $external_endpoint ) : ?>
							<div class="form-check d-flex align-items-center justify-content-start p-2">
								<input class="form-check-input m-0 bg-white me-2 mo-caw-external-api-export" type="checkbox" value="<?php echo esc_attr( $external_endpoint['connection_name'] ); ?>" id="mo-caw-external-api-export-<?php echo esc_attr( $key ); ?>" name="mo-caw-external-api-export[]">
								<label class="form-check-label" for="mo-caw-external-api-export-<?php echo esc_attr( $key ); ?>"><?php echo esc_attr( $external_endpoint['connection_name'] ); ?></label>
							</div>
							<?php endforeach; ?>
						</div>
						<div class="modal-footer d-md-flex justify-content-md-center">
							<button class="btn btn-primary mo-caw-bg-blue-medium mo-caw-rounded-16" type="submit">Export</button>
						</div>
					</div>
				</div>
			</div>
			<div class="modal fade" id="mo-caw-external-api-type-modal" tabindex="-1" aria-labelledby="mo-caw-external-api-type-modal-label" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered justify-content-center">
					<form method="POST">
						<?php wp_nonce_field( 'MO_CAW_External_API_Connection_Export', 'MO_CAW_External_API_Connection_Nonce' ); ?>
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="mo-caw-external-api-type-modal-label">Connect External API</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body d-flex justify-content-around">
								<span class="d-flex flex-column align-items-center">
									<a class="rounded-circle btn mo-caw-shadow p-3 mx-3 mb-2" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=connect-external-api&action=add&api-type=simple_api', 'MO_CAW_External_API_Connection_Add_Nonce' ) ); ?>" role="button" data-bs-toggle="tooltip" data-bs-placement="bottom" title="">
										<svg width="60" height="60" viewBox="0 0 72 72" fill="none">
										<path d="M40.6319 31.3694C38.1746 28.9131 34.8424 27.5332 31.3679 27.5332C27.8935 27.5332 24.5612 28.9131 22.1039 31.3694L12.8369 40.6333C10.3796 43.0907 8.99902 46.4236 8.99902 49.8988C8.99902 53.3741 10.3796 56.707 12.8369 59.1643C15.2943 61.6217 18.6272 63.0022 22.1024 63.0022C25.5777 63.0022 28.9106 61.6217 31.3679 59.1643L35.9999 54.5323" stroke="#1B1B1F" stroke-width="6.66667" stroke-linecap="round" stroke-linejoin="round"/>
										<path d="M31.3679 40.6329C33.8252 43.0892 37.1575 44.469 40.6319 44.469C44.1064 44.469 47.4386 43.0892 49.8959 40.6329L59.1629 31.3689C61.6203 28.9115 63.0008 25.5786 63.0008 22.1034C63.0008 18.6282 61.6203 15.2953 59.1629 12.8379C56.7056 10.3805 53.3727 9 49.8974 9C46.4222 9 43.0893 10.3805 40.6319 12.8379L35.9999 17.4699" stroke="#1B1B1F" stroke-width="6.66667" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</a>
									<span>Simple API</span>
								</span>
								<span class="d-flex flex-column align-items-center">
									<a class="rounded-circle btn mo-caw-shadow p-3 mx-3 mb-2" role="button" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Coming Soon" >
										<svg width="60" height="60" viewBox="0 0 63 63" fill="none">
										<path d="M47.25 7.87502H17.9392C17.32 6.12358 16.1016 4.64744 14.4992 3.70748C12.8969 2.76753 11.0139 2.42429 9.18295 2.73843C7.35202 3.05256 5.6911 4.00385 4.49372 5.42416C3.29635 6.84446 2.63963 8.64234 2.63963 10.5C2.63963 12.3577 3.29635 14.1556 4.49372 15.5759C5.6911 16.9962 7.35202 17.9475 9.18295 18.2616C11.0139 18.5757 12.8969 18.2325 14.4992 17.2925C16.1016 16.3526 17.32 14.8765 17.9392 13.125H47.25C49.3386 13.125 51.3416 13.9547 52.8185 15.4316C54.2953 16.9084 55.125 18.9114 55.125 21C55.125 23.0886 54.2953 25.0916 52.8185 26.5685C51.3416 28.0453 49.3386 28.875 47.25 28.875H38.934C38.3817 27.3556 37.3751 26.043 36.0511 25.1155C34.727 24.1879 33.1495 23.6904 31.5328 23.6904C29.9162 23.6904 28.3387 24.1879 27.0146 25.1155C25.6905 26.043 24.6839 27.3556 24.1316 28.875H15.75C12.269 28.875 8.93064 30.2578 6.46922 32.7192C4.00781 35.1807 2.625 38.5191 2.625 42C2.625 45.481 4.00781 48.8194 6.46922 51.2808C8.93064 53.7422 12.269 55.125 15.75 55.125H31.5V63L42 52.5L31.5 42V49.875H15.75C13.6614 49.875 11.6584 49.0453 10.1815 47.5685C8.70468 46.0916 7.875 44.0886 7.875 42C7.875 39.9114 8.70468 37.9084 10.1815 36.4316C11.6584 34.9547 13.6614 34.125 15.75 34.125H24.1579C24.7153 35.6356 25.7223 36.939 27.0433 37.8596C28.3643 38.7803 29.9358 39.2738 31.5459 39.2738C33.1561 39.2738 34.7275 38.7803 36.0485 37.8596C37.3695 36.939 38.3766 35.6356 38.934 34.125H47.25C50.731 34.125 54.0694 32.7422 56.5308 30.2808C58.9922 27.8194 60.375 24.481 60.375 21C60.375 17.519 58.9922 14.1807 56.5308 11.7192C54.0694 9.25782 50.731 7.87502 47.25 7.87502ZM10.5 13.125C9.98082 13.125 9.47331 12.9711 9.04163 12.6826C8.60995 12.3942 8.2735 11.9842 8.07482 11.5046C7.87614 11.0249 7.82415 10.4971 7.92544 9.9879C8.02672 9.4787 8.27673 9.01097 8.64384 8.64386C9.01096 8.27675 9.47869 8.02674 9.98789 7.92545C10.4971 7.82417 11.0249 7.87615 11.5045 8.07483C11.9842 8.27351 12.3942 8.60997 12.6826 9.04164C12.971 9.47332 13.125 9.98084 13.125 10.5C13.125 11.1962 12.8484 11.8639 12.3562 12.3562C11.8639 12.8485 11.1962 13.125 10.5 13.125ZM31.5 34.125C30.9808 34.125 30.4733 33.9711 30.0416 33.6826C29.6099 33.3942 29.2735 32.9842 29.0748 32.5046C28.8761 32.0249 28.8241 31.4971 28.9254 30.9879C29.0267 30.4787 29.2767 30.011 29.6438 29.6439C30.011 29.2767 30.4787 29.0267 30.9879 28.9255C31.4971 28.8242 32.0249 28.8762 32.5045 29.0748C32.9842 29.2735 33.3942 29.61 33.6826 30.0416C33.971 30.4733 34.125 30.9808 34.125 31.5C34.125 32.1962 33.8484 32.8639 33.3562 33.3562C32.8639 33.8485 32.1962 34.125 31.5 34.125Z" fill="#6c757d"/>
										<path d="M52.5 44.625C50.9425 44.625 49.4199 45.0869 48.1249 45.9522C46.8299 46.8175 45.8205 48.0474 45.2245 49.4864C44.6284 50.9253 44.4725 52.5087 44.7763 54.0363C45.0802 55.5639 45.8302 56.9671 46.9315 58.0685C48.0329 59.1698 49.4361 59.9198 50.9637 60.2237C52.4913 60.5275 54.0747 60.3716 55.5136 59.7756C56.9526 59.1795 58.1825 58.1702 59.0478 56.8751C59.9131 55.5801 60.375 54.0575 60.375 52.5C60.375 50.4114 59.5453 48.4084 58.0685 46.9315C56.5916 45.4547 54.5886 44.625 52.5 44.625ZM52.5 55.125C51.9808 55.125 51.4733 54.971 51.0416 54.6826C50.61 54.3942 50.2735 53.9842 50.0748 53.5045C49.8761 53.0249 49.8242 52.4971 49.9254 51.9879C50.0267 51.4787 50.2767 51.011 50.6438 50.6438C51.011 50.2767 51.4787 50.0267 51.9879 49.9254C52.4971 49.8242 53.0249 49.8761 53.5045 50.0748C53.9842 50.2735 54.3942 50.6099 54.6826 51.0416C54.9711 51.4733 55.125 51.9808 55.125 52.5C55.125 53.1962 54.8484 53.8639 54.3562 54.3562C53.8639 54.8484 53.1962 55.125 52.5 55.125Z" fill="#6c757d"/>
										</svg>
									</a>
									<span>Chain API</span>
								</span>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php if ( ! empty( $external_endpoints ) ) : ?>
			<table class="table text-center fs-6 border border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
				<thead>
					<tr class="mo-caw-bg-blue-light mo-caw-rounded-top border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
						<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">API Name</th>
						<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">Method</th>
						<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">Enable/Disable</th>
						<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">Type</th>
						<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $external_endpoints as $external_endpoint ) : ?>
					<tr>
						<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_attr( $external_endpoint['connection_name'] ); ?></td>
						<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><span class="rounded-pill py-2 px-4 mo-caw-<?php echo esc_attr( $external_endpoint['method'] ); ?>-method"><?php echo esc_attr( strtoupper( $external_endpoint['method'] ) ); ?></span></td>
						<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">
							<div class="form-check form-switch d-flex justify-content-center align-items-center">
								<input class="form-check-input mo-caw-toggle-switch" type="checkbox" onchange="moCawEnableDisableApi(this, '<?php echo esc_attr( wp_create_nonce( 'mo_caw_external_api_enable_disable_api' ) ); ?>', '<?php echo esc_attr( $external_endpoint['connection_name'] ); ?>', '<?php echo esc_attr( $external_endpoint['method'] ); ?>', '', '<?php echo esc_attr( $this->type ); ?>')" 
									<?php
									echo ( ! isset( $external_endpoint['is_enabled'] ) || $external_endpoint['is_enabled'] ) ? 'checked ' : '';
									echo esc_attr( $this->license_status );
									?>
								>
							</div>
						</td>
						<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo ( 'chain_api' === $external_endpoint['api_type'] ) ? 'Chain API' : 'Simple API'; ?></td>
						<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">
							<div class="dropdown">
								<button class="btn btn-secondary dropdown-toggle mo-caw-dropdown-toggle rounded-pill mo-caw-bg-grey-light border-0 mo-caw-text-grey-medium" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">Pick an option</button>
								<ul class="dropdown-menu mo-caw-dropdown-menu" aria-labelledby="dropdownMenuButton1">
									<li><a class="dropdown-item d-flex align-items-center justify-content-between <?php echo esc_attr( $this->license_status ); ?>" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=connect-external-api&action=edit&api-name=' . $external_endpoint['connection_name'] . '&method=' . $external_endpoint['method'] . '&api-type=' . $external_endpoint['api_type'], 'MO_CAW_External_API_Connection_Edit_Nonce' ) ); ?>"><span>Edit</span><i class="fas fa-pencil mo-caw-text-black"></i></a></li>
									<li><a class="dropdown-item d-flex align-items-center justify-content-between <?php echo esc_attr( $this->license_status ); ?>" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=connect-external-api&action=test&api-name=' . $external_endpoint['connection_name'] . '&method=' . $external_endpoint['method'] . '&api-type=' . $external_endpoint['api_type'] . '&test-mode=true&output-format=json', 'MO_CAW_External_API_Connection_Test_Nonce' ) ); ?>"><span>Test</span><i class="fas fa-check mo-caw-text-black"></i></a></li>
									<li><a class="dropdown-item d-flex align-items-center justify-content-between <?php echo esc_attr( $this->license_status ); ?>" onclick="if(confirm('Are you sure you want to delete this API?')){document.getElementById('mo-caw-api-creation-delete-form-<?php echo esc_attr( $external_endpoint['connection_name'] ) . '-' . esc_attr( $external_endpoint['method'] ); ?>').submit(); return false;}"><span>Delete</span><i class="far fa-trash-can mo-caw-text-black"></i></a></li>
								</ul>
							</div>
							<form method="POST" id="mo-caw-api-creation-delete-form-<?php echo esc_attr( $external_endpoint['connection_name'] ) . '-' . esc_attr( $external_endpoint['method'] ); ?>">
								<?php wp_nonce_field( 'MO_CAW_External_API_Connection_Delete', 'MO_CAW_External_API_Connection_Nonce' ); ?>
								<input type="hidden" name="api-name" value="<?php echo esc_attr( $external_endpoint['connection_name'] ); ?>">
								<input type="hidden" name="method" value="<?php echo esc_attr( $external_endpoint['method'] ); ?>">
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="d-flex align-items-center flex-column">
				<img src="<?php echo esc_url( MO_CUSTOM_API_URL . 'classes/Common/Resources/Images/not-found.jpeg' ); ?>" width="450px" >
				<h6 class="mt-5 text-secondary">Oops! Seems like you have not connected any external APIs.</h6>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Display custom APIs add or edit page.
	 *
	 * @param  string $action Tab current action.
	 * @return void
	 */
	private function display_external_api_connection_add_or_edit( $action ) {
		global $wpdb;
		global $wp_roles;

		// Get all WordPress table names.
		$wp_all_tables = $wpdb->get_results( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Retrieving all the database table names to render on the UI.
		$table_names   = array();
		foreach ( $wp_all_tables as $index => $value ) {
			foreach ( $value as $table ) {
				array_push( $table_names, $table );
			}
		}

		// Get all WordPress Roles.
		$wp_all_roles = $wp_roles->roles;
		$role_names   = array();
		$role_slugs   = array_keys( $wp_all_roles );
		foreach ( $wp_all_roles as $index => $role ) {
			array_push( $role_names, $role['name'] );
		}

		$row_filter    = array(
			'type' => $this->type,
		);
		$column_filter = array(
			'connection_name',
			'method',
		);

		$connection_names_with_methods = DB_Utils::get_configuration( $row_filter, $column_filter );

		$names   = array_map(
			function ( $connection_names_with_methods ) {
				return $connection_names_with_methods['connection_name'];
			},
			$connection_names_with_methods
		);
		$methods = array_map(
			function ( $connection_names_with_methods ) {
				return $connection_names_with_methods['method'];
			},
			$connection_names_with_methods
		);

		$current_date_time = new \DateTime();
		$current_date_time = $current_date_time->format( 'Y-m-d\TH:i' );

		$test_mode = isset( $_GET['test-mode'] ) ? filter_var( sanitize_text_field( wp_unslash( $_GET['test-mode'] ) ), FILTER_VALIDATE_BOOLEAN ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified in the constructor.
		?>
		<form method="POST" id="mo-caw-external-api-form" class="mo-caw-element-to-toggle mo-caw-light-mode">
			<?php wp_nonce_field( 'MO_CAW_External_API_Connection', 'MO_CAW_External_API_Connection_Nonce' ); ?>
			<input type="hidden" name="mo-caw-old-authorization-type" value="<?php echo isset( $this->authorization['auth_type'] ) ? esc_attr( $this->authorization['auth_type'] ) : esc_attr( Constants::NO_AUTHORIZATION ); ?>">
			<input type="hidden" name="mo-caw-external-api-type" value="<?php echo esc_attr( $this->api_type ); ?>">
			<input type="hidden" id="mo-caw-external-api-test-mode" name="mo-caw-external-api-test-mode" value="false">
			<div class="d-flex justify-content-between align-items-end mb-4">
				<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode"><?php echo Constants::ADD === $action ? 'Connect External API' : 'Edit API - ' . esc_attr( $this->api_name ); ?></h6>
				<div class="d-grid gap-2 d-md-block">
					<button class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4 mo-caw-element-to-toggle mo-caw-light-mode <?php echo esc_attr( $this->license_status ); ?>" type="button" 
						<?php
						if ( Constants::DISABLED !== $this->license_status ) {
							echo esc_attr( 'onclick=moCawEnableTestMode()' );
						}
						?>
					>Test</button>
					<button class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4 <?php echo esc_attr( $this->license_status ); ?>" type="submit" id="mo-caw-external-api-form-submit">Save</button>
				</div>
			</div>
			<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16">
				<div class="row">
					<div class="mb-3 col">
						<label for="mo-caw-external-api-name" class="form-label mo-caw-form-label">API Name</label>
						<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-external-api-name" name="mo-caw-external-api-name" value="<?php echo esc_attr( $this->api_name ); ?>" placeholder="API Name" pattern="^(?=.{1,25}$)[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$" title="Should be of maximum length 25 and only '-' are accepted in between along with [A-Z, a-z and 0-9]" aria-required="true" required <?php echo Constants::EDIT === $action || Constants::TEST === $action ? 'disabled' : ''; ?>>
						<?php if ( Constants::EDIT === $action || Constants::TEST === $action ) : ?>
							<input type="hidden" name="mo-caw-external-api-name" value="<?php echo esc_attr( $this->api_name ); ?>">
						<?php endif; ?>
					</div>
					<div class="mb-3 col">
						<label for="mo-caw-external-api-method" class="form-label mo-caw-form-label">Method <i class="fas fa-info rounded-circle border py-1 px-2" data-bs-toggle="tooltip" data-bs-placement="bottom" title="HTTP request method"></i></label>
						<select class="form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode mo-caw-method-selector" id="mo-caw-external-api-method" name="mo-caw-external-api-method" aria-label="#mo-caw-external-api-method" aria-required="true" required <?php echo Constants::EDIT === $action || Constants::TEST === $action ? 'disabled' : ''; ?>>
							<option value="">Select Method</option>
							<option value="get" <?php echo Constants::HTTP_GET === $this->method ? 'selected' : ''; ?> >GET</option>
							<option value="post" <?php echo Constants::HTTP_POST === $this->method ? 'selected' : ''; ?> >POST</option>
							<option value="put" <?php echo Constants::HTTP_PUT === $this->method ? 'selected' : ''; ?> >PUT</option>
							<option value="patch" <?php echo Constants::HTTP_PATCH === $this->method ? 'selected' : ''; ?> >PATCH</option>
							<option value="delete" <?php echo Constants::HTTP_DELETE === $this->method ? 'selected' : ''; ?> >DELETE</option>
						</select>
						<?php if ( Constants::EDIT === $action || Constants::TEST === $action ) : ?>
							<input type="hidden" name="mo-caw-external-api-method" value="<?php echo esc_attr( $this->method ); ?>">
						<?php endif; ?>
					</div>
				</div>
				<div class="mb-3">
					<label for="mo-caw-external-api-endpoint" class="form-label mo-caw-form-label">External API Endpoint</label>
					<input type="url" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-external-api-endpoint" name="mo-caw-external-api-endpoint" value="<?php echo esc_attr( $this->endpoint ); ?>" placeholder="External API Endpoint" pattern="https?://.+" title="Please enter a valid URL starting with http:// or https://" aria-required="true" required>
				</div>
				<div id="mo-caw-external-api-request-format-block">
					<ul class="nav nav-tabs nav-pills border-0 flex-column flex-sm-row text-center rounded">
						<li class="nav-item mo-caw-bg-blue-light flex-sm-fill">
							<a class="nav-link active" data-bs-toggle="tab" href="#mo-caw-external-api-request-format-authorization">Authorization</a>
						</li>
						<li class="nav-item mx-1 mo-caw-bg-blue-light flex-sm-fill">
							<a class="nav-link" data-bs-toggle="tab" href="#mo-caw-external-api-request-format-headers">Headers</a>
						</li>
						<li class="nav-item mo-caw-bg-blue-light flex-sm-fill">
							<a class="nav-link" data-bs-toggle="tab" href="#mo-caw-external-api-request-format-body">Body</a>
						</li>
					</ul>
					<div class="tab-content pb-3">
						<div class="tab-pane active" id="mo-caw-external-api-request-format-authorization">
							<div class="d-flex mt-3">
								<div class="form-check d-flex align-items-center me-3">
									<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-request-auth-type" value="no-auth" onchange="moCawShowOrHideElements('mo-caw-external-api-auth-inputs')" <?php echo ! isset( $this->authorization['auth_type'] ) || Constants::NO_AUTHORIZATION === $this->authorization['auth_type'] ? 'checked' : ''; ?>>
									<label class="form-check-label" for="mo-caw-external-api-request-basic-authorization">No Authorization</label>
								</div>
								<div class="form-check d-flex align-items-center me-3">
									<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-request-auth-type" value="basic-authorization" onchange="moCawShowOrHideElements('mo-caw-external-api-auth-inputs', 'mo-caw-external-api-request-basic-authorization-inputs')" <?php echo isset( $this->authorization['auth_type'] ) && Constants::BASIC_AUTHORIZATION === $this->authorization['auth_type'] ? 'checked' : ''; ?>>
									<label class="form-check-label" for="mo-caw-external-api-request-basic-authorization">Basic Authorization</label>
								</div>
								<div class="form-check d-flex align-items-center me-3">
									<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-request-auth-type" value="bearer-token" onchange="moCawShowOrHideElements('mo-caw-external-api-auth-inputs', 'mo-caw-external-api-request-bearer-token-inputs')" <?php echo isset( $this->authorization['auth_type'] ) && Constants::BEARER_TOKEN === $this->authorization['auth_type'] ? 'checked' : ''; ?>>
									<label class="form-check-label" for="mo-caw-external-api-request-bearer-token">Bearer Token</label>
								</div>
								<div class="form-check d-flex align-items-center me-3">
									<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-request-auth-type" value="api-key-authentication" onchange="moCawShowOrHideElements('mo-caw-external-api-auth-inputs', 'mo-caw-external-api-request-api-key-authentication-inputs')" <?php echo isset( $this->authorization['auth_type'] ) && Constants::API_KEY_AUTHENTICATION === $this->authorization['auth_type'] ? 'checked' : ''; ?>>
									<label class="form-check-label" for="mo-caw-external-api-request-api-key-authentication">API Key</label>
								</div>
							</div>
							<div id="mo-caw-external-api-auth-inputs">
								<?php if ( isset( $this->authorization['auth_type'] ) && Constants::BASIC_AUTHORIZATION === $this->authorization['auth_type'] ) : ?>
									<div class="my-3 row mo-caw-show-or-hide-element" id="mo-caw-external-api-request-basic-authorization-inputs">
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-basic-authorization-username" name="mo-caw-external-api-basic-authorization-username" placeholder="Username" value="<?php echo esc_attr( array_key_first( $this->authorization['auth_config'] ) ); ?>">
										</div>
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-basic-authorization-password" name="mo-caw-external-api-basic-authorization-password" placeholder="Password" value="<?php echo esc_attr( array_values( $this->authorization['auth_config'] )[0] ); ?>">
										</div>
									</div>
								<?php else : ?>
									<div class="my-3 row mo-caw-show-or-hide-element d-none" id="mo-caw-external-api-request-basic-authorization-inputs">
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-basic-authorization-username" name="mo-caw-external-api-basic-authorization-username" placeholder="Username">
										</div>
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-basic-authorization-password" name="mo-caw-external-api-basic-authorization-password" placeholder="Password">
										</div>
									</div>
								<?php endif; ?>
								<?php if ( isset( $this->authorization['auth_type'] ) && Constants::BEARER_TOKEN === $this->authorization['auth_type'] ) : ?>
									<div class="my-3 row mo-caw-show-or-hide-element" id="mo-caw-external-api-request-bearer-token-inputs">
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-bearer-token-token" name="mo-caw-external-api-bearer-token-token" placeholder="Bearer Token"  value="<?php echo esc_attr( $this->authorization['auth_config'] ); ?>">
										</div>
									</div>
								<?php else : ?>
									<div class="my-3 row mo-caw-show-or-hide-element d-none" id="mo-caw-external-api-request-bearer-token-inputs">
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-bearer-token-token" name="mo-caw-external-api-bearer-token-token" placeholder="Bearer Token">
										</div>
									</div>
								<?php endif; ?>
								<?php if ( isset( $this->authorization['auth_type'] ) && Constants::API_KEY_AUTHENTICATION === $this->authorization['auth_type'] ) : ?>
									<div class="my-3 row mo-caw-show-or-hide-element" id="mo-caw-external-api-request-api-key-authentication-inputs">
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-api-key-authentication-key" name="mo-caw-external-api-api-key-authentication-key" placeholder="Key" value="<?php echo esc_attr( array_key_first( $this->authorization['auth_config'] ) ); ?>">
										</div>
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-api-key-authentication-value" name="mo-caw-external-api-api-key-authentication-value" placeholder="Value" value="<?php echo esc_attr( array_values( $this->authorization['auth_config'] )[0] ); ?>">
										</div>
									</div>
								<?php else : ?>
									<div class="my-3 row mo-caw-show-or-hide-element d-none" id="mo-caw-external-api-request-api-key-authentication-inputs">
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-api-key-authentication-key" name="mo-caw-external-api-api-key-authentication-key" placeholder="Key">
										</div>
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-api-key-authentication-value" name="mo-caw-external-api-api-key-authentication-value" placeholder="Value">
										</div>
									</div>
								<?php endif; ?>
							</div>
						</div>
						<div class="tab-pane fade" id="mo-caw-external-api-request-format-headers">
							<div class="d-flex justify-content-between align-items-center my-3">
								<label class="form-label mo-caw-form-label fw-bolder">Key and Value</label>
								<span>
									<button class="border-0 bg-white p-0" type="button" onclick="moCawAddField('external', 'mo-caw-external-api-headers-key-value-duplicate-div-0', 'mo-caw-external-api-headers-key-value-duplicate-div-', this.nextElementSibling)"><i class="fa-solid fa-plus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
									<button class="border-0 bg-white p-0" type="button" onclick="moCawRemoveField('mo-caw-external-api-headers-key-value-duplicate-div-', this)"><i class="fa-solid fa-minus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
								</span>
							</div>
							<?php if ( ! empty( $this->header ) ) : ?>
								<?php $index = 0; ?>
								<?php foreach ( $this->header as $key => $value ) : ?>
									<div class="row mb-3" id="mo-caw-external-api-headers-key-value-duplicate-div-<?php echo esc_attr( $index ); ?>">
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" name="mo-caw-external-api-headers-key[]" placeholder="Key" value="<?php echo esc_attr( $key ); ?>" <?php echo Constants::NO_AUTHORIZATION !== $this->authorization['auth_type'] && 0 === $index ? 'readonly aria-readonly="true"' : ''; ?>>
										</div>
										<div class="col">
											<input type="text" class="form-control mo-caw-form-control py-1" name="mo-caw-external-api-headers-val[]" placeholder="Value" value="<?php echo esc_attr( $value ); ?>" <?php echo Constants::NO_AUTHORIZATION !== $this->authorization['auth_type'] && 0 === $index ? 'readonly aria-readonly="true"' : ''; ?>>
										</div>
									</div>
									<?php ++$index; ?>
								<?php endforeach; ?>
							<?php else : ?>
								<div class="row mb-3" id="mo-caw-external-api-headers-key-value-duplicate-div-0">
									<div class="col">
										<input type="text" class="form-control mo-caw-form-control py-1" name="mo-caw-external-api-headers-key[]" placeholder="Key">
									</div>
									<div class="col">
										<input type="text" class="form-control mo-caw-form-control py-1" name="mo-caw-external-api-headers-val[]" placeholder="Value">
									</div>
								</div>
							<?php endif; ?>
						</div>
						<div class="tab-pane fade" id="mo-caw-external-api-request-format-body">
							<div class="d-flex mt-3">
								<div class="form-check d-flex align-items-center me-3">
									<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-request-body" value="no-body" onchange="moCawShowOrHideElements('mo-caw-external-api-body-inputs')" <?php echo ! isset( $this->body['request_type'] ) || Constants::NO_BODY === $this->body['request_type'] ? 'checked' : ''; ?>>
									<label class="form-check-label" for="mo-caw-external-api-request-body-no-body">No Body</label>
								</div>
								<div class="form-check d-flex align-items-center me-3">
									<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-request-body" value="x-www-form-urlencoded" onchange="moCawShowOrHideElements('mo-caw-external-api-body-inputs', 'mo-caw-external-api-x-www-form-urlencoded-body-inputs')" <?php echo isset( $this->body['request_type'] ) && Constants::X_WWW_FORM_URLENCODED === $this->body['request_type'] ? 'checked' : ''; ?>>
									<label class="form-check-label" for="mo-caw-external-api-request-body-x-www-form-urlencoded">x-www-form-urlencoded</label>
								</div>
								<div class="form-check d-flex align-items-center me-3">
									<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-request-body" value="json" onchange="moCawShowOrHideElements('mo-caw-external-api-body-inputs', 'mo-caw-external-api-json-body-inputs')" <?php echo isset( $this->body['request_type'] ) && Constants::JSON === $this->body['request_type'] ? 'checked' : ''; ?>>
									<label class="form-check-label" for="mo-caw-external-api-request-body-json">JSON</label>
								</div>
								<div class="form-check d-flex align-items-center me-3">
									<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-request-body" value="graphql" onchange="moCawShowOrHideElements('mo-caw-external-api-body-inputs', 'mo-caw-external-api-graphql-body-inputs')" <?php echo isset( $this->body['request_type'] ) && Constants::GRAPH_QL === $this->body['request_type'] ? 'checked' : ''; ?>>
									<label class="form-check-label" for="mo-caw-external-api-body-graphql">GraphQL</label>
								</div>
								<div class="form-check d-flex align-items-center me-3">
									<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-request-body" value="xml" onchange="moCawShowOrHideElements('mo-caw-external-api-body-inputs', 'mo-caw-external-api-xml-body-inputs')" <?php echo isset( $this->body['request_type'] ) && Constants::XML === $this->body['request_type'] ? 'checked' : ''; ?>>
									<label class="form-check-label" for="mo-caw-external-api-request-body-xml">XML</label>
								</div>
							</div>
							<div id="mo-caw-external-api-body-inputs">
								<?php if ( isset( $this->body['request_type'] ) && Constants::X_WWW_FORM_URLENCODED === $this->body['request_type'] ) : ?>
									<div class="mo-caw-show-or-hide-element" id="mo-caw-external-api-x-www-form-urlencoded-body-inputs">
										<div class="d-flex justify-content-between align-items-center my-3">
											<label class="form-label mo-caw-form-label fw-bolder">Key and Value</label>
											<span>
												<button class="border-0 bg-white p-0" type="button" onclick="moCawAddField('external', 'mo-caw-external-api-x-www-form-urlencoded-key-value-duplicate-div-0', 'mo-caw-external-api-x-www-form-urlencoded-key-value-duplicate-div-', this.nextElementSibling)"><i class="fa-solid fa-plus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
												<button class="border-0 bg-white p-0" type="button" onclick="moCawRemoveField('mo-caw-external-api-x-www-form-urlencoded-key-value-duplicate-div-', this)"><i class="fa-solid fa-minus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
											</span>
										</div>
										<?php $index = 0; ?>
										<?php foreach ( $this->body['request_value'] as $key => $value ) : ?>
										<div class="row mb-3" id="mo-caw-external-api-x-www-form-urlencoded-key-value-duplicate-div-<?php echo esc_attr( $index ); ?>">
											<div class="col">
												<input type="text" class="form-control mo-caw-form-control py-1" name="mo-caw-external-api-x-www-form-urlencoded-body-key[]" placeholder="Key" value="<?php echo esc_attr( $key ); ?>">
											</div>
											<div class="col">
												<input type="text" class="form-control mo-caw-form-control py-1" name="mo-caw-external-api-x-www-form-urlencoded-body-value[]" placeholder="Value" value="<?php echo esc_attr( $value ); ?>">
											</div>
										</div>
											<?php ++$index; ?>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<div class="mo-caw-show-or-hide-element d-none" id="mo-caw-external-api-x-www-form-urlencoded-body-inputs">
										<div class="d-flex justify-content-between align-items-center my-3">
											<label class="form-label mo-caw-form-label fw-bolder">Key and Value</label>
											<span>
												<button class="border-0 bg-white p-0" type="button" onclick="moCawAddField('external', 'mo-caw-external-api-x-www-form-urlencoded-key-value-duplicate-div-0', 'mo-caw-external-api-x-www-form-urlencoded-key-value-duplicate-div-', this.nextElementSibling)"><i class="fa-solid fa-plus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
												<button class="border-0 bg-white p-0" type="button" onclick="moCawRemoveField('mo-caw-external-api-x-www-form-urlencoded-key-value-duplicate-div-', this)"><i class="fa-solid fa-minus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
											</span>
										</div>
										<div class="row mb-3" id="mo-caw-external-api-x-www-form-urlencoded-key-value-duplicate-div-0">
											<div class="col">
												<input type="text" class="form-control mo-caw-form-control py-1" name="mo-caw-external-api-x-www-form-urlencoded-body-key[]" placeholder="Key">
											</div>
											<div class="col">
												<input type="text" class="form-control mo-caw-form-control py-1" name="mo-caw-external-api-x-www-form-urlencoded-body-value[]" placeholder="Value">
											</div>
										</div>
									</div>
								<?php endif; ?>
								<?php if ( isset( $this->body['request_type'] ) && Constants::JSON === $this->body['request_type'] ) : ?>
									<div class="my-3 row mo-caw-show-or-hide-element" id="mo-caw-external-api-json-body-inputs">
										<div class="col">
											<textarea class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-json-body" name="mo-caw-external-api-json-body" placeholder="Add JSON body" rows="10"><?php echo esc_attr( $this->body['request_value'] ); ?></textarea>
										</div>
									</div>
								<?php else : ?>
									<div class="my-3 row mo-caw-show-or-hide-element d-none" id="mo-caw-external-api-json-body-inputs">
										<div class="col">
											<textarea class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-json-body" name="mo-caw-external-api-json-body" placeholder="Add JSON body" rows="10"></textarea>
										</div>
									</div>
								<?php endif; ?>
								<?php if ( isset( $this->body['request_type'] ) && Constants::GRAPH_QL === $this->body['request_type'] ) : ?>
									<div class="my-3 row mo-caw-show-or-hide-element" id="mo-caw-external-api-graphql-body-inputs">
										<div class="col">
											<textarea class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-graphql-body" name="mo-caw-external-api-graphql-body" placeholder="Add GraphQL body" rows="10"><?php echo esc_attr( $this->body['request_value'] ); ?></textarea>
										</div>
									</div>
								<?php else : ?>
									<div class="my-3 row mo-caw-show-or-hide-element d-none" id="mo-caw-external-api-graphql-body-inputs">
										<div class="col">
											<textarea class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-graphql-body" name="mo-caw-external-api-graphql-body" placeholder="Add GraphQL body" rows="10"></textarea>
										</div>
									</div>
								<?php endif; ?>
								<?php if ( isset( $this->body['request_type'] ) && Constants::XML === $this->body['request_type'] ) : ?>
									<div class="my-3 row mo-caw-show-or-hide-element" id="mo-caw-external-api-xml-body-inputs">
										<div class="col">
											<textarea class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-xml-body" name="mo-caw-external-api-xml-body" placeholder="Add XML body" rows="10"><?php echo esc_xml( $this->body['request_value'] ); ?></textarea>
										</div>
									</div>
								<?php else : ?>
									<div class="my-3 row mo-caw-show-or-hide-element d-none" id="mo-caw-external-api-xml-body-inputs">
										<div class="col">
											<textarea class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-xml-body" name="mo-caw-external-api-xml-body" placeholder="Add XML body" rows="10"></textarea>
										</div>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
				<div class="accordion d-none" id="mo-caw-adv-setting-accordion">
					<div class="accordion accordion-flush" id="mo-caw-external-api-accordion-flush">
						<div class="accordion-item mb-2 border-0 mo-caw-rounded-8 mo-caw-accordion-item mo-caw-element-to-toggle mo-caw-light-mode">
							<h2 class="accordion-header" id="mo-caw-external-api-adv-settings-accordion-heading">
								<button class="accordion-button collapsed mo-caw-bg-blue-light shadow-none mo-caw-rounded-8" type="button" data-bs-toggle="collapse" data-bs-target="#mo-caw-external-api-adv-settings-accordion-collapse" aria-expanded="false" aria-controls="mo-caw-external-api-adv-settings-accordion-collapse">
									Advance Settings
								</button>
							</h2>
							<div id="mo-caw-external-api-adv-settings-accordion-collapse" class="accordion-collapse collapse" aria-labelledby="mo-caw-external-api-adv-settings-accordion-heading" data-bs-parent="#mo-caw-external-api-accordion-flush">
								<div class="accordion-body">
										<div class="row">
											<div class="mb-3 col">
												<label for="mo-caw-external-api-allowed-roles" class="form-label mo-caw-form-label">Restrict role-based access</label>
												<div class="dropdown mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-aac-plan">
													<button class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan btn dropdown-toggle mo-caw-dropdown-toggle w-100 bg-white mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode mo-caw-text-grey-medium d-flex justify-content-between align-items-center" type="button" id="mo-caw-external-api-allowed-roles-dropdown" data-bs-toggle="dropdown" aria-expanded="false" >
															<?php echo ! empty( $this->blocked_roles ) ? 'Selected (' . count( $this->blocked_roles ) . ')' : 'Select Roles'; ?>
													</button>
													<div class="dropdown-menu mo-caw-dropdown-menu w-100 mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-aac-plan" aria-labelledby="mo-caw-external-api-allowed-roles-dropdown" >
														<div class="form-check d-flex align-items-center p-2">
															<input class="form-check-input m-0 bg-white mo-caw-select-all-checkbox me-2" type="checkbox" value="" id="mo-caw-external-api-allowed-roles-select-all" data-target="mo-caw-external-api-allowed-roles" <?php echo count( $this->blocked_roles ) === count( $role_slugs ) ? 'checked' : ''; ?>>
															<label class="form-check-label" for="mo-caw-external-api-allowed-roles-select-all">Select All</label>
														</div>
														<?php foreach ( $role_slugs as $index => $role_slug ) : ?>
														<div class="form-check d-flex align-items-center p-2">
															<input class="form-check-input m-0 bg-white mo-caw-external-api-allowed-roles me-2" type="checkbox" value="<?php echo esc_attr( $role_slug ); ?>" id="mo-caw-external-api-allowed-roles-<?php echo esc_attr( $index ); ?>" name="mo-caw-external-api-allowed-roles[]" <?php echo in_array( $role_slug, $this->blocked_roles, true ) ? 'checked' : ''; ?>>
															<label class="form-check-label" for="mo-caw-external-api-allowed-roles-<?php echo esc_attr( $index ); ?>"><?php echo esc_attr( $role_names[ $index ] ); ?></label>
														</div>
														<?php endforeach; ?>
													</div>
													</div>
												</div>
											<div class="mb-3 col">
												<label for="mo-caw-external-api-dependent-apis" class="form-label mo-caw-form-label">Dependent APIs</label>
													<div class="dropdown mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-aac-plan">
														<button class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan btn dropdown-toggle mo-caw-dropdown-toggle w-100 bg-white mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode mo-caw-text-grey-medium d-flex justify-content-between align-items-center" type="button" id="mo-caw-external-api-dependent-apis-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
															<?php echo empty( $this->dependent_apis ) ? 'Select Dependent APIs' : 'Selected (' . count( $this->dependent_apis ) . ')'; ?>
														</button>
														<div class="dropdown-menu mo-caw-dropdown-menu w-100" aria-labelledby="mo-caw-external-api-dependent-apis-dropdown">
															<?php if ( ( count( $names ) > 0 && Constants::ADD === $action ) || count( $names ) > 1 ) : ?>
																<?php foreach ( $names as $key => $connection_name ) : ?>
																	<?php if ( $connection_name !== $this->api_name || $methods[ $key ] !== $this->method ) : ?>
																	<div class="form-check d-flex align-items-center justify-content-start mo-caw-external-api-dependent-apis-div p-2">
																		<input class="form-check-input m-0 bg-white me-2 mo-caw-external-api-dependent-apis" type="checkbox" value="<?php echo esc_attr( $connection_name ) . ' [' . esc_attr( strtoupper( $methods[ $key ] ) ) . ']'; ?>" id="mo-caw-external-api-dependent-apis-<?php echo esc_attr( $key ); ?>" name="mo-caw-external-api-dependent-apis[]" <?php echo in_array( $connection_name . ' [' . strtoupper( $methods[ $key ] ) . ']', $this->dependent_apis, true ) ? 'checked' : ''; ?>>
																		<label class="form-check-label" for="mo-caw-external-api-dependent-apis-<?php echo esc_attr( $key ); ?>"><b><?php echo esc_attr( $connection_name ); ?></b> <?php echo ' [' . esc_attr( strtoupper( $methods[ $key ] ) ) . ']'; ?></label>
																	</div>
																	<?php endif; ?>
																<?php endforeach; ?>
															<?php else : ?>
																<label class="form-label px-2">No APIs available to configure as dependent APIs.</label>
															<?php endif; ?>
														</div>
												</div>
											</div>
										</div>
									<div>
										<div class="row">
											<div class="col-6 d-flex justify-content-between align-items-center">
												<p class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode fs-6">Schedule CRON</p>
												<div class="form-check form-switch d-flex justify-content-between align-items-center p-0">
													<label class="form-check-label"></label>
													<input class="mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-aac-plan form-check-input mo-caw-toggle-switch" name="mo-external-api-cron-enable-disable" type="checkbox" 
													<?php
													echo isset( $this->cron['is_cron_enabled'] ) && $this->cron['is_cron_enabled'] ? 'checked' : '';
													echo esc_attr( $this->license_status );
													?>
													>
												</div>
											</div>
										</div>
										<div class="row">
											<div class="mb-3 col">
												<label for="mo-caw-external-cron-start-date-time" class="form-label mo-caw-form-label">Start date and time</label>
												<div class="d-flex justify-content-between align-items-center">
													<input type="datetime-local" class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-external-cron-start-date-time" name="mo-caw-external-cron-start-date-time" value="<?php echo ! empty( $this->cron['date_and_time'] ) ? esc_attr( $this->cron['date_and_time'] ) : esc_attr( $current_date_time ); ?>" placeholder="<?php echo esc_attr( $current_date_time ); ?>">
												</div>
											</div>
											<div class="mb-3 col">
												<label for="mo-caw-external-api-cron-frequency" class="form-label mo-caw-form-label">Frequency</label>
												<div class="d-flex justify-content-between align-items-center">
													<select class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-external-api-cron-frequency" name="mo-caw-external-api-cron-frequency" aria-label="#mo-caw-external-api-cron-frequency">
														<option value="">Select Frequency</option>
														<option value="hourly" <?php echo isset( $this->cron['frequency'] ) && 'hourly' === $this->cron['frequency'] ? 'selected' : ''; ?>>Hourly</option>
														<option value="daily" <?php echo isset( $this->cron['frequency'] ) && 'daily' === $this->cron['frequency'] ? 'selected' : ''; ?>>Daily</option>
														<option value="weekly" <?php echo isset( $this->cron['frequency'] ) && 'weekly' === $this->cron['frequency'] ? 'selected' : ''; ?>>Weekly</option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div id="mo-caw-external-api-post-api-call-actions-block">
										<p class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode fs-6">Post API call actions</p>
										<div class="d-flex my-3">
											<div class="form-check d-flex align-items-center me-3">
												<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-post-api-call-action" value="display-data-via-shortcode" onchange='moCawShowOrHideElements("mo-caw-external-api-post-api-call-actions-options", "mo-caw-external-api-display-data-via-shortcode-inputs")' <?php echo esc_attr( $this->license_status ); ?> checked />
												<label class="form-check-label" for="mo-caw-external-api-request-basic-authorization">Display Data via Shortcode</label>
											</div>
											<div class="form-check d-flex align-items-center me-3">
												<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-post-api-call-action" value="store-data-in-database"  onchange='moCawShowOrHideElements("mo-caw-external-api-post-api-call-actions-options", "mo-caw-external-api-store-data-in-database-inputs")' <?php echo esc_attr( $this->license_status ); ?> />
												<label class="form-check-label" for="mo-caw-external-api-request-bearer-token">Store data in database</label>
											</div>
											<div class="form-check d-flex align-items-center me-3 d-none">
												<input class="form-check-input mo-caw-radio-btn" type="radio" name="mo-caw-external-api-post-api-call-action" value="call-another-api" 
												onchange='moCawShowOrHideElements("mo-caw-external-api-post-api-call-actions-options", "mo-caw-external-api-call-another-api-inputs")' <?php echo esc_attr( $this->license_status ); ?> />
												<label class="form-check-label" for="mo-caw-external-api-request-api-key-authentication">Call another API</label>
											</div>
										</div>
										<div id="mo-caw-external-api-post-api-call-actions-options">
											<div class="mo-caw-show-or-hide-element" id="mo-caw-external-api-display-data-via-shortcode-inputs">
												<div class="accordion accordion-flush" id="mo-caw-external-api-display-data-via-shortcode-accordion">
													<div class="accordion-item mb-2 border-0 mo-caw-rounded-8 mo-caw-accordion-item mo-caw-element-to-toggle mo-caw-light-mode">
														<h2 class="accordion-header" id="mo-caw-external-api-shortcode">
															<button class="accordion-button mo-caw-bg-blue-light shadow-none mo-caw-rounded-8 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mo-caw-external-api-shortcode-accordion-button" aria-expanded="false" aria-controls="mo-caw-external-api-shortcode-accordion-button">
																Shortcode
															</button>
														</h2>
														<div id="mo-caw-external-api-shortcode-accordion-button" class="accordion-collapse collapse" aria-labelledby="mo-caw-external-api-shortcode" data-bs-parent="#mo-caw-external-api-display-data-via-shortcode-accordion">
															<div class="accordion-body">
																<div class="d-flex justify-content-between align-items-start">
																	<span><pre>[mo_custom_api_shortcode api="<?php echo esc_attr( $this->api_name ); ?>" method="<?php echo esc_attr( $this->method ); ?>"]</pre></span>
																	<span class="input-group-text bg-white mo-caw-cursor-pointer fs-6 mo-caw-copy-icon border-0" data-bs-toggle="tooltip" data-bs-placement="right" title="Copy Shortcode"><i class="far fa-copy fa-lg"></i></span>
																</div>
															</div>
														</div>
													</div>
													<div class="accordion-item mb-2 border-0 mo-caw-rounded-8 mo-caw-accordion-item mo-caw-element-to-toggle mo-caw-light-mode">
														<h2 class="accordion-header" id="mo-caw-external-api-template-tag">
															<button class="accordion-button mo-caw-bg-blue-light shadow-none mo-caw-rounded-8 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mo-caw-external-api-template-tag-accordion-button" aria-expanded="false" aria-controls="mo-caw-external-api-template-tag-accordion-button">
																Template Tag
															</button>
														</h2>
														<div id="mo-caw-external-api-template-tag-accordion-button" class="accordion-collapse collapse" aria-labelledby="mo-caw-external-api-template-tag" data-bs-parent="#mo-caw-external-api-display-data-via-shortcode-accordion">
															<div class="accordion-body">
																<div class="d-flex justify-content-between align-items-start">
																	<span class="overflow-auto"><pre>apply_filters( 'mo_caw_execute_external_api', '<?php echo esc_attr( $this->api_name ); ?>', '<?php echo esc_attr( $this->method ); ?>', array() )</pre></span>
																	<span class="input-group-text bg-white mo-caw-cursor-pointer fs-6 mo-caw-copy-icon border-0" data-bs-toggle="tooltip" data-bs-placement="right" title="Copy Template Tag"><i class="far fa-copy fa-lg"></i></span>
																</div>
															</div>
														</div>
													</div>
														<div class="accordion-item mb-2 border-0 mo-caw-rounded-8 mo-caw-accordion-item mo-caw-element-to-toggle mo-caw-light-mode">
															<h2 class="accordion-header" id="mo-caw-external-api-shortcode-settings">
																<button class="accordion-button mo-caw-bg-blue-light shadow-none mo-caw-rounded-8 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mo-caw-external-api-accordion-shortcode-settings-button" aria-expanded="false" aria-controls="mo-caw-external-api-accordion-shortcode-settings-button">
																	Shortcode Settings
																</button>
															</h2>
															<div id="mo-caw-external-api-accordion-shortcode-settings-button" class="accordion-collapse collapse" aria-labelledby="mo-caw-external-api-shortcode-settings" data-bs-parent="#mo-caw-external-api-display-data-via-shortcode-accordion">
															<div class="accordion-body">
																<ul class="nav nav-tabs nav-pills border-0 flex-column flex-sm-row text-center rounded">
																	<li class="nav-item mo-caw-bg-blue-light flex-sm-fill">
																		<a class="nav-link active" data-bs-toggle="tab" href="#mo-caw-external-api-display-data-via-shortcode-html">HTML</a>
																	</li>
																	<li class="nav-item mx-1 mo-caw-bg-blue-light flex-sm-fill">
																		<a class="nav-link" data-bs-toggle="tab" href="#mo-caw-external-api-display-data-via-shortcode-css">CSS</a>
																	</li>
																	<li class="nav-item mo-caw-bg-blue-light flex-sm-fill">
																		<a class="nav-link" data-bs-toggle="tab" href="#mo-caw-external-api-display-data-via-shortcode-js">JS</a>
																	</li>
																</ul>
																<div class="tab-content pb-3">
																	<div class="tab-pane active" id="mo-caw-external-api-display-data-via-shortcode-html">
																		<div class="d-flex justify-content-between align-items-center my-3">
																			<label class="form-label mo-caw-form-label fw-bolder">Configure HTML</label>
																			<span>
																				<button class="mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-aac-plan mo-caw-add-standard-tooltip mo-caw-add-bac-tooltip mo-caw-add-aac-tooltip border-0 bg-white p-0" type="button" onclick="moCawAddField('external', 'mo-caw-external-api-display-data-via-shortcode-html-duplicate-div-0', 'mo-caw-external-api-display-data-via-shortcode-html-duplicate-div-', this.nextElementSibling)"><i class="fa-solid fa-plus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
																				<button class="mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-aac-plan mo-caw-add-standard-tooltip mo-caw-add-bac-tooltip mo-caw-add-aac-tooltip border-0 bg-white p-0" type="button" onclick="moCawRemoveField('mo-caw-external-api-display-data-via-shortcode-html-duplicate-div-', this)"><i class="fa-solid fa-minus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
																			</span>
																		</div>
																		<?php if ( ! empty( $this->shortcode_settings['html'] ) ) : ?>
																			<?php foreach ( $this->shortcode_settings['html'] as $index => $value ) : ?>
																				<div class="row mb-3" id="mo-caw-external-api-display-data-via-shortcode-html-duplicate-div-<?php echo esc_attr( $index ); ?>">
																					<div class="mb-3">
																						<div class="d-flex justify-content-between align-items-center">
																							<input type="text" class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" name="mo-caw-external-api-html-response-attribute[]" value="<?php echo esc_attr( $value['reference_key'] ); ?>" placeholder="Response Attribute">
																						</div>
																					</div>
																					<div class="col">
																						<div class="d-flex justify-content-between align-items-center">
																							<textarea class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan form-control mo-caw-form-control py-1" name="mo-caw-external-api-html-code[]" placeholder="Your HTML code goes here..." rows="10"><?php echo esc_html( $value['html'] ); ?></textarea>
																						</div>
																					</div>
																					<div class="btn-group mt-3" role="group" aria-label="HTML Looped or Fixed">
																						<input type="radio" class="btn-check" name="mo-caw-external-api-html-looped-or-fixed-<?php echo esc_attr( $index ); ?>[]" id="mo-caw-external-api-html-fixed-<?php echo esc_attr( $index ); ?>" value="fixed" autocomplete="off" <?php echo ( false === $value['is_loop'] ) ? 'checked' : ''; ?>>
																						<label class="btn btn-outline-primary" for="mo-caw-external-api-html-fixed-<?php echo esc_attr( $index ); ?>">Fixed</label>
																						<input type="radio" class="btn-check" name="mo-caw-external-api-html-looped-or-fixed-<?php echo esc_attr( $index ); ?>[]" id="mo-caw-external-api-html-looped-<?php echo esc_attr( $index ); ?>" value="looped" autocomplete="off" <?php echo ( true === $value['is_loop'] ) ? 'checked' : ''; ?>>
																						<label class="btn btn-outline-primary" for="mo-caw-external-api-html-looped-<?php echo esc_attr( $index ); ?>">Looped</label>
																					</div>
																				</div>
																			<?php endforeach; ?>
																		<?php else : ?>
																			<div class="row mb-3" id="mo-caw-external-api-display-data-via-shortcode-html-duplicate-div-0">
																				<div class="mb-3">
																					<div class="d-flex justify-content-between align-items-center">
																						<input type="text" class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" name="mo-caw-external-api-html-response-attribute[]" placeholder="Response Attribute" >
																					</div>
																				</div>
																				<div class="col">
																					<div class="d-flex justify-content-between align-items-center">
																						<textarea class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan form-control mo-caw-form-control py-1" name="mo-caw-external-api-html-code[]" placeholder="Your HTML code goes here..." rows="10" ></textarea>
																					</div>
																				</div>
																				<div class="btn-group mt-3" role="group" aria-label="HTML Looped or Fixed">
																					<input type="radio" class="mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-aac-plan btn-check" name="mo-caw-external-api-html-looped-or-fixed-0[]" id="mo-caw-external-api-html-fixed-0" value="fixed" autocomplete="off" checked >
																					<label class="btn btn-outline-primary" for="mo-caw-external-api-html-fixed-0">Fixed</label>
																					<input type="radio" class="mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-aac-plan btn-check" name="mo-caw-external-api-html-looped-or-fixed-0[]" id="mo-caw-external-api-html-looped-0" value="looped" autocomplete="off" >
																					<label class="btn btn-outline-primary" for="mo-caw-external-api-html-looped-0">Looped</label>
																				</div>
																			</div>
																		<?php endif; ?>
																	</div>
																	<div class="tab-pane fade" id="mo-caw-external-api-display-data-via-shortcode-css">
																		<div class="col mt-3">
																			<div class="d-flex justify-content-between align-items-center">
																				<textarea class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan form-control mo-caw-form-control py-1" name="mo-caw-external-api-css[]" placeholder="Your CSS goes here..." rows="10" ><?php echo esc_attr( $this->shortcode_settings['css'][0] ?? '' ); ?></textarea>
																			</div>
																		</div>
																	</div>
																	<div class="tab-pane fade" id="mo-caw-external-api-display-data-via-shortcode-js">
																		<div class="col mt-3">
																			<div class="d-flex justify-content-between align-items-center">
																				<textarea class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan form-control mo-caw-form-control py-1" name="mo-caw-external-api-js[]" placeholder="Your JS goes here..." rows="10" ><?php echo esc_attr( $this->shortcode_settings['js'][0] ?? '' ); ?></textarea>
																			</div>
																		</div>
																	</div>
																</div>
																</div>
															</div>
														</div>
												</div>
											</div>
											<div class="mo-caw-show-or-hide-element d-none" id="mo-caw-external-api-store-data-in-database-inputs">
												<div class="row">
													<div class="mb-3 col">
														<label for="mo-caw-external-api-table" class="form-label mo-caw-form-label">Table Name</label>
														<div class="d-flex justify-content-between align-items-center">
															<input type="text" class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-external-api-table" name="mo-caw-external-api-table" value="wp_options" placeholder="Database table" aria-readonly="true" readonly >
														</div>
													</div>
													<div class="mb-3 col">
														<label for="mo-caw-external-api-columns" class="form-label mo-caw-form-label">Option Name</label>
														<div class="d-flex justify-content-between align-items-center">
															<input type="text" class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-aac-plan mo-caw-crown-aac-plan form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-external-api-columns" name="mo-caw-external-api-columns" value="<?php echo isset( $this->subsequent_actions['store_in_database']['columns'] ) ? esc_attr( $this->subsequent_actions['store_in_database']['columns'] ) : ''; ?>" placeholder="Option name" >
														</div>
													</div>
												</div>
											</div>
											<div class="mo-caw-show-or-hide-element d-none" id="mo-caw-external-api-call-another-api-inputs">
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</form>
		<?php if ( $test_mode ) : ?>
			<div class="d-flex justify-content-between align-items-end my-4">
				<div class="d-flex justify-content-center align-items-baseline">
					<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">Test Results</h6>
					<span class="ms-2 badge rounded-pill" id="mo-caw-external-api-test-result-status"></span>
				</div>
				<div class="dropdown">
					<button class="btn mo-caw-btn-outline-blue-medium dropdown-toggle mo-caw-rounded-16 mo-caw-dropdown-toggle mo-caw-element-to-toggle mo-caw-light-mode" type="button" id="mo-caw-external-api-test-result-dropdown" data-bs-toggle="dropdown" aria-expanded="false" <?php echo esc_attr( $this->license_status ); ?>>
						Result format
					</button>
					<ul class="dropdown-menu mo-caw-dropdown-menu" aria-labelledby="mo-caw-external-api-test-result-dropdown">
						<li><a class="dropdown-item" href="<?php echo esc_url( add_query_arg( array( 'output-format' => Constants::JSON ) ) ); ?>">JSON</a></li>
						<li><a class="dropdown-item" href="<?php echo esc_url( add_query_arg( array( 'output-format' => Constants::TABLE ) ) ); ?>">Table</a></li>
						<li><a class="dropdown-item" href="<?php echo esc_url( add_query_arg( array( 'output-format' => Constants::RAW ) ) ); ?>">Raw</a></li>
					</ul>
				</div>
			</div>
			<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16 overflow-auto mo-caw-test-result" id="mo-caw-external-api-test-result">
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Execute custom APIs export.
	 *
	 * @return void
	 */
	private function display_external_connection_export() {
	}

	/**
	 * AJAX callback function to get API response of the external API and return is requested format.
	 *
	 * @return void
	 */
	public static function get_api_response() {
		if ( Utils::mo_caw_require_capability() ) {
			if ( isset( $_POST['nonce'] ) && check_ajax_referer( 'mo_caw_external_api_get_response', 'nonce' ) ) {
				if ( isset( $_POST['api-name'] ) ) {
					$api_name      = sanitize_text_field( wp_unslash( $_POST['api-name'] ) );
					$api_method    = isset( $_POST['api-method'] ) ? sanitize_text_field( wp_unslash( $_POST['api-method'] ) ) : '';
					$output_format = isset( $_POST['output-format'] ) ? sanitize_text_field( wp_unslash( $_POST['output-format'] ) ) : Constants::JSON;

					$api_call_class = Utils::validate_class_name( Constants::PLAN_NAMESPACE . '\Functionality\\', 'External_API_Connection' );

					$api_call_class_instance = new $api_call_class();

					$response = $api_call_class_instance->external_api_initiate( $api_name, false, array(), false, array( 'method' => $api_method ) );

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
							$html  = '<table id="mo-caw-test-configuration" class="table table-bordered text-center"> <tr class="table-light"><th>Attribute Name</th><th>Attribute Value</th></tr>';
							$html .= $api_call_class_instance->generate_api_response_table_rows( '', json_decode( $response ) );
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
}

