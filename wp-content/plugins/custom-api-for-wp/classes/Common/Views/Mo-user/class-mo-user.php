<?php
/**
 * This file handles display content for to MO_User.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Views;

use DateTime;
use MO_CAW\Common\Constants;
use MO_CAW\Common\DB_Utils;
use MO_CAW\Common\Functionality\MO_User as Common_MO_User_Functionality;

/**
 * This class deals with rendering common views for user.
 */
class MO_User {

	/**
	 * Display the content for user account as per the action.
	 *
	 * @param  string $tab    Active tab name.
	 * @param  string $action Tab current action.
	 * @return void
	 */
	public static function display_user_account_ui( $tab, $action ) {

		switch ( $action ) {
			case 'register':
				\MO_CAW\Standard\Views\MO_User::miniorange_registration_form();
				break;
			case 'login':
				self::miniorange_login_form();
				break;
			case 're-login':
				self::miniorange_login_form();
				break;
			default:
				if ( Common_MO_User_Functionality::is_user_logged_in() ) {
					self::display_logged_in_user_details();
				} else {
					\MO_CAW\Standard\Views\MO_User::miniorange_registration_form();
				}
				break;
		}
	}

	/**
	 * Display plugin support popup.
	 *
	 * @return void
	 */
	public static function display_support_popup() {
		?>
		<div id="mo-caw-support-popup" class="position-fixed d-flex align-items-center">
			<div class="align-items-center me-3 d-none">
				<span id="mo-caw-support-popup-rectangle" class="bg-white shadow mo-caw-rounded-16 p-3">
					<p class="mb-0">Need help with anything? Drop us an email here!</p>
				</span>
				<span id="mo-caw-support-popup-triangle" class="bg-white mo-caw-ml-n1"></span>
			</div>
			<i id="mo-caw-email-icon" class="fas fa-envelope text-white mo-caw-bg-blue-dark p-3 rounded-circle mo-caw-cursor-pointer" data-bs-toggle="modal" data-bs-target="#mo-caw-support-popup-modal"></i>
		</div>
		<div class="modal fade" id="mo-caw-support-popup-modal" tabindex="-1" aria-labelledby="mo-caw-support-popup-modal-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<form method="POST">
					<?php wp_nonce_field( 'MO_CAW_MO_User_Contact_Us', 'MO_CAW_MO_User_Nonce' ); ?>
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="mo-caw-support-popup-modal-label">Contact miniOrange Support</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<form method="POST">
								<div class="mb-3">
									<label for="mo-caw-modal-support-email" class="col-form-label">Email</label>
									<input type="email" class="form-control" id="mo-caw-modal-support-email" name="mo-caw-modal-support-email" placeholder="person@example.com" aria-required="true" required>
								</div>
								<div class="mb-3">
									<label for="mo-caw-modal-support-phone" class="col-form-label">Phone</label>
									<input type="tel" class="form-control" id="mo-caw-modal-support-phone" name="mo-caw-modal-support-phone" placeholder="1234567890" pattern="[\+]\d{11,14}|[\+]\d{1,4}[\s]\d{9,10}">
								</div>
								<div class="mb-3">
									<label for="mo-caw-modal-support-query" class="col-form-label">Query</label>
									<textarea class="form-control" id="mo-caw-modal-support-query" name="mo-caw-modal-support-query" placeholder="Let us know your query..." rows="5" cols="100" aria-required="true" required></textarea>
								</div>
							</form>
						</div>
						<div class="modal-footer d-md-flex justify-content-md-center">
							<button type="submit" class="btn btn-primary mo-caw-bg-blue-medium mo-caw-rounded-16">Send Query</button>
						</div>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * MiniOrange login form.
	 *
	 * @return void
	 */
	public static function miniorange_login_form() {
		?>
			<form method="POST" id="mo-caw-user-login-form" class="mo-caw-element-to-toggle mo-caw-light-mode">
				<?php wp_nonce_field( 'MO_CAW_MO_User_Login', 'MO_CAW_MO_User_Nonce' ); ?>
				<div class="d-flex justify-content-between align-items-end mb-4">
					<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">Login with miniOrange</h6>
				</div>
				<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16">
					<div class="row">
						<div class="col-5 d-grid align-items-center justify-content-center pe-0">
							<img src="<?php echo esc_attr( MO_CUSTOM_API_URL ) . 'classes/Common/Resources/Images/login.jpeg'; ?>" alt="Login with miniOrange" height="250px">
						</div>
						<div class="col ps-0">
							<div>
								<div class="mb-3 col">
									<label for="mo-caw-user-email" class="form-label mo-caw-form-label">Email</label>
									<input type="email" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-user-email" name="mo-caw-user-email" placeholder="person@example.com" aria-required="true" required>
								</div>
								<div class="mb-3 col">
									<label for="mo-caw-user-password" class="form-label mo-caw-form-label">Password</label>
									<input type="password" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-user-password" name="mo-caw-user-password" placeholder="Enter your password" aria-required="true" required>
								</div>
								<div>
									<input type="checkbox" id="mo_caw_terms_privacy_checkbox" name="mo_caw_terms_privacy_checkbox" required>
											<label class=" mo-caw-text-grey-medium" for="mo_caw_terms_privacy_checkbox" style="font-size: 0.8rem;">
												I have read and agree to the <a href="https://plugins.miniorange.com/end-user-license-agreement" target="_blank">end user agreement</a> and <a href="https://plugins.miniorange.com/wp-content/uploads/2023/08/Plugins-Privacy-Policy.pdf" target="_blank">plugin privacy policy</a>
											</label>
								</div>
								<div>
									<a class="fs-7 mo-caw-text-grey-medium" href="https://portal.miniorange.com/forgotpassword" target="_blank">Forgot password?</a>
								</div>
							</div>
							<div class="d-grid gap-2 d-md-block text-center">
								<?php if ( Constants::STANDARD_PLAN_NAME === Constants::PLAN_NAME ) : ?>
									<a class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4" type="button" href="admin.php?page=custom_api_wp_settings&tab=user-account&action=register">Register</a>
								<?php endif; ?>
								<button class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4" type="submit">Log In</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		<?php
	}

	/**
	 * Function to display logged in user details.
	 *
	 * @return void
	 */
	public static function display_logged_in_user_details() {
		$user_details = DB_Utils::get_option( 'mo_caw_user_display_details', array() );
		$user_email   = $user_details['user_email'] ?? 'example@gmail.com';
		$user_id      = $user_details['user_id'] ?? '12345';
		$user_expiry  = isset( $user_details['license_expiry'] ) ? new DateTime( $user_details['license_expiry'] ) : new DateTime( '12-12-1212' );
		?>
		<form method="POST" id="mo-caw-user-change-account" class="mo-caw-element-to-toggle mo-caw-light-mode">
			<?php wp_nonce_field( 'MO_CAW_MO_User_Change_Account', 'MO_CAW_MO_User_Nonce' ); ?>
			<div class="d-flex justify-content-between align-items-end mb-4">
				<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">miniOrange Account Details</h6>
			</div>
			<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16">
				<table class="table text-center fs-6 border border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
					<thead class="mo-caw-bg-blue-light mo-caw-rounded-top border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode">
						<tr>
							<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">miniOrange Account Email</th>
							<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">Customer ID</th>
							<?php if ( Constants::STANDARD_PLAN_NAME !== Constants::PLAN_NAME ) : ?>
								<th scope="col" class="border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode border-bottom-0 col-md-3 p-3">License Expiry</th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_attr( $user_email ); ?></td>
							<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_attr( $user_id ); ?></td>
							<?php if ( Constants::STANDARD_PLAN_NAME !== Constants::PLAN_NAME ) : ?>
								<td class="col-md-3 border-1 mo-caw-dark-border mo-caw-element-to-toggle mo-caw-light-mode p-3 align-middle"><?php echo esc_attr( $user_expiry->format( 'j F Y' ) ); ?></td>
							<?php endif; ?>
						</tr>
					</tbody>
				</table>
				<div class="d-grid gap-2 d-md-block text-center">
					<button class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4" type="submit">Log In with different account</button>
					<?php if ( Constants::STANDARD_PLAN_NAME !== Constants::PLAN_NAME ) : ?>
						<button class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4" type="button" onclick="document.getElementById('mo-caw-user-refresh-expiry').submit()">Refresh License Expiry</button>
					<?php endif; ?>
				</div>
			</div>
		</form>
		<form method="POST" id="mo-caw-user-refresh-expiry" class="mo-caw-element-to-toggle mo-caw-light-mode">
			<?php wp_nonce_field( 'MO_CAW_MO_User_Refresh_Expiry', 'MO_CAW_MO_User_Nonce' ); ?>
		</form>
		<?php
	}
}
