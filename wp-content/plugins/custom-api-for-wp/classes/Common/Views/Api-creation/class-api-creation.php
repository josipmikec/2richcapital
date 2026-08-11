<?php
/**
 * This file handles display content for to API_Creation.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Views;

use MO_CAW\Common\Utils;
use MO_CAW\Common\DB_Utils;
use MO_CAW\Common\Constants;

/**
 * This class deals with rendering common views for Custom GUI API.
 */
class API_Creation {

	/**
	 * Name of the API.
	 *
	 * @var string
	 */
	private $api_name = '';
	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'mo/v1';
	/**
	 * API request method.
	 *
	 * @var string
	 */
	private $method = Constants::HTTP_GET;
	/**
	 * Selected table to perform action on.
	 *
	 * @var string
	 */
	private $selected_table = '';
	/**
	 * Selected table columns to perform action on.
	 *
	 * @var array
	 */
	private $request_columns = array();
	/**
	 * API response details.
	 *
	 * @var array
	 */
	private $response = array();
	/**
	 * Roles allowed to access the API.
	 *
	 * @var array
	 */
	private $blocked_roles = array();
	/**
	 * Common filters such as Orderby.
	 *
	 * @var array
	 */
	private $common_filters = array();
	/**
	 * Filters based on specific values.
	 *
	 * @var array
	 */
	private $value_specific_filter = array();
	/**
	 * Complete configuration of the API.
	 *
	 * @var string
	 */
	private $gui_endpoint_config = array();
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
		if ( isset( $_GET['_wpnonce'] ) && check_admin_referer( 'MO_CAW_API_Creation_' . ucfirst( $action ) . '_Nonce', '_wpnonce' ) ) {
			$session_form_data = isset( $_SESSION['MO_CAW_API_Creation_Form_Data'] ) ? wp_unslash( $_SESSION['MO_CAW_API_Creation_Form_Data'] ) : array(); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization is done in sanitize_nested_array() function.
			Utils::sanitize_nested_array( $session_form_data );
			$this->gui_endpoint_config = ! empty( $session_form_data ) ? $session_form_data : $this->gui_endpoint_config;

			if ( ( Constants::EDIT === $action || Constants::VIEW === $action || Constants::TEST === $action || Constants::DELETE === $action ) && Constants::DISABLED !== $this->license_status ) {
				$this->api_name  = isset( $_GET['api-name'] ) ? sanitize_text_field( wp_unslash( $_GET['api-name'] ) ) : $this->api_name;
				$this->method    = isset( $_GET['method'] ) ? sanitize_text_field( wp_unslash( $_GET['method'] ) ) : $this->method;
				$this->namespace = isset( $_GET['namespace'] ) ? sanitize_text_field( wp_unslash( $_GET['namespace'] ) ) : $this->namespace;

				$row_filter = array(
					'connection_name' => $this->api_name,
					'type'            => Constants::GUI_ENDPOINT,
					'method'          => $this->method,
					'namespace'       => $this->namespace,
				);

				$this->gui_endpoint_config = empty( $this->gui_endpoint_config ) ? DB_Utils::get_configuration( $row_filter )[0] : $this->gui_endpoint_config;
			} elseif ( Constants::ADD === $action && Constants::DISABLED !== $this->license_status ) {
				$this->namespace = $this->gui_endpoint_config['namespace'] ?? $this->namespace;
				$this->method    = $this->gui_endpoint_config['method'] ?? $this->method;
				$this->api_name  = $this->gui_endpoint_config['connection_name'] ?? $this->api_name;
			}

			$this->selected_table        = $this->gui_endpoint_config['configuration']['table'] ?? $this->selected_table;
			$this->request_columns       = $this->gui_endpoint_config['configuration']['request_columns'] ?? $this->request_columns;
			$this->response              = $this->gui_endpoint_config['configuration']['response'] ?? $this->response;
			$this->blocked_roles         = $this->gui_endpoint_config['configuration']['blocked_roles'] ?? $this->blocked_roles;
			$this->common_filters        = $this->gui_endpoint_config['configuration']['common_filters'] ?? $this->common_filters;
			$this->value_specific_filter = $this->gui_endpoint_config['configuration']['value_specific_filter'] ?? $this->value_specific_filter;
		}
		// The else condition is not required here as WordPress handles failure in nonce verification itself.
	}

	/**
	 * Display the content for Custom API as per the action.
	 *
	 * @param  string $tab    Active tab name.
	 * @param  string $action Tab current action.
	 * @return void
	 */
	public function display_api_creation_ui( $tab, $action ) {
		switch ( $action ) {
			case Constants::ADD:
				$this->display_api_creation_add_or_edit( $action );
				break;
			case Constants::VIEW:
				$this->display_api_creation_view();
				break;
			case Constants::EDIT:
				$this->display_api_creation_add_or_edit( $action );
				break;
			case Constants::TEST:
				$this->display_api_creation_add_or_edit( $action );
				break;
			case 'export':
				$this->display_api_creation_export();
				break;
			default:
				$this->display_api_creation_all_config();
				break;
		}
	}

	/**
	 * Display all custom APIs config.
	 *
	 * @return void
	 */
	private function display_api_creation_all_config() {
		$row_filter['type'] = Constants::GUI_ENDPOINT;
		$gui_endpoints      = DB_Utils::get_configuration( $row_filter );
		$gui_endpoints      = Utils::organize_endpoints_by_namespace( $gui_endpoints );
		?>
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">Configured APIs</h6>
			<div class="d-grid gap-2 d-md-block">
				<button class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4 mo-caw-element-to-toggle mo-caw-light-mode" method="button" data-bs-toggle="modal" data-bs-target="#" aria-hidden="true" hidden <?php echo esc_attr( $this->license_status ); ?>>Export Postman Collection</button>
				<a class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4 <?php echo esc_attr( $this->license_status ); ?>" type="button" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-api&action=add', 'MO_CAW_API_Creation_Add_Nonce' ) ); ?>">Create API</a>
			</div>
			<div class="modal fade mo-caw-export-modal" id="mo-caw-custom-api-export-modal" tabindex="-1" aria-labelledby="mo-caw-custom-api-export-modal-label" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered justify-content-center">
					<form method="POST">
						<?php wp_nonce_field( 'MO_CAW_API_Creation_Export', 'MO_CAW_API_Creation_Nonce' ); ?>
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="mo-caw-custom-api-export-modal-label">Export Postman Collection</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<h6 class="mo-caw-element-to-toggle mo-caw-light-mode">Select namespaces to export</h6>
									<div class="form-check d-flex align-items-center justify-content-start p-2">
										<input class="form-check-input m-0 bg-white me-2 mo-caw-select-all-checkbox" type="checkbox" value="" id="mo-caw-custom-api-export-select-all" data-target="mo-caw-custom-api-export">
										<label class="form-check-label" for="mo-caw-custom-api-export-select-all">Select All</label>
									</div>
									<?php foreach ( $gui_endpoints as $namespace => $details ) : ?>
										<div class="form-check d-flex align-items-center justify-content-start p-2">
											<input class="form-check-input m-0 bg-white me-2 mo-caw-custom-api-export" type="checkbox" value="<?php echo esc_attr( $namespace ); ?>" id="mo-caw-custom-api-export-<?php echo esc_attr( $namespace ); ?>" name="mo-caw-custom-api-export[]">
											<label class="form-check-label" for="mo-caw-custom-api-export"><?php echo esc_attr( $namespace ); ?></label>
										</div>
									<?php endforeach; ?>
							</div>
							<div class="modal-footer d-md-flex justify-content-md-center">
								<button class="btn btn-primary mo-caw-bg-blue-medium mo-caw-rounded-16" type="submit">Export</button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php if ( ! empty( $gui_endpoints ) ) : ?>
			<div class="accordion" id="mo-caw-custom-api-accordion">
				<div class="accordion accordion-flush" id="mo-caw-custom-api-accordion-flush">
				<?php $index = 0; ?>
				<?php foreach ( $gui_endpoints as $namespace => $details ) : ?>
					<div class="accordion-item mo-caw-accordion-item mb-2 border-0 mo-caw-rounded-8 mo-caw-element-to-toggle mo-caw-light-mode">
						<h2 class="accordion-header" id="mo-caw-custom-api-config-accordion-<?php echo esc_attr( $index ); ?>">
						<button class="accordion-button mo-caw-bg-blue-light fw-normal shadow-none mo-caw-rounded-8 <?php echo 0 === $index ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#mo-caw-create-api-accordion-collapse-<?php echo esc_attr( $index ); ?>" aria-expanded="false" aria-controls="flush-collapseOne">
							<?php echo esc_attr( $namespace ); ?>
						</button>
						</h2>
						<div id="mo-caw-create-api-accordion-collapse-<?php echo esc_attr( $index ); ?>" class="accordion-collapse collapse <?php echo 0 === $index ? 'show' : ''; ?>" aria-labelledby="mo-caw-custom-api-config-accordion-<?php echo esc_attr( $index ); ?>" data-bs-parent="#mo-caw-custom-api-accordion-flush">
							<div class="accordion-body">
								<table class="table text-center fs-6 border border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
									<thead>
										<tr class="mo-caw-bg-blue-light mo-caw-rounded-top border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
											<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">API Name</th>
											<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">Method</th>
											<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">Enable/Disable</th>
											<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">Actions</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $details as $position => $namespace_endpoint ) : ?>
											<tr>
												<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_attr( $namespace_endpoint['connection_name'] ); ?></td>
												<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><span class="rounded-pill py-2 px-4 mo-caw-<?php echo esc_attr( $namespace_endpoint['method'] ); ?>-method"><?php echo esc_attr( strtoupper( $namespace_endpoint['method'] ) ); ?></span></td>
												<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">
													<div class="form-check form-switch d-flex justify-content-center align-items-center">
														<input class="form-check-input mo-caw-toggle-switch" type="checkbox" onchange="moCawEnableDisableApi(this, '<?php echo esc_attr( wp_create_nonce( 'mo_caw_custom_api_enable_disable_api' ) ); ?>', '<?php echo esc_attr( $namespace_endpoint['connection_name'] ); ?>', '<?php echo esc_attr( $namespace_endpoint['method'] ); ?>', '<?php echo esc_attr( $namespace ); ?>', '<?php echo esc_attr( Constants::GUI_ENDPOINT ); ?>')" 
															<?php
															echo ( ! isset( $namespace_endpoint['is_enabled'] ) || $namespace_endpoint['is_enabled'] ) ? 'checked ' : '';
															echo esc_attr( $this->license_status );
															?>
														>
													</div>
												</td>
												<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">
													<div class="dropdown">
														<button class="btn btn-secondary dropdown-toggle mo-caw-dropdown-toggle rounded-pill mo-caw-bg-grey-light border-0 mo-caw-text-grey-medium" type="button" id="mo-caw-api-creation-actions-<?php echo esc_attr( $position ); ?>" data-bs-toggle="dropdown" aria-expanded="false">Pick an option</button>
														<ul class="dropdown-menu mo-caw-dropdown-menu" aria-labelledby="mo-caw-api-creation-actions-<?php echo esc_attr( $position ); ?>">
															<li><a class="dropdown-item d-flex align-items-center justify-content-between" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-api&action=view&api-name=' . $namespace_endpoint['connection_name'] . '&method=' . $namespace_endpoint['method'] . '&namespace=' . $namespace, 'MO_CAW_API_Creation_View_Nonce' ) ); ?>"><span>View</span><i class="far fa-eye mo-caw-text-black"></i></a></li>
															<li><a class="dropdown-item d-flex align-items-center justify-content-between <?php echo esc_attr( $this->license_status ); ?>" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-api&action=edit&api-name=' . $namespace_endpoint['connection_name'] . '&method=' . $namespace_endpoint['method'] . '&namespace=' . $namespace, 'MO_CAW_API_Creation_Edit_Nonce' ) ); ?>"><span>Edit</span><i class="fas fa-pencil mo-caw-text-black"></i></a></li>
															<li><a class="dropdown-item d-flex align-items-center justify-content-between <?php echo esc_attr( $this->license_status ); ?>" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-api&action=test&api-name=' . $namespace_endpoint['connection_name'] . '&method=' . $namespace_endpoint['method'] . '&namespace=' . $namespace . '&test-mode=true', 'MO_CAW_API_Creation_Test_Nonce' ) ); ?>"><span>Test</span><i class="fas fa-check mo-caw-text-black"></i></a></li>
															<li><a class="dropdown-item d-flex align-items-center justify-content-between <?php echo esc_attr( $this->license_status ); ?>" onclick="if(confirm('Are you sure you want to delete this API?')){document.getElementById('mo-caw-api-creation-delete-form-<?php echo esc_attr( $namespace_endpoint['connection_name'] ) . '-' . esc_attr( $namespace_endpoint['method'] ) . '-' . esc_attr( $namespace ); ?>').submit(); return false;}"><span>Delete</span><i class="far fa-trash-can mo-caw-text-black"></i></a></li>
														</ul>
													</div>
													<form method="POST" id="mo-caw-api-creation-delete-form-<?php echo esc_attr( $namespace_endpoint['connection_name'] ) . '-' . esc_attr( $namespace_endpoint['method'] ) . '-' . esc_attr( $namespace ); ?>">
														<?php wp_nonce_field( 'MO_CAW_API_Creation_Delete', 'MO_CAW_API_Creation_Nonce' ); ?>
														<input type="hidden" name="api-name" value="<?php echo esc_attr( $namespace_endpoint['connection_name'] ); ?>">
														<input type="hidden" name="method" value="<?php echo esc_attr( $namespace_endpoint['method'] ); ?>">
														<input type="hidden" name="namespace" value="<?php echo esc_attr( $namespace ); ?>">
													</form>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
					<?php ++$index; ?>
				<?php endforeach; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="d-flex align-items-center flex-column">
				<img src="<?php echo esc_url( MO_CUSTOM_API_URL . 'classes/Common/Resources/Images/not-found.jpeg' ); ?>" width="450px" >
				<h6 class="mt-5 text-secondary">Oops! Seems like you have not created any Custom APIs.</h6>
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
	private function display_api_creation_add_or_edit( $action ) {
		global $wpdb;
		global $wp_roles;

		$wp_column_names = array();
		if ( Constants::EDIT === $action || Constants::TEST === $action ) {
			$wp_column_names = DB_Utils::get_all_column_names( $this->selected_table );
		}

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

		// Get all namespaces.
		$table_configuration = array(
			'type' => Constants::GUI_ENDPOINT,
		);

		$all_namespaces = DB_Utils::get_all_namespaces( $table_configuration );
		$namespaces     = array();
		foreach ( $all_namespaces as $namespace ) {
			if ( ! in_array( $namespace->namespace, $namespaces, true ) ) {
				array_push( $namespaces, $namespace->namespace );
			}
		}

		$test_mode = isset( $_GET['test-mode'] ) ? filter_var( sanitize_text_field( wp_unslash( $_GET['test-mode'] ) ), FILTER_VALIDATE_BOOLEAN ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified in the constructor.
		?>
		<form method="POST" id="mo-caw-custom-api-form" class="mo-caw-element-to-toggle mo-caw-light-mode">
			<?php wp_nonce_field( 'MO_CAW_API_Creation', 'MO_CAW_API_Creation_Nonce' ); ?>
			<input type="hidden" id="mo-caw-custom-api-test-mode" name="mo-caw-custom-api-test-mode" value="false">
			<div class="d-flex justify-content-between align-items-end mb-4">
				<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode"><?php echo Constants::ADD === $action ? 'Create Custom API' : 'Edit API - ' . esc_attr( $this->api_name ); ?></h6>
				<div class="d-grid gap-2 d-md-block">
					<button class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4 mo-caw-element-to-toggle mo-caw-light-mode" type="button" 
					<?php
					if ( Constants::DISABLED !== $this->license_status ) {
						echo esc_attr( 'onclick=moCawEnableTestMode()' );
					}
					?>
					<?php echo esc_attr( $this->license_status ); ?>>Test</button>
					<button class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4" id="mo-caw-custom-api-form-submit" type="submit" <?php echo esc_attr( $this->license_status ); ?>>Save</button>
				</div>
			</div>
			<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16">
				<div class="mb-3">
					<label for="mo-caw-custom-api-namespace" class="form-label mo-caw-form-label">Custom Namespace</label>
					<div class="d-flex justify-content-between align-items-center">
						<input type="text" class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-eai-plan mo-caw-crown-eai-plan form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-custom-api-namespace" name="mo-caw-custom-api-namespace" value="<?php echo esc_attr( $this->namespace ); ?>" placeholder="Custom Namespace (eg. mo/v1)" list="mo-caw-custom-api-namespace-list" pattern="^(?=.{1,15}$)[A-Za-z]+/v[0-9]+$" title="Namespace must be of max length 15 and contain at least one '/' in between, [A-Z, a-z] before '/', 'v' (denoting version) and numbers only after '/'" aria-required="true" required <?php echo Constants::EDIT === $action || Constants::TEST === $action ? 'disabled' : ''; ?>>
						<?php if ( Constants::EDIT === $action || Constants::TEST === $action ) : ?>
							<input type="hidden" name="mo-caw-custom-api-namespace" value="<?php echo esc_attr( $this->namespace ); ?>">
						<?php endif; ?>
					</div>
					<datalist id="mo-caw-custom-api-namespace-list">
						<?php foreach ( $namespaces as $namespace ) : ?>
						<option value="<?php echo esc_attr( $namespace ); ?>">
						<?php endforeach; ?>
					</datalist>
				</div>
				<div class="row">
					<div class="mb-3 col">
						<label for="mo-caw-custom-api-name" class="form-label mo-caw-form-label">API Name</label>
						<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-custom-api-name" name="mo-caw-custom-api-name" value="<?php echo esc_attr( $this->api_name ); ?>" placeholder="API Name" pattern="^(?=.{1,25}$)[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$" title="Should be of maximum length 25 and only '-' are accepted in between along with [A-Z, a-z and 0-9]" aria-required="true" required <?php echo Constants::EDIT === $action || Constants::TEST === $action ? 'disabled' : ''; ?>>
						<?php if ( Constants::EDIT === $action || Constants::TEST === $action ) : ?>
							<input type="hidden" name="mo-caw-custom-api-name" value="<?php echo esc_attr( $this->api_name ); ?>">
						<?php endif; ?>
					</div>
					<div class="mb-3 col">
						<label for="mo-caw-custom-api-method" class="form-label mo-caw-form-label">Method <i class="fas fa-info rounded-circle border py-1 px-2" data-bs-toggle="tooltip" data-bs-placement="bottom" title="HTTP request method"></i></label>
						<select class="form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode mo-caw-method-selector" id="mo-caw-custom-api-method" name="mo-caw-custom-api-method" aria-label="#mo-caw-custom-api-method" aria-required="true" required <?php echo Constants::EDIT === $action || Constants::TEST === $action ? 'disabled' : ''; ?>>
							<option value="">Select Method</option>
							<option value="get" <?php echo Constants::HTTP_GET === $this->method ? 'selected' : ''; ?>>GET</option>
							<option value="post" <?php echo Constants::HTTP_POST === $this->method ? 'selected' : ''; ?> class="mo-caw-disable-standard-plan mo-caw-disable-eai-plan mo-caw-add-standard-tooltip mo-caw-add-eai-tooltip">POST</option>
							<option value="put" <?php echo Constants::HTTP_PUT === $this->method ? 'selected' : ''; ?> class="mo-caw-disable-standard-plan mo-caw-disable-eai-plan mo-caw-add-standard-tooltip mo-caw-add-eai-tooltip">PUT</option>
							<option value="delete" <?php echo Constants::HTTP_DELETE === $this->method ? 'selected' : ''; ?> class="mo-caw-disable-standard-plan mo-caw-disable-eai-plan mo-caw-add-standard-tooltip mo-caw-add-eai-tooltip">DELETE</option>
						</select>
						<?php if ( Constants::EDIT === $action || Constants::TEST === $action ) : ?>
							<input type="hidden" name="mo-caw-custom-api-method" value="<?php echo esc_attr( $this->method ); ?>">
						<?php endif; ?>
					</div>
				</div>
				<div class="row">
					<div class="mb-3 col mo-caw-form-content d-none" request-methods="get post put delete">
						<label for="mo-caw-custom-api-table" class="form-label mo-caw-form-label">Table</label>
						<select class="form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-custom-api-table" name="mo-caw-custom-api-table" aria-label="#mo-caw-custom-api-method" aria-required="true" required onchange="moCawCustomApiGetTableColumns(this.value, '<?php echo esc_attr( wp_create_nonce( 'mo_caw_get_columns_nonce' ) ); ?>', '<?php echo esc_attr( Constants::CUSTOM_API_TAB ); ?>')">
							<option value="">Select Table</option>
							<?php foreach ( $table_names as $table ) : ?>
							<option value="<?php echo esc_attr( $table ); ?>" <?php echo $table === $this->selected_table ? 'selected' : ''; ?>><?php echo esc_attr( $table ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mb-3 col mo-caw-form-content d-none" request-methods="get post put">
						<label for="mo-caw-custom-api-columns" class="form-label mo-caw-form-label">Request Columns</label>
						<div class="dropdown">
							<button class="btn dropdown-toggle mo-caw-dropdown-toggle w-100 bg-white mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode mo-caw-text-grey-medium d-flex justify-content-between align-items-center" type="button" id="mo-caw-custom-api-columns-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
								<?php echo empty( $this->request_columns ) ? 'Select Request Columns' : 'Selected (' . count( $this->request_columns ) . ')'; ?>
							</button>
							<div class="dropdown-menu mo-caw-dropdown-menu w-100" aria-labelledby="mo-caw-custom-api-columns-dropdown">
								<div class="form-check d-flex align-items-center justify-content-start p-2">
									<input class="form-check-input m-0 bg-white me-2 mo-caw-select-all-checkbox" type="checkbox" value="" id="mo-caw-custom-api-columns-select-all" data-target="mo-caw-custom-api-columns" <?php echo empty( $this->selected_table ) ? 'hidden' : ''; ?> <?php echo ( count( $this->request_columns ) === count( $wp_column_names ) ) ? 'checked' : ''; ?>>
									<label class="form-check-label" for="mo-caw-custom-api-columns-select-all"><?php echo empty( $this->selected_table ) ? 'Please select a table' : 'Select All'; ?></label>
								</div>
								<?php if ( Constants::EDIT === $action || Constants::TEST === $action ) : ?>
									<?php foreach ( $wp_column_names as $column ) : ?>
										<div class="form-check d-flex align-items-center justify-content-start mo-caw-custom-api-columns-div p-2">
											<input class="form-check-input m-0 bg-white me-2 mo-caw-custom-api-columns" type="checkbox" value="<?php echo esc_attr( $column ); ?>" id="mo-caw-custom-api-columns-<?php echo esc_attr( $column ); ?>" name="mo-caw-custom-api-columns[]" <?php echo in_array( $column, $this->request_columns, true ) ? 'checked' : ''; ?>>
											<label class="form-check-label" for="mo-caw-custom-api-columns-<?php echo esc_attr( $column ); ?>""><?php echo esc_attr( $column ); ?></label>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
				<div class="accordion d-none" id="mo-caw-adv-setting-accordion">
					<div class="accordion accordion-flush" id="mo-caw-custom-api-accordion-flush">
						<div class="accordion-item mb-2 border-0 mo-caw-rounded-8 mo-caw-accordion-item mo-caw-element-to-toggle mo-caw-light-mode">
							<h2 class="accordion-header" id="mo-caw-custom-api-adv-settings-accordion-heading">
								<button class="accordion-button collapsed mo-caw-bg-blue-light shadow-none mo-caw-rounded-8" type="button" data-bs-toggle="collapse" data-bs-target="#mo-caw-custom-api-adv-settings-accordion-collapse" aria-expanded="false" aria-controls="mo-caw-custom-api-adv-settings-accordion-collapse">
									Advance Settings
								</button>
							</h2>
							<div id="mo-caw-custom-api-adv-settings-accordion-collapse" class="accordion-collapse collapse" aria-labelledby="mo-caw-custom-api-adv-settings-accordion-heading" data-bs-parent="#mo-caw-custom-api-accordion-flush">
								<div class="accordion-body">
									<div class="row">
										<div class="mb-3 col mo-caw-form-content d-none" request-methods="get post put delete">
											<label for="mo-caw-custom-api-response-format" class="form-label mo-caw-form-label">Response Type <i class="fas fa-info rounded-circle border py-1 px-2" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Send response in default or your custom format"></i></label>
											<div class="d-flex justify-content-between align-items-center">
												<select class="mo-caw-crown-standard-plan mo-caw-crown-eai-plan form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-response-type" name="mo-caw-custom-api-response-type" aria-label="#mo-caw-custom-api-method">
													<option value="default" <?php echo ! isset( $this->response['response_type'] ) || Constants::DEFAULT === $this->response['response_type'] ? 'selected' : ''; ?>>Default</option>
													<option value="custom" <?php echo isset( $this->response['response_type'] ) && Constants::CUSTOM === $this->response['response_type'] ? 'selected' : ''; ?> class="mo-caw-disable-standard-plan mo-caw-disable-eai-plan" >Custom Response</option>
												</select>
											</div>
										</div>
										<div class="mb-3 col mo-caw-form-content d-none" request-methods="get post put delete">
											<label for="mo-caw-custom-api-response-format" class="form-label mo-caw-form-label">Response Format</label>
											<div class="d-flex justify-content-between align-items-center">
												<select class="form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-custom-api-response-format" name="mo-caw-custom-api-response-format" aria-label="#mo-caw-custom-api-method">
													<option value="json" <?php echo ! isset( $this->response['response_content_type'] ) || Constants::JSON === $this->response['response_content_type'] ? 'selected' : ''; ?>>JSON</option>
													<option value="xml" <?php echo isset( $this->response['response_content_type'] ) && Constants::XML === $this->response['response_content_type'] ? 'selected' : ''; ?> aria-hidden="true" hidden >XML</option>
												</select>
											</div>
										</div>
										<div id="mo-caw-response-format-block" class="<?php echo ! isset( $this->response['response_type'] ) || Constants::DEFAULT === $this->response['response_type'] ? 'd-none' : ''; ?>">
											<ul class="nav nav-tabs nav-pills border-0 flex-column flex-sm-row text-center rounded">
												<li class="nav-item mo-caw-bg-blue-light flex-sm-fill">
													<a class="nav-link active" data-bs-toggle="tab" href="#mo-caw-custom-api-response-type-success">Success</a>
												</li>
												<li class="nav-item mx-1 mo-caw-bg-blue-light flex-sm-fill">
													<a class="nav-link" data-bs-toggle="tab" href="#mo-caw-custom-api-response-type-error">Error</a>
												</li>
												<li class="nav-item mo-caw-bg-blue-light flex-sm-fill">
													<a class="nav-link" data-bs-toggle="tab" href="#mo-caw-custom-api-response-type-authentication">Authentication</a>
												</li>
											</ul>
											<div class="tab-content pb-3">
												<div class="tab-pane active" id="mo-caw-custom-api-response-type-success">
													<label for="mo-caw-custom-api-response-type-success-format"></label>
													<textarea class="form-control" placeholder="{&#13;&#10;  &quot;message&quot;: &quot;success&quot;,&#13;&#10;  &quot;data&quot;: &quot;$response_data&quot;&#13;&#10;}&#13;&#10;" id="mo-caw-custom-api-response-type-success-format" name="mo-caw-custom-api-response-type-success-format" rows="10"><?php echo ( isset( $this->response['response_content']['success'] ) && ! empty( $this->response['response_content']['success'] ) ) ? esc_attr( $this->response['response_content']['success'] ) : ''; ?></textarea>
												</div>
												<div class="tab-pane fade" id="mo-caw-custom-api-response-type-error">
													<label for="mo-caw-custom-api-response-type-error-format"></label>
													<textarea class="form-control" placeholder="{&#13;&#10;  &quot;message&quot;: &quot;error&quot;,&#13;&#10;  &quot;data&quot;: &quot;$response_data&quot;&#13;&#10;}&#13;&#10;" id="mo-caw-custom-api-response-type-error-format" name="mo-caw-custom-api-response-type-error-format" rows="10"><?php echo ( isset( $this->response['response_content']['error'] ) && ! empty( $this->response['response_content']['error'] ) ) ? esc_attr( $this->response['response_content']['error'] ) : ''; ?></textarea>
												</div>
												<div class="tab-pane fade" id="mo-caw-custom-api-response-type-authentication">
													<label for="mo-caw-custom-api-response-type-authentication-format"></label>
													<textarea class="form-control" placeholder="{&#13;&#10;  &quot;message&quot;: &quot;authentication_error&quot;,&#13;&#10;  &quot;data&quot;: &quot;$response_data&quot;&#13;&#10;}&#13;&#10;" id="mo-caw-custom-api-response-type-authentication-format" name="mo-caw-custom-api-response-type-authentication-format" rows="10"><?php echo ( isset( $this->response['response_content']['authentication'] ) && ! empty( $this->response['response_content']['authentication'] ) ) ? esc_attr( $this->response['response_content']['authentication'] ) : ''; ?></textarea>
												</div>
											</div>
										</div>
									</div>
									<div>
										<div class="mb-3">
											<label for="mo-caw-custom-api-allowed-roles" class="form-label mo-caw-form-label">Restrict role-based access</label>
											<div class="dropdown mo-caw-disable-standard-plan mo-caw-disable-eai-plan" >
												<button class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-eai-plan mo-caw-crown-eai-plan btn dropdown-toggle mo-caw-dropdown-toggle w-100 bg-white mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode mo-caw-text-grey-medium d-flex justify-content-between align-items-center" type="button" id="mo-caw-custom-api-allowed-roles-dropdown" data-bs-toggle="dropdown" aria-expanded="false" >
													<?php echo ! empty( $this->blocked_roles ) ? 'Selected (' . count( $this->blocked_roles ) . ')' : 'Select Roles'; ?>
												</button>
												<div class="mo-caw-disable-standard-plan mo-caw-disable-eai-plan dropdown-menu mo-caw-dropdown-menu w-100" aria-labelledby="mo-caw-custom-api-allowed-roles-dropdown" >
													<div class="form-check d-flex align-items-center p-2">
														<input class="form-check-input m-0 bg-white mo-caw-select-all-checkbox me-2" type="checkbox" value="" id="mo-caw-custom-api-allowed-roles-select-all" data-target="mo-caw-custom-api-allowed-roles" <?php echo count( $this->blocked_roles ) === count( $role_slugs ) ? 'checked' : ''; ?>>
														<label class="form-check-label" for="mo-caw-custom-api-allowed-roles-select-all">Select All</label>
													</div>
													<?php foreach ( $role_slugs as $index => $role_slug ) : ?>
													<div class="form-check d-flex align-items-center p-2">
														<input class="form-check-input m-0 bg-white mo-caw-custom-api-allowed-roles me-2" type="checkbox" value="<?php echo esc_attr( $role_slug ); ?>" id="mo-caw-custom-api-allowed-roles-<?php echo esc_attr( $index ); ?>" name="mo-caw-custom-api-allowed-roles[]" <?php echo in_array( $role_slug, $this->blocked_roles, true ) ? 'checked' : ''; ?>>
														<label class="form-check-label" for="mo-caw-custom-api-allowed-roles-<?php echo esc_attr( $index ); ?>"><?php echo esc_attr( $role_names[ $index ] ); ?></label>
													</div>
													<?php endforeach; ?>
												</div>
											</div>
										</div>
										<div class="row mo-caw-form-content d-none" request-methods="get">
											<label class="form-label mo-caw-form-label">Filters <em>(Based on Order)</em></label>
											<div class="mb-3 col">
												<div class="d-flex justify-content-between align-items-center">
													<select class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-eai-plan mo-caw-crown-eai-plan form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-custom-api-common-filter-filter" name="mo-caw-custom-api-common-filter-filter" aria-label="#mo-caw-custom-api-common-filter-filter" >
														<option value="">Filter</option>
														<option value="orderby" selected>Order By</option>
													</select>
												</div>
											</div>
											<div class="mb-3 col">
												<div class="d-flex justify-content-between align-items-center">
													<select class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-eai-plan mo-caw-crown-eai-plan form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-custom-api-common-filter-column" name="mo-caw-custom-api-common-filter-column" aria-label="#mo-caw-custom-api-common-filter-column" >
														<option value="">Column</option>
														<?php if ( 'edit' === $action ) : ?>
															<?php foreach ( $wp_column_names as $column ) : ?>
																<option class="mo-caw-custom-api-common-filter-column-option" value="<?php echo esc_attr( $column ); ?>" <?php echo isset( $this->common_filters['orderby']['column'] ) && $column === $this->common_filters['orderby']['column'] ? 'selected' : ''; ?>><?php echo esc_attr( $column ); ?></option>
															<?php endforeach; ?>
														<?php endif; ?>
													</select>
												</div>
											</div>
											<div class="mb-3 col">
												<div class="d-flex justify-content-between align-items-center">
													<select class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-eai-plan mo-caw-crown-eai-plan form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-custom-api-common-filter-order" name="mo-caw-custom-api-common-filter-order" aria-label="#mo-caw-custom-api-common-filter-order" >
														<option value="">Order</option>
														<option value="asc" <?php echo isset( $this->common_filters['orderby']['option'] ) && 'asc' === $this->common_filters['orderby']['option'] ? 'selected' : ''; ?>>Ascending</option>
														<option value="desc" <?php echo isset( $this->common_filters['orderby']['option'] ) && 'desc' === $this->common_filters['orderby']['option'] ? 'selected' : ''; ?>>Descending</option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div>
										<div class="d-flex justify-content-between align-items-center mb-2 mo-caw-form-content d-none" request-methods="get put delete">
											<label class="form-label mo-caw-form-label">Filters <em>(Based on column, condition and parameter)</em></label>
											<span>
												<button class="mo-caw-disable-standard-plan mo-caw-disable-eai-plan mo-caw-add-standard-tooltip mo-caw-add-eai-tooltip border-0 bg-white p-0" type="button" onclick="moCawAddField('gui', 'mo-caw-custom-api-value-specific-filter-duplicate-div-0', 'mo-caw-custom-api-value-specific-filter-duplicate-div-', this.nextElementSibling)" ><i class="fa-solid fa-plus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
												<button class="mo-caw-disable-standard-plan mo-caw-disable-eai-plan mo-caw-add-standard-tooltip mo-caw-add-eai-tooltip border-0 bg-white p-0" type="button" onclick="moCawRemoveField('mo-caw-custom-api-value-specific-filter-duplicate-div-', this)" ><i class="fa-solid fa-minus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
											</span>
										</div>
										<?php if ( ! empty( $this->value_specific_filter['filter_details'] ) ) : ?>
											<?php $filter_details_count = count( $this->value_specific_filter['filter_details'] ); ?>
											<?php foreach ( $this->value_specific_filter['filter_details'] as $index => $filter_details ) : ?>
												<div class="row" id="mo-caw-custom-api-value-specific-filter-duplicate-div-<?php echo esc_attr( $index ); ?>">
													<?php if ( 1 <= $filter_details_count && 1 <= $index ) : ?>
													<div class="d-flex justify-content-center mb-3 mo-caw-form-content d-none" request-methods="get put delete">
														<input type="hidden" name="mo-caw-custom-api-specific-filter-operator[]" value="<?php echo esc_attr( $this->value_specific_filter['filter_relation'][ $index - 1 ] ); ?>" />
														<button class="btn mo-caw-bg-grey-light mo-caw-text-grey-medium border-0" type="button" id="mo-custom-api-specific-filter-operator-<?php echo esc_attr( $index - 1 ); ?>" onclick="moCawToggleOperation(this)"><?php echo esc_attr( strtoupper( $this->value_specific_filter['filter_relation'][ $index - 1 ] ) ); ?> <i class="fa-solid fa-arrows-rotate"></i></button>
													</div>
													<?php endif; ?>
													<div class="mb-3 col mo-caw-form-content d-none" request-methods="get put delete">
														<div class="d-flex justify-content-between align-items-center">
															<select class="form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode mo-caw-custom-api-specific-filter-column" id="mo-caw-custom-api-specific-filter-column" name="mo-caw-custom-api-specific-filter-column[]" aria-label="#mo-caw-custom-api-specific-filter-column">
																<option value="">Column</option>
																<?php foreach ( $wp_column_names as $column ) : ?>
																<option class="mo-caw-custom-api-specific-filter-column-option" value="<?php echo esc_attr( $column ); ?>" <?php echo $column === $filter_details['column'] ? 'selected' : ''; ?>><?php echo esc_attr( $column ); ?></option>
																<?php endforeach; ?>
															</select>
														</div>
													</div>
													<div class="mb-3 col mo-caw-form-content d-none" request-methods="get put delete">
														<div class="d-flex justify-content-between align-items-center">
															<select class="form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-custom-api-specific-filter-condition" name="mo-caw-custom-api-specific-filter-condition[]" aria-label="#mo-caw-custom-api-specific-filter-condition">
																<option value="">Condition</option>
																<option value="like" <?php echo 'like' === $filter_details['condition'] ? 'selected' : ''; ?>>Like</option>
																<option value="not-like" <?php echo 'not-like' === $filter_details['condition'] ? 'selected' : ''; ?>>Not Like</option>
																<option value="=" <?php echo '=' === $filter_details['condition'] ? 'selected' : ''; ?>>Equal</option>
																<option value="!=" <?php echo '!=' === $filter_details['condition'] ? 'selected' : ''; ?>>Not Equal</option>
																<option value=">" <?php echo '>' === $filter_details['condition'] ? 'selected' : ''; ?>>Greater Than</option>
																<option value="<" <?php echo '<' === htmlspecialchars_decode( $filter_details['condition'] ) ? 'selected' : ''; ?>>Less Than</option>
															</select>
														</div>
													</div>
													<div class="mb-3 col mo-caw-form-content d-none" request-methods="get">
														<div class="d-flex justify-content-between align-items-center">
															<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode pt-1 px-2" id="mo-caw-custom-api-specific-filter-parameter-get-<?php echo esc_attr( $index++ ); ?>" name="mo-caw-custom-api-specific-filter-parameter-get[]" aria-label="#mo-caw-custom-api-specific-filter-parameter-get" value="<?php echo isset( $filter_details['parameter'] ) ? esc_attr( $filter_details['parameter'] ) : ( 'Position_' . esc_attr( $index++ ) ); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Indicates the position of parameter in the API request" placeholder="Parameter position in request URL" aria-readonly="true" readonly>
														</div>
													</div>
													<div class="mb-3 col mo-caw-form-content d-none" request-methods="put">
														<div class="d-flex justify-content-between align-items-center">
															<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode pt-1 px-2" id="mo-caw-custom-api-specific-filter-parameter-put-<?php echo esc_attr( $index++ ); ?>" name="mo-caw-custom-api-specific-filter-parameter-put[]" aria-label="#mo-caw-custom-api-specific-filter-parameter-put" value="<?php echo isset( $filter_details['parameter'] ) ? esc_attr( $filter_details['parameter'] ) : ( 'column_param' . esc_attr( $index++ ) ); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Indicates the name of parameter in the API request" placeholder="Parameter name in request URL">
														</div>
													</div>
													<div class="mb-3 col mo-caw-form-content d-none" request-methods="delete">
														<div class="d-flex justify-content-between align-items-center">
															<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode pt-1 px-2" id="mo-caw-custom-api-specific-filter-parameter-delete-<?php echo esc_attr( $index++ ); ?>" name="mo-caw-custom-api-specific-filter-parameter-delete[]" aria-label="#mo-caw-custom-api-specific-filter-parameter-delete" value="<?php echo isset( $filter_details['parameter'] ) ? esc_attr( $filter_details['parameter'] ) : ( 'column_param' . esc_attr( $index++ ) ); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Indicates the name of parameter in the API request" placeholder="Parameter name in request URL">
														</div>
													</div>
												</div>
											<?php endforeach; ?>
										<?php else : ?>
											<div class="row" id="mo-caw-custom-api-value-specific-filter-duplicate-div-0">
												<div class="mb-3 col mo-caw-form-content d-none" request-methods="get put delete">
													<div class="d-flex justify-content-between align-items-center">
														<select class="form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode mo-caw-custom-api-specific-filter-column" id="mo-caw-custom-api-specific-filter-column" name="mo-caw-custom-api-specific-filter-column[]" aria-label="#mo-caw-custom-api-specific-filter-column">
															<option value="">Column</option>
															<?php if ( 'edit' === $action ) : ?>
																<?php foreach ( $wp_column_names as $column ) : ?>
																	<option class="mo-caw-custom-api-specific-filter-column-option" value="<?php echo esc_attr( $column ); ?>"><?php echo esc_attr( $column ); ?></option>
																<?php endforeach; ?>
															<?php endif; ?>
														</select>
													</div>
												</div>
												<div class="mb-3 col mo-caw-form-content d-none" request-methods="get put delete">
													<div class="d-flex justify-content-between align-items-center">
														<select class="form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-custom-api-specific-filter-condition" name="mo-caw-custom-api-specific-filter-condition[]" aria-label="#mo-caw-custom-api-specific-filter-condition">
															<option value="">Condition</option>
															<option value="like">Like</option>
															<option value="not-like">Not Like</option>
															<option value="=">Equal</option>
															<option value="!=">Not Equal</option>
															<option value=">">Greater Than</option>
															<option value="<">Less Than</option>
														</select>
													</div>
												</div>
												<div class="mb-3 col mo-caw-form-content d-none" request-methods="get">
													<div class="d-flex justify-content-between align-items-center">
														<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode pt-1 px-2" id="mo-caw-custom-api-specific-filter-parameter-get-0" name="mo-caw-custom-api-specific-filter-parameter-get[]" aria-label="#mo-caw-custom-api-specific-filter-parameter-get-0" value="<?php echo 'Position_1'; ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Indicates the position of parameter in the API request" placeholder="Parameter position in request URL" aria-readonly="true" readonly>
													</div>
												</div>
												<div class="mb-3 col mo-caw-form-content d-none" request-methods="put">
													<div class="d-flex justify-content-between align-items-center">
														<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode pt-1 px-2" id="mo-caw-custom-api-specific-filter-parameter-put-0" name="mo-caw-custom-api-specific-filter-parameter-put[]" aria-label="#mo-caw-custom-api-specific-filter-parameter-put-0" value="<?php echo 'column_param1'; ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Indicates the name of parameter in the API request" placeholder="Parameter name in request URL">
													</div>
												</div>
												<div class="mb-3 col mo-caw-form-content d-none" request-methods="delete">
													<div class="d-flex justify-content-between align-items-center">
														<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode pt-1 px-2" id="mo-caw-custom-api-specific-filter-parameter-delete-0" name="mo-caw-custom-api-specific-filter-parameter-delete[]" aria-label="#mo-caw-custom-api-specific-filter-parameter-delete-0" value="<?php echo 'column_param1'; ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Indicates the name of parameter in the API request" placeholder="Parameter name in request URL">
													</div>
												</div>
											</div>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</form>
		<div class="modal fade mo-caw-export-modal" id="mo-caw-custom-api-test-inputs-modal" tabindex="-1" aria-labelledby="mo-caw-custom-api-test-inputs-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered justify-content-center">
				<form method="POST" id="mo-caw-custom-api-test-inputs-form">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="mo-caw-custom-api-test-inputs-modal-label">Values to run test</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
						</div>
						<div class="modal-footer d-md-flex justify-content-md-center">
							<button class="btn btn-primary mo-caw-bg-blue-medium mo-caw-rounded-16" type="button" onclick="moCawGetCustomAPITestResult('<?php echo esc_attr( site_url() ); ?>')">Continue</button>
						</div>
					</div>
				</form>
			</div>
		</div>
		<?php if ( $test_mode ) : ?>
			<div class="d-flex justify-content-between align-items-end my-4">
				<div class="d-flex justify-content-center align-items-baseline">
					<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">Test Results</h6>
					<span class="ms-2 badge rounded-pill" id="mo-caw-custom-api-test-result-status"></span>
				</div>
			</div>
			<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16 mo-caw-test-result" id="mo-caw-custom-api-test-result">
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Display custom APIs view page.
	 *
	 * @return void
	 */
	private function display_api_creation_view() {
		$route = site_url() . '/wp-json/' . $this->namespace . '/' . $this->api_name;

		if ( Constants::HTTP_GET === $this->method ) {
			if ( ! empty( $this->value_specific_filter['filter_details'] ) ) {

				$filter_details  = $this->value_specific_filter['filter_details'];
				$ordered_columns = array();
				foreach ( $filter_details as $filter_detail ) {
					$ordered_columns[ $filter_detail['parameter'] ] = $filter_detail['parameter'];
				}
				ksort( $ordered_columns );
				foreach ( $ordered_columns as $column_name ) {
					$route = $route . '/<' . $column_name . '>';
				}
			}
		} elseif ( Constants::HTTP_DELETE === $this->method ) {
			if ( ! empty( $this->value_specific_filter['filter_details'] ) ) {
				$filter_details = $this->value_specific_filter['filter_details'];
				$request_params = array();
				foreach ( $filter_details as $filter_detail ) {
					$request_params[ $filter_detail['parameter'] ] = '<' . $filter_detail['parameter'] . '>';
				}
				$route = add_query_arg( $request_params, $route );
			}
		}

		?>

		<div class="d-flex justify-content-between align-items-end mb-4">
			<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode"><?php echo 'View API - ' . esc_attr( $this->api_name ); ?></h6>
			<div class="d-grid gap-2 d-md-block">
				<!--TODO : Add backend-->
				<button class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 mo-caw-bg-blue-dark px-4 <?php echo esc_attr( $this->license_status ); ?>" type="submit" aria-hidden="true" hidden>Export Postman Collection</button>
				<a class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4 mo-caw-element-to-toggle mo-caw-light-mode <?php echo esc_attr( $this->license_status ); ?>" type="button" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-api&action=test&api-name=' . $this->api_name . '&method=' . $this->method . '&namespace=' . $this->namespace . '&test-mode=true', 'MO_CAW_API_Creation_Test_Nonce' ) ); ?>">Test</a>
				<a class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4 <?php echo esc_attr( $this->license_status ); ?>" type="button" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-api&action=edit&api-name=' . $this->api_name . '&method=' . $this->method . '&namespace=' . $this->namespace, 'MO_CAW_API_Creation_Edit_Nonce' ) ); ?>">Edit API</a>
			</div>
		</div>
		<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16">
			<div class="input-group mb-3">
				<span class="input-group-text mo-caw-<?php echo esc_attr( $this->method ); ?>-method border-0 fs-6 px-3"><?php echo esc_attr( strtoupper( $this->method ) ); ?></span>
				<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode border-end-0 border-start-0 bg-white fs-6" aria-label="" placeholder="https://<your_domain>/wp-json/<namespace>/<api-name>?<params>" value="<?php echo esc_attr( $route ); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Any parameters enclosed within <> should be replaced with actual values" aria-readonly="true" readonly> <!-- Using esc_attr and not esc_url as it does not allow using < or > in URL. -->
				<span class="input-group-text bg-white mo-caw-cursor-pointer fs-6 mo-caw-copy-icon" data-bs-toggle="tooltip" data-bs-placement="right" title="Copy API Endpoint"><i class="far fa-copy fa-lg"></i></span>
			</div>
			<?php if ( ! empty( $this->value_specific_filter ) && isset( $this->value_specific_filter['filter_details'] ) ) : ?>
				<div class="mt-4">
					<h6 class="mb-3 mo-caw-element-to-toggle mo-caw-light-mode">Request Format</h6>
					<table class="table text-center fs-6 border border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
						<thead>
							<tr class="mo-caw-bg-blue-light mo-caw-rounded-top border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
								<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Column Name</th>
								<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Description</th>
								<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Condition Applied</th>
								<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle mo-caw-form-content d-none" request-methods="get">Parameter place in API</th>
								<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle mo-caw-form-content d-none" request-methods="get">Operator if any</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $this->value_specific_filter['filter_details'] as $index => $filter_detail ) : ?>
							<tr>
								<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_attr( $filter_detail['column'] ); ?> </td>
								<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">Enter data of respective column in mentioned parameter</td>
								<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_attr( $filter_detail['condition'] ); ?></td>
								<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle mo-caw-form-content d-none" request-methods="get"><?php echo esc_attr( $filter_detail['parameter'] ); ?></td>
								<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle mo-caw-form-content d-none" request-methods="get"><?php echo isset( $this->value_specific_filter['filter_relation'] ) && ! empty( $this->value_specific_filter['filter_relation'][ $index ] ) ? esc_attr( strtoupper( $this->value_specific_filter['filter_relation'][ $index ] ) ) : 'None'; ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $this->common_filters ) ) : ?>
				<div class="mt-4">
					<h6 class="mb-3 mo-caw-element-to-toggle mo-caw-light-mode">Filters</h6>
					<table class="table text-center fs-6 border border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
						<thead>
							<tr class="mo-caw-bg-blue-light mo-caw-rounded-top border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
								<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Filters Applied</th>
								<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Column Name</th>
								<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Order</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">Order By</td>
								<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_attr( $this->common_filters['orderby']['column'] ); ?></td>
								<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_attr( ucfirst( $this->common_filters['orderby']['option'] ) ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
			<?php
			if ( Constants::HTTP_POST === $this->method || Constants::HTTP_PUT === $this->method ) :
				$column_name_json_keys = $this->request_columns;
				$default_body_json     = array_fill_keys( $column_name_json_keys, 'value' );
				$body_json_data        = wp_json_encode( $default_body_json, JSON_PRETTY_PRINT );
				?>
			<h6 class="mb-3 mo-caw-element-to-toggle mo-caw-light-mode">Required parameters for Custom APIs</h6>
			<div id="mo-caw-external-api-request-format-block">
				<ul class="nav nav-tabs nav-pills border-0 flex-column flex-sm-row text-center rounded mb-2">
					<li class="nav-item mo-caw-bg-blue-light flex-sm-fill col-6">
						<a class="nav-link active" data-bs-toggle="tab" href="#mo-caw-external-api-request-format-x-www">X-WWW-FORM-URLENCODED</a>
					</li>
					<li class="nav-item mo-caw-bg-blue-light flex-sm-fill col-6">
						<a class="nav-link" data-bs-toggle="tab" href="#mo-caw-external-api-request-format-json">JSON</a>
					</li>
				</ul>
			</div>
			<div class="tab-content pb-3">
				<div class="tab-pane active" id="mo-caw-external-api-request-format-x-www">
					<div>
						<table class="table text-center fs-6 border border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
							<thead>
								<tr class="mo-caw-bg-blue-light mo-caw-rounded-top border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
									<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Header Name</th>
									<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Header Value</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Required if using POST/PUT method with x-www-form-urlencoded format">Content-Type</td>
									<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_html( Constants::X_WWW_HEADER_NAME ); ?></td>
								</tr>
							</tbody>
						</table>
						<div class="overflow-auto mh-50 mo-caw-table-wrapper" >
							<table class="table text-center fs-6 border border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
								<thead class="sticky-top">
									<tr class="mo-caw-bg-blue-light mo-caw-rounded-top border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
										<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Body Parameter Name</th>
										<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Body Parameter Value</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $this->request_columns as $key => $value ) : ?>
									<tr>
											<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Required if using x-www-form-urlencoded format"><?php echo ( esc_html( $value ) ); ?> </td>
											<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">Column Value</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="tab-pane fade" id="mo-caw-external-api-request-format-json">
					<div>
						<table class="table text-center fs-6 border border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
							<thead>
								<tr class="mo-caw-bg-blue-light mo-caw-rounded-top border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
									<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Header Name</th>
									<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Header Value</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Required if using POST/PUT method with application/json format">Content-Type</td>
									<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_html( Constants::JSON_HEADER_NAME ); ?></td>
								</tr>
							</tbody>
						</table>
						<table class="table text-center fs-6 border border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
							<thead>
								<tr class="mo-caw-bg-blue-light mo-caw-rounded-top border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
									<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Body</th>
								</tr>
							</thead>
						</table>
						<div>
							<textarea class="form-control mo-caw-form-control py-1" id="mo-caw-external-api-json-body" name="mo-caw-external-api-json-body" placeholder="Add JSON body" rows="10"><?php echo esc_attr( $body_json_data ); ?></textarea>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>
			<div class="mt-4">
				<h6 class="mb-3 mo-caw-element-to-toggle mo-caw-light-mode">Request parameters for Pagination <em class="fw-normal">(Optional)</em></h6>
				<table class="table text-center fs-6 border border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
					<thead>
						<tr class="mo-caw-bg-blue-light mo-caw-rounded-top border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
							<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Parameter Name</th>
							<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3 align-middle">Description</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Required if using pagination">size</td>
							<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">Defines page size</td>
						</tr>
						<tr>
							<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Optional in pagination">page</td>
							<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">Page number needed in the response <em>(starts from 1)</em></td>
						</tr>
						<tr>
							<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Optional in pagination">offset</td>
							<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">Defines from which count number to start returning data <em>(starts from 1)</em></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<div class="d-flex justify-content-between align-items-end my-4 d-none"> <!--TODO : Pending Integration-->
			<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">Code Snippets</h6>
			<div class="dropdown">
				<button class="btn mo-caw-btn-outline-blue-medium dropdown-toggle mo-caw-rounded-16 mo-caw-dropdown-toggle mo-caw-element-to-toggle mo-caw-light-mode" type="button" id="mo-caw-custom-api-test-result-dropdown" data-bs-toggle="dropdown" aria-expanded="false" <?php echo esc_attr( $this->license_status ); ?>>
					Select Language
				</button>
				<ul class="dropdown-menu mo-caw-dropdown-menu" aria-labelledby="mo-caw-custom-api-test-result-dropdown">
					<li><a class="dropdown-item" href="#">Node.js</a></li>
					<li><a class="dropdown-item" href="#">PHP</a></li>
					<li><a class="dropdown-item" href="#">Python</a></li>
				</ul>
			</div>
		</div>
		<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16 d-none">
			<pre>
				const axios = require('axios');

				const url = 'http://localhost/wp_1/wp-json/mo/v1/bghfx/{args}/{attempts}';

				axios.get(url)
				.then(response => {
					console.log(response.data);
				})
				.catch(error => {
					console.error('Request failed:', error);
				});
			</pre>
		</div>
		<?php
	}

	/**
	 * Execute custom APIs export.
	 *
	 * @return void
	 */
	private function display_api_creation_export() {
	}
}
