<?php
/**
 * This file handles display content for to MO_User.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Standard\Views;

/**
 * This class deals with rendering common views for user.
 */
class MO_User {

	/**
	 * MiniOrange registration form.
	 *
	 * @return void
	 */
	public static function miniorange_registration_form() {
		?>
			<form method="POST" id="mo-caw-user-registration-form" class="mo-caw-element-to-toggle mo-caw-light-mode">
				<?php wp_nonce_field( 'MO_CAW_MO_User_Registration', 'MO_CAW_MO_User_Nonce' ); ?>
				<div class="d-flex justify-content-between align-items-end mb-4">
					<h6 class="fw-bolder mo-caw-element-to-toggle mo-caw-light-mode">Register with miniOrange</h6>
				</div>
				<div class="bg-white mo-caw-shadow p-3 mo-caw-rounded-16">
					<div class="row">
						<div class="col-5 d-grid align-items-center justify-content-center pe-0">
							<img src="<?php echo esc_attr( MO_CUSTOM_API_URL ) . 'classes/Common/Resources/Images/login.jpeg'; ?>" alt="Register with miniOrange" height="250px">
						</div>
						<div class="col ps-0">
							<div>
								<div class="mb-3 col">
									<label for="mo-caw-user-email" class="form-label mo-caw-form-label">Email</label>
									<input type="email" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-user-email" name="mo-caw-user-email" placeholder="person@example.com" aria-required="true" required>
								</div>
								<div class="mb-3 col">
									<label for="mo-caw-user-password" class="form-label mo-caw-form-label">Password</label>
									<input type="password" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-user-password" name="mo-caw-user-password" placeholder="Enter your password" onchange="moCawValidatePassword()" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{12,}$" title="Password should be atleast 12 characters long and should contain at least one from A-Z, a-z and 0-9 and a special character." aria-required="true" required>
								</div>
								<div class="mb-3 col">
									<label for="mo-caw-user-confirm-password" class="form-label mo-caw-form-label">Confirm Password</label>
									<input type="password" class="form-control mo-caw-form-control mo-caw-element-to-toggle mo-caw-light-mode py-1 px-2" id="mo-caw-user-confirm-password" name="mo-caw-user-confirm-password" placeholder="Confirm your password" onkeyup="moCawValidatePassword()" aria-required="true" required>
								</div>
							</div>
							<div>
								<input type="checkbox" id="mo_caw_terms_privacy_checkbox" name="mo_caw_terms_privacy_checkbox" required>
									<label class=" mo-caw-text-grey-medium" for="mo_caw_terms_privacy_checkbox" style="font-size: 0.81rem;">
										I have read and agree to the <a href="https://plugins.miniorange.com/end-user-license-agreement" target="_blank">end user agreement</a> and <a href="https://plugins.miniorange.com/wp-content/uploads/2023/08/Plugins-Privacy-Policy.pdf" target="_blank">plugin privacy policy</a>
									</label>
							</div>
							<div class="d-grid gap-2 d-md-block text-center" style="margin-top: 1rem;">
								<a class="btn mo-caw-btn-outline-blue-medium mo-caw-rounded-16 px-4" type="button" href="admin.php?page=custom_api_wp_settings&tab=user-account&action=login">Already have an account!</a>
								<button class="btn btn-primary mo-caw-rounded-16 mo-caw-bg-blue-dark px-4" type="submit">Register</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		<?php
	}
}
