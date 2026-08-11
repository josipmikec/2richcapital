<?php
/**
 * This file deals with handling the plugin licensing plans.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Views;

/**
 * This class deals with handling the plugin licensing plans.
 */
class License {
	/**
	 * Displays plugin's license plans.
	 *
	 * @return void
	 */
	public static function display_license_plans() {
		$basic_api_creation_features    = array(
			'Create Unlimited Basic Custom APIs'     => 'Allows you to create unlimited basic custom APIs.',
			'Create APIs with Multiple HTTP Methods' => 'Create GET, POST, PUT, and DELETE APIs.',
			'Limit the Columns in the API Response'  => 'Choose which columns should be included in the API response.',
			'Customizable API Response Format'       => 'Define the format of the API response.',
			'Add Filters and Conditional Logic'      => 'Filter the API response based on conditions such as like, not like, equal, not equal, and, or.',
			'Role-Based Restriction on Custom APIs'  => 'Restrict access to users based on roles like administrator, editor, etc.',
			'Test the Custom API Configuration'      => 'Test and verify the API configuration.',
		);
		$advanced_api_creation_features = array(
			'Create Unlimited Basic Custom APIs'          => 'Allows you to create unlimited basic custom APIs.',
			'Create Unlimited Custom SQL Based Advanced APIs' => 'Allows you to create unlimited advanced custom APIs using SQL Queries.',
			'Create APIs with Multiple HTTP Methods'      => 'Create GET, POST, PUT, and DELETE APIs.',
			'Filter API Response with Dynamic Parameters' => 'Use dynamic parameters during API execution to filter responses.',
			'Customizable API Response Format'            => 'Define the format of the API response.',
			'Add Filters and Conditional Logic'           => 'Filter the API response based on different filters and conditions such as like, not like, equal, not qual, and, or. etc.',
			'Role-Based Restriction on Custom APIs'       => 'Restrict access to users based on roles like administrator, editor, etc.',
			'Test the Custom API Configuration'           => 'Test and verify the API configuration.',
		);
		$connect_external_apis_features = array(
			'Connect to Unlimited External APIs'     => 'Allows you to connect unlimited external APIs.',
			'Connect APIs with various HTTP methods' => 'Connect GET, POST, PUT, PATCH, and DELETE APIs.',
			'Supports JSON, XML, SOAP Based APIs'    => 'Connect both SOAP and REST APIs with JSON and XML format.',
			'OAuth2, Bearer, API Key Based Authentication for External APIs' => 'Supports OAuth2, Bearer, API Key Based Authentication for External APIs.',
			'Data Display using Shortcode'           => 'Display data on WordPress site using the shortcode in table/custom format.',
			'Data Display using Template Tag'        => 'Display data on WordPress site using the template tag',
			'Developer Hooks'                        => 'Extend the Plugin with Developer Hooks',
			'Customizable API Response Format'       => 'Define the format of the API response.',
			'Test the External API Connection'       => 'Test and verify the API connection.',
		);
		$all_inclusive_features         = array(
			'Create Unlimited Basic and Advanced Custom APIs' => 'Allows you to create to unlimited basic and advanced custom APIs.',
			'Connect to Unlimited External APIs'    => 'Allows you to connect unlimited external APIs.',
			'Create/Connect APIs with Multiple HTTP Methods' => 'Create/Connect GET, POST, PUT, PATCH and DELETE APIs.',
			'Limit the Columns in the API Response' => 'Choose which columns should be included in the API response.',
			'Customizable API Response Format'      => 'Define the format of the API response.',
			'Add Filters and Conditional Logic'     => 'Filter the API response based on conditions such as like, not like, equal, not equal, and, or.',
			'Role-Based Restriction on Custom APIs' => 'Restrict access to users based on roles like administrator, editor, etc.',
			'Supports JSON, XML, SOAP Based APIs'   => 'Connect both SOAP and REST APIs with JSON and XML format.',
			'OAuth2, Bearer, API Key Based Authentication for External APIs' => 'Supports OAuth2, Bearer, API Key Based Authentication for External APIs.',
			'Data Display using Shortcode'          => 'Display data on WordPress site using the shortcode in table/custom format.',
			'Data Display using Template Tag'       => 'Display data on WordPress site using the template tag',
			'Developer Hooks'                       => 'Extend the Plugin with Developer Hooks',
			'Test the Custom API Configuration'     => 'Test and verify the API configuration.',
		);
		?>
		<div class="d-flex justify-content-between align-items-end mb-4">
			<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">Pricing Plans</h6>
		</div>
		<div id="mo-caw-pricing-plans" class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16">
			<div class="row gy-3">
				<div class="col-4">
					<div class="card h-100 mo-caw-rounded-16 border m-0 p-0">
						<div class="card-body m-0 p-0 mo-caw-rounded-8">
							<div class="d-flex justify-content-between align-items-end">
								<div class="px-4 py-5">
									<h1 class="card-title mo-caw-text-black mo-caw-plan-price fw-bold">$199</h1>
									<h4 class="text-start mo-caw-text-grey-dark fw-normal mo-caw-plan-name">Basic API Creation</h4>
								</div>
								<span><i class="mo-caw-plan-icon fas fa-house-chimney mo-caw-text-grey-light position-relative"></i></span>
							</div>
							<div class="d-grid gap-2 m-3">
								<button type="button" class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-8 mo-caw-element-to-toggle mo-caw-light-mode" data-bs-toggle="modal" data-bs-target="#mo-caw-basic-api-creation-features-modal">Check Features</button>
								<a class="btn btn-primary mo-caw-rounded-8 mo-caw-bg-blue-dark" href="https://portal.miniorange.com/initializePayment?requestOrigin=wp_rest_custom_api_basic_api_creation_plan" target="_blank">Upgrade Plan</a>
							</div>
						</div>
					</div>
				</div>
				<div class="col-4">
					<div class="card h-100 mo-caw-rounded-8 border m-0 p-0">
						<div class="card-body m-0 p-0 mo-caw-rounded-8">
							<div class="d-flex justify-content-between align-items-end">
								<div class="px-4 py-5">
									<h1 class="card-title mo-caw-text-black fw-bold mo-caw-plan-price">$249</h1>
									<h4 class="text-start mo-caw-text-grey-dark fw-normal mo-caw-plan-name">Advanced API Creation</h4>
								</div>
								<span><i class="mo-caw-plan-icon fas fa-hotel mo-caw-text-grey-light position-relative"></i></span>
							</div>
							<div class="d-grid gap-2 m-3">
								<button type="button" class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-8 mo-caw-element-to-toggle mo-caw-light-mode" data-bs-toggle="modal" data-bs-target="#mo-caw-advanced-api-creation-features-modal">Check Features</button>
								<a class="btn btn-primary mo-caw-rounded-8 mo-caw-bg-blue-dark" href="https://portal.miniorange.com/initializePayment?requestOrigin=wp_rest_custom_api_advanced_api_creation_plan" target="_blank">Upgrade Plan</a>
							</div>
						</div>
					</div>
				</div>
				<div class="col-4">
					<div class="card h-100 mo-caw-rounded-8 border m-0 p-0">
						<div class="card-body m-0 p-0 mo-caw-rounded-8">
							<div class="d-flex justify-content-between align-items-end">
								<div class="px-4 py-5">
									<h1 class="card-title mo-caw-text-black fw-bold mo-caw-plan-price">$299</h1>
									<h4 class="text-start mo-caw-text-grey-dark fw-normal mo-caw-plan-name">Connect External API</h4>
								</div>
								<span><i class="mo-caw-plan-icon fas fa-city mo-caw-text-grey-light position-relative"></i></span>
							</div>
							<div class="d-grid gap-2 m-3">
								<button type="button" class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-8 mo-caw-element-to-toggle mo-caw-light-mode" data-bs-toggle="modal" data-bs-target="#mo-caw-connect-external-api-features-modal">Check Features</button>
								<a class="btn btn-primary mo-caw-rounded-8 mo-caw-bg-blue-dark" href="https://portal.miniorange.com/initializePayment?requestOrigin=wp_rest_custom_api_external_api_integration_plan" target="_blank">Upgrade Plan</a>
							</div>
						</div>
					</div>
				</div>
				<div class="col-4">
					<div class="card h-100 mo-caw-rounded-8 border m-0 p-0">
						<div class="card-body m-0 p-0 mo-caw-rounded-8">
							<div class="d-flex justify-content-between align-items-end">
								<div class="px-4 py-5">
									<h1 class="card-title mo-caw-text-black fw-bold mo-caw-plan-price">$399</h1>
									<h4 class="text-start mo-caw-text-grey-dark fw-normal mo-caw-plan-name">All-Inclusive</h4>
								</div>
								<span><i class="mo-caw-plan-icon fas fa-globe mo-caw-text-grey-light position-relative"></i></span>
							</div>
							<div class="d-grid gap-2 m-3">
								<button type="button" class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-8 mo-caw-element-to-toggle mo-caw-light-mode" data-bs-toggle="modal" data-bs-target="#mo-caw-all-inclusive-features-modal">Check Features</button>
								<a class="btn btn-primary mo-caw-rounded-8 mo-caw-bg-blue-dark" href="https://portal.miniorange.com/initializePayment?requestOrigin=wp_rest_custom_api_all_inclusive_plan" target="_blank">Upgrade Plan</a>
							</div>
						</div>
					</div>
				</div>
				<div class="col-8">
					<div class="card h-100 mo-caw-rounded-8 border m-0 p-0" id="mo-caw-get-in-touch-card">
						<div class="card-body mo-caw-rounded-8 d-flex align-items-center text-   gap-4">
							<span>Get in touch with us at <a href="mailto:apisupport@xecurify.com">apisupport@xecurify.com</a> if you have a custom requirement and we can provide a quote for your requirement ensuring 100% satisfaction.</span>
							<img src="<?php echo esc_attr( MO_CUSTOM_API_URL ) . 'classes/Common/Resources/Images/mail-sent.jpeg'; ?>" alt="Reach out to miniOrange" height="200px">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="modal fade" id="mo-caw-basic-api-creation-features-modal" tabindex="-1" aria-labelledby="mo-caw-basic-api-creation-features-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
				<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="mo-caw-basic-api-creation-features-modal-label">Basic API Creation Features</h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<ul class="list-group list-group-flush">
						<?php foreach ( $basic_api_creation_features as $feature => $detail ) : ?>
							<li class="list-group-item d-flex justify-content-between align-items-start">
								<div class="ms-2 me-auto">
								<div class="fw-bold"><?php echo esc_attr( $feature ); ?></div>
								<?php echo esc_attr( $detail ); ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				</div>
			</div>
		</div>
		<div class="modal fade" id="mo-caw-advanced-api-creation-features-modal" tabindex="-1" aria-labelledby="mo-caw-advanced-api-creation-features-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
				<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="mo-caw-advanced-api-creation-features-modal-label">Advanced API Creation Features</h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<ul class="list-group list-group-flush">
						<?php foreach ( $advanced_api_creation_features as $feature => $detail ) : ?>
							<li class="list-group-item d-flex justify-content-between align-items-start">
								<div class="ms-2 me-auto">
								<div class="fw-bold"><?php echo esc_attr( $feature ); ?></div>
								<?php echo esc_attr( $detail ); ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				</div>
			</div>
		</div>
		<div class="modal fade" id="mo-caw-connect-external-api-features-modal" tabindex="-1" aria-labelledby="mo-caw-connect-external-api-features-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
				<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="mo-caw-connect-external-api-features-modal-label">Connect External API Features</h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<ul class="list-group list-group-flush">
						<?php foreach ( $connect_external_apis_features as $feature => $detail ) : ?>
							<li class="list-group-item d-flex justify-content-between align-items-start">
								<div class="ms-2 me-auto">
								<div class="fw-bold"><?php echo esc_attr( $feature ); ?></div>
								<?php echo esc_attr( $detail ); ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				</div>
			</div>
		</div>
		<div class="modal fade" id="mo-caw-all-inclusive-features-modal" tabindex="-1" aria-labelledby="mo-caw-all-inclusive-features-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
				<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="mo-caw-all-inclusive-features-modal-label">All-Inclusive Features</h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<ul class="list-group list-group-flush">
						<?php foreach ( $all_inclusive_features as $feature => $detail ) : ?>
							<li class="list-group-item d-flex justify-content-between align-items-start">
								<div class="ms-2 me-auto">
								<div class="fw-bold"><?php echo esc_attr( $feature ); ?></div>
								<?php echo esc_attr( $detail ); ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				</div>
			</div>
		</div>
		<?php
	}
}
