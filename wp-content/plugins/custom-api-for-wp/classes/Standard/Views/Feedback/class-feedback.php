<?php
/**
 * This file deals with Common displayable parts for standard version of the plugin.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Standard\Views;

use MO_CAW\Common\DB_Utils;

/**
 * This class deals with rendering feedback_form for plugin.
 */
class Feedback {

	/**
	 * Function to display feedback form.
	 *
	 * @return void
	 */
	public static function display_feedback_form() {
		if ( ! empty( $_SERVER['PHP_SELF'] ) && 'plugins.php' !== basename( sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) ) ) {
			return;
		}

		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_script( 'utils' );

		$user_details = DB_Utils::get_option( 'mo_caw_user_display_details', array() );
		$user_email   = $user_details['user_email'] ?? DB_Utils::get_option( 'admin_email', '' );
		?>
		<style>
			.mo-caw-feedback {
				position: fixed;
				display: none;
				justify-content: center;
				align-items: center;
				text-align: center;
				width: -webkit-fill-available;
				height: 100%;
				z-index: 1;
				top: 0;
				left: -90px;
				margin-left: 13%;
				background-color: rgba(0, 0, 0, 0.4);
			}
			.mo-caw-modal {
				min-width: 700px;
				width: fit-content;
			}
			.mo-caw-modal-content {
				background-color: #fff;
				padding: 20px;
				width: 80%;
				border-radius: 36px;
			}
			.mo-caw-feedback-close {
				color: #aaa;
				float: right;
				font-size: 28px;
				font-weight: bold;
			}
			.mo-caw-feedback-close:hover,
			.mo-caw-feedback-close:focus {
				color: black;
				text-decoration: none;
				cursor: pointer;
			}
			.mo-caw-reasons-container{
				display: flex;
				flex-direction: column;
				gap: 12px;
				padding: 0rem 3rem;
				margin-bottom: 20px;
			}
			.mo-caw-buttons{
				margin-top: 20px;
			}
			.mo-caw-buttons button{
				cursor: pointer;
				line-height: 1.5;
				text-decoration: none;
				padding: 0.5rem 1rem;
				border-radius: 16px;
			}
			#mo-caw-feedback-skip{

				border: 1px solid #4378FE;
				color: #4378FE;
				background-color: #fff;

			}
			#mo-caw-feedback-submit{
				border: 1px solid #4378FE;
				color: #fff;
				background-color: #4378FE;
			}
			.mo-caw-rating-emojis-container {
				margin: 20px auto;
			}
			#mo-caw-feedback-deactivation-reason{
				max-width: -webkit-fill-available;
			}
			#mo-caw-feedback-email{
				display: flex;
				justify-content: space-between;
				align-items: center;
				background-color: #c9ddef2e;
			}
			#mo-caw-feedback-email input{
				width: -webkit-fill-available;
				border: none;
				background-color: transparent;
			}
			#mo-caw-feedback-email input:read-only{
				color: #9FA0A6;
				cursor: not-allowed;
			}
			#mo-caw-feedback-email span{
				cursor: pointer;
				font-size: x-large;
				margin-right: 8px;
				transform: rotate(90deg);
			}
			#mo-caw-feedback-should-miniorange-reach-out{
				padding: 0rem 2.5rem;
			}
			#mo-caw-emoji-container {
				display: flex;
				justify-content: center;
				gap: 10px;
				margin-top: 10px;
			}
			.mo-caw-feedback-emoji {
				padding: 1em 1em 0em 1em;
				cursor: pointer;
			}
			.mo-caw-feedback-emoji.mo-caw-feedback-emoji-active{
				background-color: #d8d8d84d;
				border-radius: 16px;
			}
		</style>
			<div class="mo-caw-feedback" id="mo-caw-feedback-modal">
				<div class="mo-caw-modal">
					<div class="mo-caw-modal-content">
						<span class="mo-caw-feedback-close">&times;</span>
						<h2>Leaving so soon? We'd love to hear your feedback!</h2>
						<p>Help us improve by sharing your thoughts on why you're deactivating the plugin.</p>
						<form id="mo-caw-feedback-form" method="POST">
							<?php wp_nonce_field( 'MO_CAW_MO_User_Feedback', 'MO_CAW_MO_User_Nonce' ); ?>
							<div class="mo-caw-rating-emojis-container">
								<div id="mo-caw-emoji-container">
									<input type="hidden" name="mo-caw-feedback-rating" id="mo-caw-feedback-rating" value="3">
									<span id="mo-caw-feedback-rating-1" class="mo-caw-feedback-emoji" onclick="moCawChangeRating(this.id)">
										<img src="<?php echo esc_attr( MO_CUSTOM_API_URL ) . 'classes/Common/Resources/Images/angry.jpeg'; ?>" alt="Angry" height="60px">
										<p>Angry</p>
									</span>
									<span id="mo-caw-feedback-rating-2" class="mo-caw-feedback-emoji" onclick="moCawChangeRating(this.id)">
										<img src="<?php echo esc_attr( MO_CUSTOM_API_URL ) . 'classes/Common/Resources/Images/sad.jpeg'; ?>" alt="Sad" height="60px">
										<p>Sad</p>
									</span>
									<span id="mo-caw-feedback-rating-3" class="mo-caw-feedback-emoji" onclick="moCawChangeRating(this.id)">
										<img src="<?php echo esc_attr( MO_CUSTOM_API_URL ) . 'classes/Common/Resources/Images/neutral.jpeg'; ?>" alt="Neutral" height="60px">
										<p>Okay</p>
									</span>
									<span id="mo-caw-feedback-rating-4" class="mo-caw-feedback-emoji" onclick="moCawChangeRating(this.id)">
										<img src="<?php echo esc_attr( MO_CUSTOM_API_URL ) . 'classes/Common/Resources/Images/wow.jpeg'; ?>" alt="Wow" height="60px">
										<p>Wow</p>
									</span>
									<span id="mo-caw-feedback-rating-5" class="mo-caw-feedback-emoji" onclick="moCawChangeRating(this.id)">
										<img src="<?php echo esc_attr( MO_CUSTOM_API_URL ) . 'classes/Common/Resources/Images/mind-blowing.jpeg'; ?>" alt="Mind-blowing" height="60px">
										<p>Mind-blowing</p>
									</span>
								</div>
							</div>
							<div class="mo-caw-reasons-container">
								<select name="mo-caw-feedback-deactivation-reason" id="mo-caw-feedback-deactivation-reason" required aria-required="true">
									<option value="" disabled selected>Select Reason</option>
									<option value="Lack of Features">Lack of Features</option>
									<option value="Features Not Working As Expected">Features Not Working As Expected</option>
									<option value="Bugs/Improvements">Bugs/Improvements</option>
									<option value="Not User-Friendly">Not User-Friendly</option>
									<option value="Too-Expensive To Upgrade">Too-Expensive To Upgrade</option>
									<option value="Switched To Alternative">Switched To Alternative</option>
									<option value="Other">Other</option>
								</select>
								<textarea id="mo-caw-reason" name="mo-caw-feedback-reason" placeholder="Tell us what happened?" rows="6"></textarea>
								<div id="mo-caw-feedback-email">
									<input type="email" name="mo-caw-feedback-email" id="mo-caw-feedback-customer-email" placeholder="" value="<?php echo esc_attr( $user_email ); ?>" readonly aria-readonly="true">
									<span onclick="moCawFeedbackEmail()">✎</span>
								</div>
							</div>
							<div id="mo-caw-feedback-should-miniorange-reach-out">
								<input type="checkbox" name="mo-caw-feedback-should-miniorange-reach-out" checked>miniOrange representative will reach out to you at the email-address entered above.
							</div>
							<div class="mo-caw-buttons">
								<button type="button" id="mo-caw-feedback-skip" onclick="moCawSkipFeedback()">Skip</button>
								<button type="submit" id="mo-caw-feedback-submit">Submit</button>
							</div>
						</form>
					</div>
				</div>
			</div>
			<script>
				function moCawChangeRating(ratingSpanId) {
					var allSpans = document.querySelectorAll('.mo-caw-feedback-emoji');
					allSpans.forEach(function(span) {
						span.classList.remove('mo-caw-feedback-emoji-active');
					});

					var ratingSpan = document.getElementById(ratingSpanId);
					ratingSpan.classList.add('mo-caw-feedback-emoji-active');

					var rating = ratingSpanId.split("-").pop();
					document.getElementById("mo-caw-feedback-rating").value = rating;
				}

				function moCawFeedbackEmail(){
					const customerEmail = document.getElementById("mo-caw-feedback-customer-email");
					customerEmail.removeAttribute('aria-readonly');
					customerEmail.removeAttribute('readonly');
				}
				function moCawSkipFeedback(){
					moCawHideModal();
					jQuery("#mo-caw-feedback-form").submit();
				}

				var modal = document.getElementById('mo-caw-feedback-modal');
				var closeButton = document.querySelector('.mo-caw-feedback-close');

				function moCawShowModal() {
					modal.style.display = 'flex';
				}

				function moCawHideModal() {
					modal.style.display = 'none';
				}

				jQuery('a[aria-label="Deactivate Custom API for WP"]').click(function() {
					moCawShowModal();
					return false;
				});

				closeButton.onclick = function() {
					moCawHideModal();
				}

				window.onclick = function(event) {
					if (event.target == modal) {
						moCawHideModal();
					}
				}
			</script>
		<?php
	}
}
