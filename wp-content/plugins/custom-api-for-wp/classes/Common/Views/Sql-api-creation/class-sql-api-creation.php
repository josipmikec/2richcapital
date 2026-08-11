<?php
/**
 * This file handles display content for to SQL_API_Creation.
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
 * This class deals with rendering common views for Custom SQL API.
 */
class SQL_API_Creation {

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
	 * SQL queries to run on API call.
	 *
	 * @var array
	 */
	private $queries = array();
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
	 * Complete configuration of the API.
	 *
	 * @var string
	 */
	private $sql_endpoint_config = array();
	/**
	 * Disable UI components.
	 *
	 * @var string
	 */
	protected $license_status = '';
	/**
	 * Disable UI components.
	 *
	 * @var string
	 */
	private $plan_status = 'disabled';

	/**
	 * Default class constructor
	 *
	 * @param string $action Current tab action.
	 */
	public function __construct( $action ) {
		if ( isset( $_GET['_wpnonce'] ) && check_admin_referer( 'MO_CAW_SQL_API_Creation_' . ucfirst( $action ) . '_Nonce', '_wpnonce' ) ) {
			$session_form_data = isset( $_SESSION['MO_CAW_SQL_API_Creation_Form_Data'] ) ? wp_unslash( $_SESSION['MO_CAW_SQL_API_Creation_Form_Data'] ) : array(); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization is done in sanitize_nested_array() function.
			Utils::sanitize_nested_array( $session_form_data );
			$this->sql_endpoint_config = ! empty( $session_form_data ) ? $session_form_data : $this->sql_endpoint_config;

			if ( Constants::EDIT === $action || Constants::VIEW === $action || Constants::TEST === $action || Constants::DELETE === $action ) {
				$this->api_name  = isset( $_GET['api-name'] ) ? sanitize_text_field( wp_unslash( $_GET['api-name'] ) ) : $this->api_name;
				$this->method    = isset( $_GET['method'] ) ? sanitize_text_field( wp_unslash( $_GET['method'] ) ) : $this->method;
				$this->namespace = isset( $_GET['namespace'] ) ? sanitize_text_field( wp_unslash( $_GET['namespace'] ) ) : $this->namespace;

				$row_filter = array(
					'connection_name' => $this->api_name,
					'type'            => Constants::SQL_ENDPOINT,
					'method'          => $this->method,
					'namespace'       => $this->namespace,
				);

				$this->sql_endpoint_config = empty( $this->sql_endpoint_config ) ? DB_Utils::get_configuration( $row_filter )[0] : $this->sql_endpoint_config;
			} elseif ( Constants::ADD === $action && Constants::DISABLED !== $this->license_status ) {
				$this->namespace = $this->sql_endpoint_config['namespace'] ?? $this->namespace;
				$this->method    = $this->sql_endpoint_config['method'] ?? $this->method;
				$this->api_name  = $this->sql_endpoint_config['connection_name'] ?? $this->api_name;
			}

			$this->response      = $this->sql_endpoint_config['configuration']['response'] ?? array();
			$this->blocked_roles = $this->sql_endpoint_config['configuration']['blocked_roles'] ?? array();
			$this->queries       = $this->sql_endpoint_config['configuration']['sql_queries'] ?? array();
			// The else condition is not required here as we don't have a pre-saved config for add flow and default values are already assigned to the variables at time of declaration.
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
	public function display_sql_api_creation_ui( $tab, $action ) {
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
		$row_filter['type'] = Constants::SQL_ENDPOINT;
		$sql_endpoints      = DB_Utils::get_configuration( $row_filter );
		$sql_endpoints      = Utils::organize_endpoints_by_namespace( $sql_endpoints );
		?>
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">Configured SQL APIs</h6>
			<div class="d-grid gap-2 d-md-block">
				<button class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4 mo-caw-element-to-toggle mo-caw-light-mode <?php echo esc_attr( $this->license_status ); ?>" method="button" data-bs-toggle="modal" data-bs-target="#" aria-hidden="true" hidden>Export Postman Collection</button>
				<button class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4 <?php echo esc_attr( $this->license_status ); ?> <?php echo ! empty( $sql_endpoints ) ? 'mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-eai-plan mo-caw-add-standard-tooltip mo-caw-add-bac-tooltip mo-caw-add-eai-tooltip' : ''; ?>" method="button" type="button" onclick="window.location.href = '<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-sql-api&action=add', 'MO_CAW_SQL_API_Creation_Add_Nonce' ) ); ?>'">Create SQL API</button>
			</div>
			<div class="modal fade mo-caw-export-modal" id="mo-caw-custom-sql-api-export-modal" tabindex="-1" aria-labelledby="mo-caw-custom-sql-api-export-modal-label" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered justify-content-center">
					<form method="POST">
					<?php wp_nonce_field( 'MO_CAW_SQL_API_Creation_Export', 'MO_CAW_SQL_API_Creation_Nonce' ); ?>
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="mo-caw-custom-sql-api-export-modal-label">Export Data</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<h6 class="mo-caw-element-to-toggle mo-caw-light-mode">Select namespaces to export</h6>
								<div class="form-check d-flex align-items-center justify-content-start p-2">
									<input class="form-check-input m-0 bg-white me-2 mo-caw-select-all-checkbox" type="checkbox" value="" id="mo-caw-custom-sql-api-export-select-all" data-target="mo-caw-custom-sql-api-export">
									<label class="form-check-label" for="mo-caw-custom-sql-api-export-select-all">Select All</label>
								</div>
								<?php foreach ( $sql_endpoints as $namespace => $details ) : ?>
								<div class="form-check d-flex align-items-center justify-content-start p-2">
									<input class="form-check-input m-0 bg-white me-2 mo-caw-custom-sql-api-export" type="checkbox" value="<?php echo esc_attr( $namespace ); ?>" id="mo-caw-custom-sql-api-export-<?php echo esc_attr( $namespace ); ?>" name="mo-caw-custom-sql-api-export[]">
									<label class="form-check-label" for="mo-caw-custom-sql-api-export-<?php echo esc_attr( $namespace ); ?>"><?php echo esc_attr( $namespace ); ?></label>
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
		<?php if ( ! empty( $sql_endpoints ) ) : ?>
			<div class="accordion" id="mo-caw-custom-sql-api-accordion">
				<div class="accordion accordion-flush" id="mo-caw-custom-sql-api-accordion-flush">
				<?php $index = 0; ?>
				<?php foreach ( $sql_endpoints as $namespace => $details ) : ?>
					<div class="accordion-item mo-caw-accordion-item mb-2 border-0 mo-caw-rounded-8 mo-caw-element-to-toggle mo-caw-light-mode">
						<h2 class="accordion-header" id="mo-caw-custom-sql-api-config-accordion-<?php echo esc_attr( $index ); ?>">
							<button class="accordion-button mo-caw-bg-blue-light fw-normal shadow-none mo-caw-rounded-8 <?php echo 0 === $index ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#mo-caw-create-api-accordion-collapse-<?php echo esc_attr( $index ); ?>" aria-expanded="false" aria-controls="flush-collapseOne"><?php echo esc_attr( $namespace ); ?></button>
						</h2>
						<div id="mo-caw-create-api-accordion-collapse-<?php echo esc_attr( $index ); ?>" class="accordion-collapse collapse <?php echo 0 === $index ? 'show' : ''; ?>" aria-labelledby="mo-caw-custom-sql-api-config-accordion-<?php echo esc_attr( $index ); ?>" data-bs-parent="#mo-caw-custom-sql-api-accordion-flush">
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
												<input class="form-check-input mo-caw-toggle-switch" type="checkbox" onchange="moCawEnableDisableApi(this, '<?php echo esc_attr( wp_create_nonce( 'mo_caw_custom_sql_api_enable_disable_api' ) ); ?>', '<?php echo esc_attr( $namespace_endpoint['connection_name'] ); ?>', '<?php echo esc_attr( $namespace_endpoint['method'] ); ?>', '<?php echo esc_attr( $namespace ); ?>', '<?php echo esc_attr( Constants::SQL_ENDPOINT ); ?>')" 
													<?php
													echo ( ! isset( $namespace_endpoint['is_enabled'] ) || $namespace_endpoint['is_enabled'] ) ? 'checked ' : '';
													echo esc_attr( $this->license_status );
													?>
												>
											</div>
										</td>
										<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle">
											<div class="dropdown">
												<button class="btn btn-secondary dropdown-toggle mo-caw-dropdown-toggle rounded-pill mo-caw-bg-grey-light border-0 mo-caw-text-grey-medium" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">Pick an option</button>
												<ul class="dropdown-menu mo-caw-dropdown-menu" aria-labelledby="dropdownMenuButton1">
													<li><a class="dropdown-item d-flex align-items-center justify-content-between" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-sql-api&action=view&api-name=' . $namespace_endpoint['connection_name'] . '&method=' . $namespace_endpoint['method'] . '&namespace=' . $namespace, 'MO_CAW_SQL_API_Creation_View_Nonce' ) ); ?>"><span>View</span><i class="far fa-eye mo-caw-text-black"></i></a></li>
													<li><a class="dropdown-item d-flex align-items-center justify-content-between <?php echo esc_attr( $this->license_status ); ?>" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-sql-api&action=edit&api-name=' . $namespace_endpoint['connection_name'] . '&method=' . $namespace_endpoint['method'] . '&namespace=' . $namespace, 'MO_CAW_SQL_API_Creation_Edit_Nonce' ) ); ?>"><span>Edit</span><i class="fas fa-pencil mo-caw-text-black"></i></a></li>
													<li><a class="dropdown-item d-flex align-items-center justify-content-between <?php echo esc_attr( $this->license_status ); ?>" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-sql-api&action=test&api-name=' . $namespace_endpoint['connection_name'] . '&method=' . $namespace_endpoint['method'] . '&namespace=' . $namespace . '&test-mode=true', 'MO_CAW_SQL_API_Creation_Test_Nonce' ) ); ?>"><span>Test</span><i class="fas fa-check mo-caw-text-black"></i></a></li>
													<li><a class="dropdown-item d-flex align-items-center justify-content-between <?php echo esc_attr( $this->license_status ); ?>" onclick="if(confirm('Are you sure you want to delete this API?')){document.getElementById('mo-caw-api-creation-delete-form-<?php echo esc_attr( $namespace_endpoint['connection_name'] ) . '-' . esc_attr( $namespace_endpoint['method'] ) . '-' . esc_attr( $namespace ); ?>').submit(); return false;}"><span>Delete</span><i class="far fa-trash-can mo-caw-text-black"></i></a></li>
												</ul>
											</div>
											<form method="POST" id="mo-caw-api-creation-delete-form-<?php echo esc_attr( $namespace_endpoint['connection_name'] ) . '-' . esc_attr( $namespace_endpoint['method'] ) . '-' . esc_attr( $namespace ); ?>">
												<?php wp_nonce_field( 'MO_CAW_SQL_API_Creation_Delete', 'MO_CAW_SQL_API_Creation_Nonce' ); ?>
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
				<h6 class="mt-5 text-secondary">Oops! Seems like you have not created any Custom SQL APIs.</h6>
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
		global $wp_roles;

		// Get all WordPress Roles.
		$wp_all_roles = $wp_roles->roles;
		$role_names   = array();
		$role_slugs   = array_keys( $wp_all_roles );
		foreach ( $wp_all_roles as $index => $role ) {
			array_push( $role_names, $role['name'] );
		}

		// Get all namespaces.
		$table_configuration = array(
			'type' => Constants::SQL_ENDPOINT,
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
		<form method="POST" id="mo-caw-custom-sql-api-form" class="mo-caw-element-to-toggle mo-caw-light-mode">
			<?php wp_nonce_field( 'MO_CAW_SQL_API_Creation', 'MO_CAW_SQL_API_Creation_Nonce' ); ?>
			<input type="hidden" id="mo-caw-custom-sql-api-test-mode" name="mo-caw-custom-sql-api-test-mode" value="false">
			<div class="d-flex justify-content-between align-items-end mb-4">
				<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode"><?php echo Constants::ADD === $action ? 'Create Custom API' : 'Edit API - ' . esc_attr( $this->api_name ); ?></h6>
				<div class="d-grid gap-2 d-md-block">
					<button class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4 mo-caw-element-to-toggle mo-caw-light-mode <?php echo esc_attr( $this->license_status ); ?>" type="button" 
					<?php
					if ( Constants::DISABLED !== $this->license_status ) {
						echo esc_attr( 'onclick=moCawEnableTestMode()' );
					}
					?>
					>Test</button>
					<button class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4 <?php echo esc_attr( $this->license_status ); ?>" type="submit" id="mo-caw-custom-sql-api-form-submit">Save</button>
				</div>
			</div>
			<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16">
				<div class="mb-3">
					<label for="mo-caw-custom-sql-api-namespace" class="form-label mo-caw-form-label">Custom Namespace</label>
					<div class="d-flex justify-content-between align-items-center">
						<input type="text" class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-eai-plan mo-caw-crown-eai-plan form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-custom-sql-api-namespace" name="mo-caw-custom-sql-api-namespace" value="<?php echo esc_attr( $this->namespace ); ?>" placeholder="Custom Namespace (eg. mo/v1)" list="mo-caw-custom-sql-api-namespace-list" pattern="^(?=.{1,15}$)[A-Za-z]+/v[0-9]+$" title="Namespace must be of max length 15 and contain at least one '/' in between, [A-Z, a-z] before '/', 'v' (denoting version) and numbers only after '/'" aria-required="true" required <?php echo Constants::EDIT === $action || Constants::TEST === $action ? 'disabled' : ''; ?>>
						<?php if ( Constants::EDIT === $action || Constants::TEST === $action ) : ?>
							<input type="hidden" name="mo-caw-custom-sql-api-namespace" value="<?php echo esc_attr( $this->namespace ); ?>">
						<?php endif; ?>
					</div>
					<datalist id="mo-caw-custom-sql-api-namespace-list">
						<?php foreach ( $namespaces as $namespace ) : ?>
							<option value="<?php echo esc_attr( $namespace ); ?>">
						<?php endforeach; ?>
					</datalist>
				</div>
				<div class="row">
					<div class="mb-3 col">
						<label for="mo-caw-custom-sql-api-name" class="form-label mo-caw-form-label">API Name</label>
						<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-custom-sql-api-name" name="mo-caw-custom-sql-api-name" value="<?php echo esc_attr( $this->api_name ); ?>" placeholder="API Name" pattern="^(?=.{1,25}$)[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$" title="Should be of maximum length 25 and only '-' are accepted in between along with [A-Z, a-z and 0-9]" aria-required="true" required <?php echo Constants::EDIT === $action || Constants::TEST === $action ? 'disabled' : ''; ?>>
						<?php if ( Constants::EDIT === $action || Constants::TEST === $action ) : ?>
							<input type="hidden" name="mo-caw-custom-sql-api-name" value="<?php echo esc_attr( $this->api_name ); ?>">
						<?php endif; ?>
					</div>
					<div class="mb-3 col">
						<label for="mo-caw-custom-sql-api-method" class="form-label mo-caw-form-label">Method <i class="fas fa-info rounded-circle border py-1 px-2" data-bs-toggle="tooltip" data-bs-placement="bottom" title="HTTP request method"></i></label>
						<select class="form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode mo-caw-method-selector" id="mo-caw-custom-sql-api-method" name="mo-caw-custom-sql-api-method" aria-label="#mo-caw-custom-sql-api-method" aria-required="true" required <?php echo Constants::EDIT === $action || Constants::TEST === $action ? 'disabled' : ''; ?>>
							<option value="">Select Method</option>
							<option value="get" <?php echo Constants::HTTP_GET === $this->method ? 'selected' : ''; ?>>GET</option>
							<option value="post" <?php echo Constants::HTTP_POST === $this->method ? 'selected' : ''; ?>>POST</option>
							<option value="put" <?php echo Constants::HTTP_PUT === $this->method ? 'selected' : ''; ?>>PUT</option>
							<option value="delete" <?php echo Constants::HTTP_DELETE === $this->method ? 'selected' : ''; ?>>DELETE</option>
						</select>
						<?php if ( Constants::EDIT === $action || Constants::TEST === $action ) : ?>
							<input type="hidden" name="mo-caw-custom-sql-api-method" value="<?php echo esc_attr( $this->method ); ?>">
						<?php endif; ?>
					</div>
				</div>
				<div>
					<div class="d-flex justify-content-between align-items-center mb-2 mo-caw-form-content d-none" request-methods="get post put delete">
						<label class="form-label mo-caw-form-label fw-bolder">SQL Queries</em> <i class="fas fa-info rounded-circle border py-1 px-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Write a SQL query. Any custom parameters to be replaced dynamically should be in the following format: E.g. &quot;{{parameter_name}}&quot;."></i></label>
						<span>
							<button class="mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-eai-plan mo-caw-add-standard-tooltip mo-caw-add-bac-tooltip mo-caw-add-eai-tooltip border-0 bg-white p-0" type="button" onclick="moCawAddField('sql', 'mo-caw-custom-sql-api-query-duplicate-div-0', 'mo-caw-custom-sql-api-query-duplicate-div-', this.nextElementSibling)" ><i class="fa-solid fa-plus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
							<button class="mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-eai-plan mo-caw-add-standard-tooltip mo-caw-add-bac-tooltip mo-caw-add-eai-tooltip border-0 bg-white p-0" type="button" onclick="moCawRemoveField('mo-caw-custom-sql-api-query-duplicate-div-', this)" ><i class="fa-solid fa-minus mo-caw-text-grey-medium border border-3 rounded p-1"></i></button>
						</span>
					</div>
					<div class="mo-caw-form-content d-none mb-3" request-methods="get post put delete">
						<div id="mo-caw-sortable-list" class="list-group col">
						<?php if ( ! empty( $this->queries ) ) : ?>
							<?php foreach ( $this->queries as $sequence => $query ) : ?>
									<div class="row" id="mo-caw-custom-sql-api-query-duplicate-div-<?php echo esc_attr( $sequence ); ?>">
										<div class="list-group-item border-0" draggable="true">
											<div class="input-group">
												<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 border-end-0" id="mo-caw-custom-sql-api-query" name="mo-caw-custom-sql-api-query[]" value="<?php echo esc_attr( $query ); ?>" placeholder="Enter your SQL query" aria-required="true" required>
												<span class="input-group-text bg-white border-start-0 mo-caw-cursor-pointer">
												<?php if ( Constants::STANDARD_PLAN_NAME !== Constants::PLAN_NAME ) : ?>
														<i class="fas fa-bars fa-xl mo-caw-handle"></i>
												<?php endif; ?>
												</span>
											</div>
										</div>
									</div>
							<?php endforeach; ?>
						<?php else : ?>
							<div class="row" id="mo-caw-custom-sql-api-query-duplicate-div-0">
								<div class="list-group-item border-0" draggable="true">
									<div class="input-group">
										<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 border-end-0" id="mo-caw-custom-sql-api-query" name="mo-caw-custom-sql-api-query[]" placeholder="Enter your SQL query" aria-required="true" required>
										<span class="input-group-text bg-white border-start-0 mo-caw-cursor-pointer">
											<?php if ( Constants::STANDARD_PLAN_NAME !== Constants::PLAN_NAME ) : ?>
												<i class="fas fa-bars fa-xl mo-caw-handle"></i>
											<?php endif; ?>
										</span>
									</div>
								</div>
							</div>
						<?php endif; ?>
						</div>
					</div>
				</div>
				<div class="accordion d-none" id="mo-caw-adv-setting-accordion">
					<div class="accordion accordion-flush" id="mo-caw-custom-sql-api-accordion-flush">
						<div class="accordion-item mb-2 border-0 mo-caw-rounded-8 mo-caw-accordion-item mo-caw-element-to-toggle mo-caw-light-mode">
							<h2 class="accordion-header" id="mo-caw-custom-sql-api-adv-settings-accordion-heading">
								<button class="accordion-button collapsed mo-caw-bg-blue-light shadow-none mo-caw-rounded-8" type="button" data-bs-toggle="collapse" data-bs-target="#mo-caw-custom-sql-api-adv-settings-accordion-collapse" aria-expanded="false" aria-controls="mo-caw-custom-sql-api-adv-settings-accordion-collapse">
									Advance Settings
								</button>
							</h2>
							<div id="mo-caw-custom-sql-api-adv-settings-accordion-collapse" class="accordion-collapse collapse" aria-labelledby="mo-caw-custom-sql-api-adv-settings-accordion-heading" data-bs-parent="#mo-caw-custom-sql-api-accordion-flush">
								<div class="accordion-body">
									<div class="row">
										<div class="mb-3 col mo-caw-form-content d-none" request-methods="get post put delete">
											<label for="mo-caw-custom-sql-api-response-format" class="form-label mo-caw-form-label">Response Type <i class="fas fa-info rounded-circle border py-1 px-2" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Send response in default or your custom format"></i></label>
											<div class="d-flex justify-content-between align-items-center">
												<select class="mo-caw-crown-standard-plan mo-caw-crown-bac-plan mo-caw-crown-eai-plan form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-response-type" name="mo-caw-custom-sql-api-response-type" aria-label="#mo-caw-custom-sql-api-method" >
													<option value="default" <?php echo ! isset( $this->response['response_type'] ) || Constants::DEFAULT === $this->response['response_type'] ? 'selected' : ''; ?>>Default</option>
													<option value="custom" class="mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-eai-plan" <?php echo isset( $this->response['response_type'] ) && Constants::CUSTOM === $this->response['response_type'] ? 'selected' : ''; ?>>Custom Response</option>
												</select>
											</div>
										</div>
										<div class="mb-3 col mo-caw-form-content d-none" request-methods="get post put delete">
											<label for="mo-caw-custom-sql-api-response-format" class="form-label mo-caw-form-label">Response Format</label>
											<div class="d-flex justify-content-between align-items-center">
												<select class="form-select mo-caw-form-select mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode" id="mo-caw-custom-sql-api-response-format" name="mo-caw-custom-sql-api-response-format" aria-label="#mo-caw-custom-sql-api-method" >
													<option value="json" <?php echo ! isset( $this->response['response_content_type'] ) || Constants::JSON === $this->response['response_content_type'] ? 'selected' : ''; ?>>JSON</option>
													<option value="xml" <?php echo isset( $this->response['response_content_type'] ) && Constants::XML === $this->response['response_content_type'] ? 'selected' : ''; ?> aria-hidden="true" hidden>XML</option>
												</select>
											</div>
										</div>
										<div id="mo-caw-response-format-block" class="<?php echo ! isset( $this->response['response_type'] ) || Constants::DEFAULT === $this->response['response_type'] ? 'd-none' : ''; ?>">
											<ul class="nav nav-tabs nav-pills border-0 flex-column flex-sm-row text-center rounded">
												<li class="nav-item mo-caw-bg-blue-light flex-sm-fill">
													<a class="nav-link active" data-bs-toggle="tab" href="#mo-caw-custom-sql-api-response-type-success">Success</a>
												</li>
												<li class="nav-item mx-1 mo-caw-bg-blue-light flex-sm-fill">
													<a class="nav-link" data-bs-toggle="tab" href="#mo-caw-custom-sql-api-response-type-error">Error</a>
												</li>
												<li class="nav-item mo-caw-bg-blue-light flex-sm-fill">
													<a class="nav-link" data-bs-toggle="tab" href="#mo-caw-custom-sql-api-response-type-authentication">Authentication</a>
												</li>
											</ul>
											<div class="tab-content pb-3">
												<div class="tab-pane active" id="mo-caw-custom-sql-api-response-type-success">
													<label for="mo-caw-custom-sql-api-response-type-success-format"></label>
													<textarea class="form-control" placeholder="{&#13;&#10;  &quot;message&quot;: &quot;success&quot;,&#13;&#10;  &quot;data&quot;: &quot;$response_data&quot;&#13;&#10;}&#13;&#10;" id="mo-caw-custom-sql-api-response-type-success-format" name="mo-caw-custom-sql-api-response-type-success-format" rows="10"><?php echo ( isset( $this->response['response_content']['success'] ) && ! empty( $this->response['response_content']['success'] ) ) ? esc_attr( $this->response['response_content']['success'] ) : ''; ?></textarea>
												</div>
												<div class="tab-pane fade" id="mo-caw-custom-sql-api-response-type-error">
													<label for="mo-caw-custom-sql-api-response-type-error-format"></label>
													<textarea class="form-control" placeholder="{&#13;&#10;  &quot;message&quot;: &quot;error&quot;,&#13;&#10;  &quot;data&quot;: &quot;$response_data&quot;&#13;&#10;}&#13;&#10;" id="mo-caw-custom-sql-api-response-type-error-format" name="mo-caw-custom-sql-api-response-type-error-format" rows="10"><?php echo ( isset( $this->response['response_content']['error'] ) && ! empty( $this->response['response_content']['error'] ) ) ? esc_attr( $this->response['response_content']['error'] ) : ''; ?></textarea>
												</div>
												<div class="tab-pane fade" id="mo-caw-custom-sql-api-response-type-authentication">
													<label for="mo-caw-custom-sql-api-response-type-authentication-format"></label>
													<textarea class="form-control" placeholder="{&#13;&#10;  &quot;message&quot;: &quot;authentication_error&quot;,&#13;&#10;  &quot;data&quot;: &quot;$response_data&quot;&#13;&#10;}&#13;&#10;" id="mo-caw-custom-sql-api-response-type-authentication-format" name="mo-caw-custom-sql-api-response-type-authentication-format" rows="10"><?php echo ( isset( $this->response['response_content']['authentication'] ) && ! empty( $this->response['response_content']['authentication'] ) ) ? esc_attr( $this->response['response_content']['authentication'] ) : ''; ?></textarea>
												</div>
											</div>
										</div>
									</div>
										<div class="mb-3">
											<label for="mo-caw-custom-sql-api-allowed-roles" class="form-label mo-caw-form-label">Restrict role-based access</label>
											<div class="dropdown mo-caw-disable-standard-plan mo-caw-disable-bac-plan mo-caw-disable-eai-plan" >
												<button class="mo-caw-disable-standard-plan mo-caw-crown-standard-plan mo-caw-disable-bac-plan mo-caw-crown-bac-plan mo-caw-disable-eai-plan mo-caw-crown-eai-plan btn dropdown-toggle mo-caw-dropdown-toggle w-100 bg-white mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode mo-caw-text-grey-medium d-flex justify-content-between align-items-center" type="button" id="mo-caw-custom-sql-api-allowed-roles-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
													<?php echo ! empty( $this->blocked_roles ) ? 'Selected (' . count( $this->blocked_roles ) . ')' : 'Select Roles'; ?>
												</button>
												<div class="dropdown-menu mo-caw-dropdown-menu w-100" aria-labelledby="mo-caw-custom-sql-api-allowed-roles-dropdown">
													<div class="form-check d-flex align-items-center p-2">
														<input class="form-check-input m-0 bg-white mo-caw-select-all-checkbox me-2" type="checkbox" value="" id="mo-caw-custom-sql-api-allowed-roles-select-all" data-target="mo-caw-custom-sql-api-allowed-roles" <?php echo count( $this->blocked_roles ) === count( $role_slugs ) ? 'checked' : ''; ?>>
														<label class="form-check-label" for="mo-caw-custom-sql-api-allowed-roles-select-all">Select All</label>
													</div>
													<?php foreach ( $role_slugs as $index => $role_slug ) : ?>
													<div class="form-check d-flex align-items-center p-2">
														<input class="form-check-input m-0 bg-white mo-caw-custom-sql-api-allowed-roles me-2" type="checkbox" value="<?php echo esc_attr( $role_slug ); ?>" id="mo-caw-custom-sql-api-allowed-roles-<?php echo esc_attr( $index ); ?>" name="mo-caw-custom-sql-api-allowed-roles[]" <?php echo in_array( $role_slug, $this->blocked_roles, true ) ? 'checked' : ''; ?>>
														<label class="form-check-label" for="mo-caw-custom-sql-api-allowed-roles-<?php echo esc_attr( $index ); ?>"><?php echo esc_attr( $role_names[ $index ] ); ?></label>
													</div>
													<?php endforeach; ?>
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
		<div class="modal fade mo-caw-export-modal" id="mo-caw-custom-sql-api-test-inputs-modal" tabindex="-1" aria-labelledby="mo-caw-custom-sql-api-test-inputs-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered justify-content-center">
				<form method="POST" id="mo-caw-custom-sql-api-test-inputs-form">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="mo-caw-custom-sql-api-test-inputs-modal-label">Values to run test</h5>
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
					<span class="ms-2 badge rounded-pill" id="mo-caw-custom-sql-api-test-result-status"></span>
				</div>
			</div>
			<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16 mo-caw-test-result" id="mo-caw-custom-sql-api-test-result">
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

		$queries = implode( '', $this->queries );
		$pattern = '/{{([A-Za-z0-9-_]+)}}/';

		preg_match_all( $pattern, $queries, $matches );
		$all_custom_params = $matches[1];

		if ( Constants::HTTP_GET === $this->method || Constants::HTTP_DELETE === $this->method ) {
			if ( count( $all_custom_params ) > 0 ) {
				$all_custom_params = array_values( array_unique( $all_custom_params ) );
				foreach ( $all_custom_params as $custom_param ) {
					$request_params[ $custom_param ] = '<' . $custom_param . '>';
				}
				$route = add_query_arg( $request_params, $route );
			}
		}

		?>
		<div class="d-flex justify-content-between align-items-end mb-4">
			<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode"><?php echo 'View  API - ' . esc_attr( $this->api_name ); ?></h6>
			<div class="d-grid gap-2 d-md-block">
				<button class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 mo-caw-bg-blue-dark px-4 <?php echo esc_attr( $this->license_status ); ?>" type="submit" aria-hidden="true" hidden>Export Postman Collection</button> 
				<a class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4 mo-caw-element-to-toggle mo-caw-light-mode <?php echo esc_attr( $this->license_status ); ?>" type="button" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-sql-api&action=test&api-name=' . $this->api_name . '&method=' . $this->method . '&namespace=' . $this->namespace . '&test-mode=true', 'MO_CAW_SQL_API_Creation_Test_Nonce' ) ); ?>">Test</a>
				<a class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4 <?php echo esc_attr( $this->license_status ); ?>" type="button" href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=custom_api_wp_settings&tab=custom-sql-api&action=edit&api-name=' . $this->api_name . '&method=' . $this->method . '&namespace=' . $this->namespace, 'MO_CAW_SQL_API_Creation_Edit_Nonce' ) ); ?>">Edit API</a>
			</div>
		</div>
		<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16">
			<div class="input-group mb-3">
				<span class="input-group-text mo-caw-<?php echo esc_attr( $this->method ); ?>-method border-0 fs-6 px-3"><?php echo esc_attr( strtoupper( $this->method ) ); ?></span>
				<input type="text" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode border-end-0 border-start-0 bg-white fs-6" aria-label="" placeholder="https://<your_domain>/wp-json/<namespace>/<api-name>?<params>" value="<?php echo esc_attr( $route ); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Any parameters enclosed within <> should be replaced with actual values" aria-readonly="true" readonly> <!-- Using esc_attr and not esc_url as it does not allow using < or > in URL. -->
				<span class="input-group-text bg-white mo-caw-cursor-pointer fs-6 mo-caw-copy-icon" data-bs-toggle="tooltip" data-bs-placement="right" title="Copy API Endpoint"><i class="far fa-copy fa-lg"></i></span>
			</div>
				<?php
				if ( ( Constants::HTTP_POST === $this->method || Constants::HTTP_PUT === $this->method ) && count( $all_custom_params ) ) :
					$default_body_json = array_fill_keys( $all_custom_params, 'value' );
					$body_json_data    = wp_json_encode( $default_body_json, JSON_PRETTY_PRINT );
					?>
				<h6 class="mb-3 mo-caw-element-to-toggle mo-caw-light-mode">Required parameters for Custom APIs</h6>
				<div id="mo-caw-external-api-request-format-block">
					<ul class="nav nav-tabs nav-pills border-0 flex-column flex-sm-row text-center rounded mb-2">
						<li class="nav-item mo-caw-bg-blue-light flex-sm-fill col-6 px-0">
							<a class="nav-link active" data-bs-toggle="tab" href="#mo-caw-external-api-request-format-x-www">X-WWW-FORM-URLENCODED</a>
						</li>
						<li class="nav-item mo-caw-bg-blue-light flex-sm-fill col-6 px-0">
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
										<?php foreach ( $all_custom_params as $key => $value ) : ?>
										<tr>
											<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Required if using x-www-form-urlencoded format"><?php echo esc_html( $value ); ?></td>
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
		<div class="d-flex justify-content-between align-items-end my-4 d-none"> <!--TODO : pending Integration -->
			<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">Code Snippets</h6>
			<div class="dropdown">
				<button class="btn mo-caw-btn-outline-blue-medium dropdown-toggle mo-caw-rounded-16 mo-caw-dropdown-toggle mo-caw-element-to-toggle mo-caw-light-mode" type="button" id="mo-caw-custom-sql-api-test-result-dropdown" data-bs-toggle="dropdown" aria-expanded="false" <?php echo esc_attr( $this->license_status ); ?>>
					Select Language
				</button>
				<ul class="dropdown-menu mo-caw-dropdown-menu" aria-labelledby="mo-caw-custom-sql-api-test-result-dropdown">
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
