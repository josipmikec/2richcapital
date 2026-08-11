<?php
/**
 * This file handles form submissions related to MO_User.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Settings;

use MO_CAW\Common\Constants;
use MO_CAW\Common\DB_Utils;
use MO_CAW\Common\Functionality\MO_User as MO_User_Common_Functionality;
use MO_CAW\Common\Utils;

/**
 * This class deals with saving common user settings in database.
 */
class MO_User {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		self::form_action_identifier();
	}

	/**
	 * Verify nonce for common MO User settings.
	 *
	 * @return void
	 */
	public static function form_action_identifier() {
		if ( Utils::mo_caw_require_capability() ){
			if ( isset( $_REQUEST['MO_CAW_MO_User_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_MO_User_Nonce'] ) ), 'MO_CAW_MO_User_Contact_Us' ) ) {
				self::handle_contact_us( $_POST );
			} elseif ( isset( $_REQUEST['MO_CAW_MO_User_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_MO_User_Nonce'] ) ), 'MO_CAW_MO_User_Registration' ) ) {
				self::handle_customer_registration( $_POST );
			} elseif ( isset( $_REQUEST['MO_CAW_MO_User_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_MO_User_Nonce'] ) ), 'MO_CAW_MO_User_Login' ) ) {
				self::handle_customer_verification( $_POST );
			} elseif ( isset( $_REQUEST['MO_CAW_MO_User_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_MO_User_Nonce'] ) ), 'MO_CAW_MO_User_Change_Account' ) ) {
				wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=user-account&action=re-login', 302 );
				exit();
			} elseif ( isset( $_REQUEST['MO_CAW_MO_User_Nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['MO_CAW_MO_User_Nonce'] ) ), 'MO_CAW_MO_User_Feedback' ) ) {
				self::handle_feedback( $_POST );
			}
		}
	}

	/**
	 * Handles contact us form submission.
	 *
	 * @param array $post The global $_POST.
	 * @return void
	 */
	protected static function handle_contact_us( $post ) {
		$email   = isset( $post['mo-caw-modal-support-email'] ) ? sanitize_email( wp_unslash( $post['mo-caw-modal-support-email'] ) ) : '';
		$phone   = isset( $post['mo-caw-modal-support-phone'] ) ? sanitize_text_field( wp_unslash( $post['mo-caw-modal-support-phone'] ) ) : '';
		$query   = isset( $post['mo-caw-modal-support-query'] ) ? sanitize_textarea_field( wp_unslash( $post['mo-caw-modal-support-query'] ) ) : '';
		$subject = 'Query: Connect to External APIs | Custom API for WP - ' . Utils::get_version_number() . ' Plugin';

		$response = json_decode( MO_User_Common_Functionality::contact_us( $email, $phone, $query, $subject ) );

		if ( Constants::SUCCESS_STATUS === $response->status ) {
			DB_Utils::update_option( 'mo_caw_message', 'Your query has been submitted successfully and our team will get back to you shortly.' );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_SUCCESS );
		} else {
			DB_Utils::update_option( 'mo_caw_message', 'Something went wrong while submitting your query. You can reach out to us directly at apisupport@xecurify.com.' );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_DANGER );
		}
	}

	/**
	 * Handles customer registration with miniOrange.
	 *
	 * @param array $post The global $_POST.
	 * @return void
	 */
	private static function handle_customer_registration( $post ) {
		$email            = isset( $post['mo-caw-user-email'] ) ? sanitize_email( wp_unslash( $post['mo-caw-user-email'] ) ) : '';
		$password         = isset( $post['mo-caw-user-password'] ) ? wp_unslash( $post['mo-caw-user-password'] ) : '';
		$confirm_password = isset( $post['mo-caw-user-confirm-password'] ) ? wp_unslash( $post['mo-caw-user-confirm-password'] ) : '';

		if ( $confirm_password === $password ) {
			$response = json_decode( MO_User_Common_Functionality::create_and_store_customer( $email, $password ) );

			if ( isset( $response->status ) && Constants::SUCCESS_STATUS === $response->status ) {
				DB_Utils::update_option( 'mo_caw_message', $response->message . 'Please login.' );
				DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_SUCCESS );

				wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=user-account&action=login', 302 );
				exit();
			} else {
				DB_Utils::update_option( 'mo_caw_message', $response->message );
				DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_DANGER );
			}
		} else {
			DB_Utils::update_option( 'mo_caw_message', 'Registration failed. The passwords do not match.' );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_DANGER );
		}
	}

	/**
	 * Handles customer login with miniOrange.
	 *
	 * @param array $post The global $_POST.
	 * @return void
	 */
	private static function handle_customer_verification( $post ) {
		$email    = isset( $post['mo-caw-user-email'] ) ? sanitize_email( wp_unslash( $post['mo-caw-user-email'] ) ) : '';
		$password = isset( $post['mo-caw-user-password'] ) ? wp_unslash( $post['mo-caw-user-password'] ) : '';

		$response = json_decode( MO_User_Common_Functionality::verify_and_store_customer( $email, $password ) );

		if ( isset( $response->status ) && Constants::SUCCESS_STATUS === $response->status ) {
			DB_Utils::update_option( 'mo_caw_message', 'Logged in successfully' );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_SUCCESS );

			wp_safe_redirect( 'admin.php?page=custom_api_wp_settings&tab=user-account', 302 );
			exit();
		} else {
			DB_Utils::update_option( 'mo_caw_message', $response );
			DB_Utils::update_option( 'mo_caw_message_status', Constants::MESSAGE_STATUS_DANGER );
		}
	}

	/**
	 * Handles feedback form submission.
	 *
	 * @param array $post The global $_POST.
	 * @return void
	 */
	private static function handle_feedback( $post ) {
		$email               = isset( $post['mo-caw-feedback-email'] ) ? sanitize_email( wp_unslash( $post['mo-caw-feedback-email'] ) ) : '';
		$deactivation_reason = isset( $post['mo-caw-feedback-deactivation-reason'] ) ? sanitize_textarea_field( wp_unslash( $post['mo-caw-feedback-deactivation-reason'] ) ) : '';
		$reason              = isset( $post['mo-caw-feedback-reason'] ) ? sanitize_textarea_field( wp_unslash( $post['mo-caw-feedback-reason'] ) ) : '';
		$rating              = isset( $post['mo-caw-feedback-rating'] ) ? sanitize_textarea_field( wp_unslash( $post['mo-caw-feedback-rating'] ) ) : '';
		$should_reach_out    = isset( $post['mo-caw-feedback-should-miniorange-reach-out'] ) ? 'Yes' : 'No';

		$subject = 'Feedback: Connect to External APIs | Custom API for WP - ' . Utils::get_version_number() . ' Plugin';

		$query = '<br><br><b>Feedback: </b>' . $deactivation_reason . ' - ' . $reason . '<br><br><b>Rating: </b>' . $rating . '<br><br><b>Should miniOrange Reach Out?: </b>' . $should_reach_out;
		MO_User_Common_Functionality::contact_us( $email, '', $query, $subject );

		deactivate_plugins( MO_CUSTOM_API_DIR . Constants::PLUGIN_FILE );
	}
}
