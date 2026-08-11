<?php
/**
 * This file deals with Common displayable parts of the plugin.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Views;

use MO_CAW\Common\Constants;
use MO_CAW\Common\DB_Utils;

/**
 * This class deals with Common displayable parts of the plugin.
 */
class Display_Common {

	/**
	 * Displays plugin navbar.
	 *
	 * @return void
	 */
	public static function display_top_navbar() {
		?>
		<nav class="navbar navbar-light mo-caw-navbar border-bottom mo-caw-element-to-toggle mo-caw-light-mode">
			<div class="container-fluid">
				<a class="navbar-brand d-flex align-items-center p-0" href="#">
					<img src="<?php echo esc_attr( MO_CUSTOM_API_URL ) . 'classes/Common/Resources/Images/miniorange.png'; ?>" alt="miniOrange" width="45" height="45" class="d-inline-block align-text-top ms-1 me-3">
					<span class="d-flex flex-column align-items-start">
						<span class="navbar-brand m-0 p-0 h1 fw-bolder">miniOrange Custom API</span>
						<span class="m-0 fs-6">Dashboard</span>
					</span>
				</a>
				<span>
					<a class="rounded-circle btn mo-caw-shadow p-2 mx-1" href="#" role="button" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Switch to dark mode"id="theme-toggle" aria-hidden="true" hidden><i class="fas fa-sun fa-xl mo-caw-text-yellow"></i></i></a>
					<a class="rounded-circle btn mo-caw-shadow p-2 mx-1" href="#" role="button" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Export Plugin Settings" aria-hidden="true" hidden><i class="fas fa-file-export fa-xl mo-caw-text-black"></i></a>
					<a class="rounded-circle btn mo-caw-shadow p-2 mx-1" href="https://wordpress.org/support/plugin/custom-api-for-wp/" role="button" data-bs-toggle="tooltip" data-bs-placement="bottom" title="FAQ"><i class="far fa-circle-question fa-xl mo-caw-text-black"></i></a>
					<a class="rounded-circle btn mo-caw-shadow p-2" href="https://wordpress.org/plugins/custom-api-for-wp/" target="__blank" role="button" data-bs-toggle="tooltip" data-bs-placement="bottom" title="WordPress Plugin Page"><i class="fa-brands fa-wordpress fa-xl mo-caw-text-black"></i></i></a>
				</span>
			</div>
		</nav>
		<?php
	}

	/**
	 * Displays plugin left side navbar.
	 *
	 * @param  string $tab Active tab name.
	 * @return void
	 */
	public static function display_side_navbar( $tab ) {
		?>
		<div id="mo-caw-sidenav" class="d-flex flex-column flex-shrink-0 border-end border-bottom position-absolute mo-caw-element-to-toggle mo-caw-light-mode">
			<div class="my-auto d-flex flex-column justify-content-center">
				<div class="position-fixed" id="mo-caw-sidenav-svg">
					<svg width="3.25rem" height="40rem" viewBox="0 0 80 818" fill="none">
						<path d="M0 21.8375V21.8375C0 61.3567 80 85.4107 80 124.93V678.82C80 718.056 0 754.815 0 794.05V794.05C0 994.095 0 -170.425 0 21.8375Z" fill="#2854C5"/>
					</svg>
					<div class="position-absolute top-50 start-50 translate-middle py-3 d-flex flex-column justify-content-evenly align-items-center">
						<div class="d-flex align-items-center my-1">
							<svg width="25" height="25" viewBox="0 0 29 29" fill="none" class="mo-caw-cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="right" title="Create Custom API" onclick="window.location.href='admin.php?page=custom_api_wp_settings&tab=custom-api';">
								<path d="M28.2899 1.7251L26.8407 0.275879C26.786 0.221192 26.7176 0.197266 26.6458 0.197266C26.5741 0.197266 26.5057 0.224609 26.451 0.275879L23.8499 2.87695C22.7192 2.11083 21.3842 1.70236 20.0184 1.70459C18.2684 1.70459 16.5184 2.37109 15.182 3.70752L11.6991 7.19043C11.6482 7.24182 11.6196 7.31122 11.6196 7.38354C11.6196 7.45587 11.6482 7.52527 11.6991 7.57666L20.9857 16.8633C21.0404 16.918 21.1087 16.9419 21.1805 16.9419C21.2489 16.9419 21.3206 16.9146 21.3753 16.8633L24.8582 13.3804C27.2132 11.022 27.4901 7.375 25.6888 4.71582L28.2899 2.11475C28.3958 2.00537 28.3958 1.83105 28.2899 1.7251ZM23.2108 11.7363L21.1805 13.7666L14.7957 7.38184L16.826 5.35156C17.6771 4.50049 18.8119 4.02881 20.0184 4.02881C21.2249 4.02881 22.3563 4.49707 23.2108 5.35156C24.0619 6.20264 24.5335 7.3374 24.5335 8.54394C24.5335 9.75049 24.0619 10.8818 23.2108 11.7363ZM16.7098 15.3252C16.6584 15.2743 16.589 15.2458 16.5167 15.2458C16.4444 15.2458 16.375 15.2743 16.3236 15.3252L14.0472 17.6016L10.9608 14.5151L13.2406 12.2354C13.3465 12.1294 13.3465 11.9551 13.2406 11.8491L11.9964 10.605C11.945 10.5541 11.8756 10.5255 11.8033 10.5255C11.731 10.5255 11.6616 10.5541 11.6102 10.605L9.33041 12.8848L7.86068 11.415C7.83517 11.3895 7.80476 11.3694 7.77129 11.3559C7.73781 11.3424 7.70195 11.3357 7.66586 11.3364C7.5975 11.3364 7.52572 11.3638 7.47104 11.415L3.99154 14.8979C1.63656 17.2563 1.35971 20.9033 3.16098 23.5625L0.559903 26.1636C0.509015 26.215 0.480469 26.2844 0.480469 26.3567C0.480469 26.429 0.509015 26.4984 0.559903 26.5498L2.00912 27.999C2.06381 28.0537 2.13217 28.0776 2.20395 28.0776C2.27572 28.0776 2.34408 28.0503 2.39877 27.999L4.99984 25.398C6.1517 26.1807 7.49154 26.5703 8.83139 26.5703C10.5814 26.5703 12.3314 25.9038 13.6678 24.5674L17.1507 21.0845C17.2567 20.9785 17.2567 20.8042 17.1507 20.6982L15.681 19.2285L17.9608 16.9487C18.0667 16.8428 18.0667 16.6685 17.9608 16.5625L16.7098 15.3252ZM12.0204 22.9268C11.602 23.3472 11.1045 23.6806 10.5566 23.9077C10.0086 24.1347 9.42109 24.2509 8.82797 24.2495C7.62143 24.2495 6.49008 23.7812 5.63559 22.9268C5.21511 22.5084 4.88172 22.0109 4.65468 21.463C4.42764 20.915 4.31145 20.3275 4.31283 19.7344C4.31283 18.5278 4.78109 17.3965 5.63559 16.542L7.66586 14.5117L14.0506 20.8965L12.0204 22.9268Z" fill="white"/>
							</svg>
							<svg width="5" height="45" viewBox="0 0 5 54" fill="none" class="position-relative <?php echo ( '' === $tab ) || ( Constants::CUSTOM_API_TAB === $tab ) ? esc_attr( 'mo-caw-active-tab' ) : ''; ?>">
								<path d="M0 6.27147C0 3.80913 5 3.46214 5 0.999802C5 -3.0002 5 57.4999 5 52.9998C5 50.5308 0 49.8664 0 47.3973V6.27147Z" fill="#2854C5"/>
							</svg>
						</div>
						<div class="d-flex align-items-center my-1">
							<svg width="25" height="25" viewBox="0 0 36 36" fill="none" class="mo-caw-cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="right" title="Create Custom SQL API" onclick="window.location.href='admin.php?page=custom_api_wp_settings&tab=custom-sql-api';">
								<path d="M18.5234 0C9.25801 0 2 4.16953 2 9.49219V26.3672C2 31.6898 9.25801 35.8594 18.5234 35.8594C27.7889 35.8594 35.0469 31.6898 35.0469 26.3672V9.49219C35.0469 4.16953 27.7889 0 18.5234 0ZM18.5234 2.10938C26.3369 2.10938 32.9375 5.48965 32.9375 9.49219C32.9375 13.4947 26.3369 16.875 18.5234 16.875C10.71 16.875 4.10938 13.4947 4.10938 9.49219C4.10938 5.48965 10.71 2.10938 18.5234 2.10938ZM32.9375 26.3672C32.9375 30.3697 26.3369 33.75 18.5234 33.75C10.71 33.75 4.10938 30.3697 4.10938 26.3672V22.6389C6.92188 25.5164 12.2604 27.4219 18.5234 27.4219C24.7865 27.4219 30.125 25.5164 32.9375 22.6389V26.3672ZM32.9375 17.9297C32.9375 21.9322 26.3369 25.3125 18.5234 25.3125C10.71 25.3125 4.10938 21.9322 4.10938 17.9297V14.2014C6.92188 17.0789 12.2604 18.9844 18.5234 18.9844C24.7865 18.9844 30.125 17.0789 32.9375 14.2014V17.9297Z" fill="white"/>
							</svg>
							<svg width="5" height="45" viewBox="0 0 5 54" fill="none" class="position-relative <?php echo ( Constants::CUSTOM_SQL_API_TAB === $tab ) ? esc_attr( 'mo-caw-active-tab' ) : ''; ?>">
								<path d="M0 6.27147C0 3.80913 5 3.46214 5 0.999802C5 -3.0002 5 57.4999 5 52.9998C5 50.5308 0 49.8664 0 47.3973V6.27147Z" fill="#2854C5"/>
							</svg>
						</div>
						<div class="d-flex align-items-center my-1">
							<svg width="25" height="25" viewBox="0 0 36 36" fill="none" class="mo-caw-cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="right" title="Connect External API" onclick="window.location.href='admin.php?page=custom_api_wp_settings&tab=connect-external-api';">
								<path d="M29.3606 24.7014C28.5881 24.7052 27.8315 24.9208 27.1731 25.3249L23.7934 21.9342C24.5798 20.8249 25.0022 19.4987 25.0022 18.1389C25.0022 16.7791 24.5798 15.4529 23.7934 14.3436L27.1731 10.953C27.8315 11.357 28.5881 11.5726 29.3606 11.5764C30.2259 11.5764 31.0718 11.3198 31.7912 10.8391C32.5107 10.3584 33.0714 9.67508 33.4026 8.87566C33.7337 8.07623 33.8203 7.19656 33.6515 6.3479C33.4827 5.49923 33.066 4.71968 32.4542 4.10783C31.8423 3.49597 31.0628 3.07929 30.2141 2.91048C29.3655 2.74167 28.4858 2.82831 27.6864 3.15945C26.8869 3.49058 26.2036 4.05133 25.7229 4.7708C25.2422 5.49026 24.9856 6.33612 24.9856 7.20142C24.9894 7.97389 25.205 8.73052 25.609 9.38892L22.2184 12.7686C21.1091 11.9822 19.7829 11.5598 18.4231 11.5598C17.0633 11.5598 15.7371 11.9822 14.6278 12.7686L11.2372 9.38892C11.6412 8.73052 11.8568 7.97389 11.8606 7.20142C11.8606 6.33612 11.604 5.49026 11.1233 4.7708C10.6425 4.05133 9.95926 3.49058 9.15984 3.15945C8.36041 2.82831 7.48074 2.74167 6.63208 2.91048C5.78341 3.07929 5.00386 3.49597 4.39201 4.10783C3.78015 4.71968 3.36347 5.49923 3.19466 6.3479C3.02585 7.19656 3.11249 8.07623 3.44362 8.87566C3.77476 9.67508 4.33551 10.3584 5.05498 10.8391C5.77444 11.3198 6.6203 11.5764 7.4856 11.5764C8.25807 11.5726 9.0147 11.357 9.6731 10.953L13.0528 14.3436C12.2664 15.4529 11.844 16.7791 11.844 18.1389C11.844 19.4987 12.2664 20.8249 13.0528 21.9342L9.6731 25.3249C9.0147 24.9208 8.25807 24.7052 7.4856 24.7014C6.6203 24.7014 5.77444 24.958 5.05498 25.4387C4.33551 25.9195 3.77476 26.6028 3.44362 27.4022C3.11249 28.2016 3.02585 29.0813 3.19466 29.9299C3.36347 30.7786 3.78015 31.5582 4.39201 32.17C5.00386 32.7819 5.78341 33.1985 6.63208 33.3674C7.48074 33.5362 8.36041 33.4495 9.15984 33.1184C9.95926 32.7873 10.6425 32.2265 11.1233 31.507C11.604 30.7876 11.8606 29.9417 11.8606 29.0764C11.8568 28.3039 11.6412 27.5473 11.2372 26.8889L14.6278 23.5092C15.7371 24.2956 17.0633 24.718 18.4231 24.718C19.7829 24.718 21.1091 24.2956 22.2184 23.5092L25.609 26.8889C25.205 27.5473 24.9894 28.3039 24.9856 29.0764C24.9856 29.9417 25.2422 30.7876 25.7229 31.507C26.2036 32.2265 26.8869 32.7873 27.6864 33.1184C28.4858 33.4495 29.3655 33.5362 30.2141 33.3674C31.0628 33.1985 31.8423 32.7819 32.4542 32.17C33.066 31.5582 33.4827 30.7786 33.6515 29.9299C33.8203 29.0813 33.7337 28.2016 33.4026 27.4022C33.0714 26.6028 32.5107 25.9195 31.7912 25.4387C31.0718 24.958 30.2259 24.7014 29.3606 24.7014ZM29.3606 5.01392C29.7932 5.01392 30.2162 5.14221 30.5759 5.38258C30.9356 5.62294 31.216 5.96458 31.3816 6.3643C31.5472 6.76401 31.5905 7.20384 31.5061 7.62818C31.4217 8.05251 31.2133 8.44229 30.9074 8.74821C30.6015 9.05414 30.2117 9.26248 29.7874 9.34689C29.363 9.43129 28.9232 9.38797 28.5235 9.2224C28.1238 9.05684 27.7821 8.77646 27.5418 8.41673C27.3014 8.057 27.1731 7.63406 27.1731 7.20142C27.1731 6.62126 27.4036 6.06486 27.8138 5.65462C28.224 5.24439 28.7804 5.01392 29.3606 5.01392ZM5.2981 7.20142C5.2981 6.76877 5.42639 6.34584 5.66676 5.98611C5.90712 5.62638 6.24876 5.346 6.64848 5.18043C7.04819 5.01486 7.48802 4.97154 7.91236 5.05595C8.33669 5.14036 8.72647 5.34869 9.03239 5.65462C9.33832 5.96055 9.54666 6.35032 9.63107 6.77466C9.71547 7.19899 9.67215 7.63882 9.50658 8.03854C9.34102 8.43825 9.06064 8.77989 8.70091 9.02026C8.34118 9.26062 7.91824 9.38892 7.4856 9.38892C6.90544 9.38892 6.34904 9.15845 5.9388 8.74821C5.52857 8.33798 5.2981 7.78158 5.2981 7.20142ZM7.4856 31.2639C7.05295 31.2639 6.63002 31.1356 6.27029 30.8953C5.91056 30.6549 5.63018 30.3133 5.46461 29.9135C5.29904 29.5138 5.25572 29.074 5.34013 28.6497C5.42453 28.2253 5.63287 27.8356 5.9388 27.5296C6.24473 27.2237 6.6345 27.0154 7.05884 26.931C7.48317 26.8465 7.923 26.8899 8.32272 27.0554C8.72243 27.221 9.06407 27.5014 9.30444 27.8611C9.5448 28.2208 9.6731 28.6438 9.6731 29.0764C9.6731 29.6566 9.44263 30.213 9.03239 30.6232C8.62216 31.0334 8.06576 31.2639 7.4856 31.2639ZM18.4231 22.5139C17.5578 22.5139 16.7119 22.2573 15.9925 21.7766C15.273 21.2959 14.7123 20.6126 14.3811 19.8132C14.05 19.0137 13.9634 18.1341 14.1322 17.2854C14.301 16.4367 14.7177 15.6572 15.3295 15.0453C15.9414 14.4335 16.7209 14.0168 17.5696 13.848C18.4182 13.6792 19.2979 13.7658 20.0973 14.0969C20.8968 14.4281 21.58 14.9888 22.0608 15.7083C22.5415 16.4278 22.7981 17.2736 22.7981 18.1389C22.7981 19.2992 22.3372 20.412 21.5167 21.2325C20.6962 22.053 19.5834 22.5139 18.4231 22.5139ZM29.3606 31.2639C28.928 31.2639 28.505 31.1356 28.1453 30.8953C27.7856 30.6549 27.5052 30.3133 27.3396 29.9135C27.174 29.5138 27.1307 29.074 27.2151 28.6497C27.2995 28.2253 27.5079 27.8356 27.8138 27.5296C28.1197 27.2237 28.5095 27.0154 28.9338 26.931C29.3582 26.8465 29.798 26.8899 30.1977 27.0554C30.5974 27.221 30.9391 27.5014 31.1794 27.8611C31.4198 28.2208 31.5481 28.6438 31.5481 29.0764C31.5481 29.6566 31.3176 30.213 30.9074 30.6232C30.4972 31.0334 29.9408 31.2639 29.3606 31.2639Z" fill="white"/>
							</svg>
							<svg width="5" height="45" viewBox="0 0 5 54" fill="none" class="position-relative <?php echo ( Constants::CONNECT_EXTERNAL_API_TAB === $tab ) ? esc_attr( 'mo-caw-active-tab' ) : ''; ?>">
								<path d="M0 6.27147C0 3.80913 5 3.46214 5 0.999802C5 -3.0002 5 57.4999 5 52.9998C5 50.5308 0 49.8664 0 47.3973V6.27147Z" fill="#2854C5"/>
							</svg>
						</div>
						<div class="d-flex align-items-center my-1">
							<i class="fa-solid fa-dollar-sign mo-caw-cursor-pointer text-white fa-xl" data-bs-toggle="tooltip" data-bs-placement="right" title="Pricing Plans" onclick="window.location.href='admin.php?page=custom_api_wp_settings&tab=pricing-plan';"></i>
							<svg width="5" height="45" viewBox="0 0 5 54" fill="none" class="position-relative <?php echo ( Constants::PRICING_PLAN_TAB === $tab ) ? esc_attr( 'mo-caw-active-tab' ) : ''; ?>">
								<path d="M0 6.27147C0 3.80913 5 3.46214 5 0.999802C5 -3.0002 5 57.4999 5 52.9998C5 50.5308 0 49.8664 0 47.3973V6.27147Z" fill="#2854C5"/>
							</svg>
						</div>
						<div class="d-flex align-items-center my-1">
							<i class="far fa-user fa-xl text-white mo-caw-cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="right" title="Account" onclick="window.location.href='admin.php?page=custom_api_wp_settings&tab=user-account';"></i>
							<svg width="5" height="45" viewBox="0 0 5 54" fill="none" class="position-relative <?php echo ( Constants::USER_ACCOUNT_TAB === $tab ) ? esc_attr( 'mo-caw-active-tab' ) : ''; ?>">
								<path d="M0 6.27147C0 3.80913 5 3.46214 5 0.999802C5 -3.0002 5 57.4999 5 52.9998C5 50.5308 0 49.8664 0 47.3973V6.27147Z" fill="#2854C5"/>
							</svg>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Function to display plugin notices.
	 * It uses four different identifiers to identify the type of message.
	 * -info    For informational notices.
	 * -warning For warning notices.
	 * -success For success notices.
	 * -danger  For danger/error notices.
	 *
	 * @return void
	 */
	public static function display_notice() {
		$status  = DB_Utils::get_option( 'mo_caw_message_status' );
		$message = DB_Utils::get_option( 'mo_caw_message' );
		if ( $status ) {
			?>
			<div id="mo-caw-callout" class="callout callout-<?php echo esc_attr( $status ); ?>">
			<?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The message can contain html tags at times. ?>
			</div>
			<?php
		}
		DB_Utils::update_option( 'mo_caw_message', false );
		DB_Utils::update_option( 'mo_caw_message_status', false );
	}

	/**
	 * Display plugin right side section content.
	 *
	 * @return void
	 */
	public static function display_side_section() {
		$user_details = DB_Utils::get_option( 'mo_caw_user_display_details', array() );
		?>
		<div class="row">
			<div class="col-md-6">
				<div class="card mo-caw-card p-0 border-0 shadow-none mo-caw-rounded-16 mo-caw-element-to-toggle mo-caw-light-mode mo-caw-cursor-pointer" onclick="moCawSetupGuideRedirection()">
					<div class="pt-5 mo-caw-bg-blue-medium mo-caw-rounded-top"></div>
					<div class="pt-4 pb-3 mo-caw-mt-n3 mo-caw-bg-blue-dark mo-caw-rounded-top"></div>
					<div class="card-body mo-caw-card-body mo-caw-shadow mo-caw-rounded-16 mo-caw-mt-n2 mo-caw-element-to-toggle mo-caw-light-mode">
						<svg class="mo-caw-element-to-toggle mo-caw-light-mode" width="45" height="42" viewBox="0 0 53 50" fill="none">
							<path d="M16.73 25H29.1505M16.73 29.7619H34.1187M16.73 34.5238H24.1823M41.571 36.9048V20.2381L29.1505 8.33337H16.73C15.4124 8.33337 14.1487 8.83507 13.217 9.7281C12.2853 10.6211 11.7618 11.8323 11.7618 13.0953V36.9048C11.7618 38.1677 12.2853 39.3789 13.217 40.272C14.1487 41.165 15.4124 41.6667 16.73 41.6667H36.6028C37.9205 41.6667 39.1842 41.165 40.1159 40.272C41.0476 39.3789 41.571 38.1677 41.571 36.9048Z" stroke="#1B1B1F" stroke-width="2.38095" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M29.1505 8.33337V15.4762C29.1505 16.7392 29.6739 17.9504 30.6057 18.8434C31.5374 19.7364 32.8011 20.2381 34.1187 20.2381H41.571" stroke="#1B1B1F" stroke-width="2.38095" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<h6 class="card-title px-2 pt-3 mo-caw-element-to-toggle mo-caw-light-mode">Setup Guides and Documentation</h6>
						<p class="card-text px-2 mo-caw-text-grey-medium">Checkout setup guides and documentation.</p>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card mo-caw-card p-0 border-0 mo-caw-shadow mo-caw-rounded-16 mo-caw-element-to-toggle mo-caw-light-mode mo-caw-cursor-pointer" onclick="window.open('https:&#47;&#47;sandbox.miniorange.com?mo_plugin=mo_custom_api<?php echo ! empty( $user_details['user_email'] ) ? '&email=' . esc_attr( $user_details['user_email'] ) : ''; ?>', '_blank');">
					<div class="card-body mo-caw-card-body">
						<div class="text-center">
							<svg class="mo-caw-element-to-toggle mo-caw-light-mode" width="45" height="47" viewBox="0 0 48 50" fill="none">
								<path d="M41.927 29.1666H39.9305V8.33329H41.927C42.4565 8.33329 42.9643 8.1138 43.3388 7.7231C43.7132 7.3324 43.9235 6.80249 43.9235 6.24996C43.9235 5.69742 43.7132 5.16752 43.3388 4.77682C42.9643 4.38612 42.4565 4.16663 41.927 4.16663H5.98951C5.46 4.16663 4.95217 4.38612 4.57775 4.77682C4.20333 5.16752 3.99298 5.69742 3.99298 6.24996C3.99298 6.80249 4.20333 7.3324 4.57775 7.7231C4.95217 8.1138 5.46 8.33329 5.98951 8.33329H7.98604V29.1666H5.98951C5.46 29.1666 4.95217 29.3861 4.57775 29.7768C4.20333 30.1675 3.99298 30.6974 3.99298 31.25C3.99298 31.8025 4.20333 32.3324 4.57775 32.7231C4.95217 33.1138 5.46 33.3333 5.98951 33.3333H21.9617V35.7291L12.8775 41.9791C12.5048 42.2198 12.2184 42.5819 12.0625 43.0093C11.9065 43.4367 11.8897 43.9058 12.0146 44.3442C12.1395 44.7826 12.3992 45.166 12.7536 45.4351C13.108 45.7042 13.5374 45.8441 13.9756 45.8333C14.3681 45.838 14.7522 45.7141 15.0737 45.4791L21.9617 40.7291V43.75C21.9617 44.3025 22.1721 44.8324 22.5465 45.2231C22.9209 45.6138 23.4287 45.8333 23.9583 45.8333C24.4878 45.8333 24.9956 45.6138 25.37 45.2231C25.7444 44.8324 25.9548 44.3025 25.9548 43.75V40.7291L32.8428 45.4791C33.1643 45.7141 33.5484 45.838 33.9409 45.8333C34.3675 45.8299 34.7817 45.684 35.123 45.417C35.4644 45.15 35.7147 44.7759 35.8375 44.3497C35.9603 43.9234 35.949 43.4673 35.8053 43.0482C35.6616 42.6291 35.393 42.269 35.039 42.0208L25.9548 35.7708V33.3333H41.927C42.4565 33.3333 42.9643 33.1138 43.3388 32.7231C43.7132 32.3324 43.9235 31.8025 43.9235 31.25C43.9235 30.6974 43.7132 30.1675 43.3388 29.7768C42.9643 29.3861 42.4565 29.1666 41.927 29.1666ZM35.9374 29.1666H11.9791V8.33329H35.9374V29.1666Z" fill="#1B1B1F"/>
							</svg>
						</div>
						<h6 class="card-title px-2 mb-0 pb-0 pt-3 mo-caw-element-to-toggle mo-caw-light-mode">Sign up for a cloud trial!</h6>
						<p class="card-text p-2 mo-caw-text-grey-medium">Are you looking for a cloud-trial? Sign up for one and try out our amazing features!</p>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<div class="card mo-caw-card p-0 border-0 mo-caw-shadow mo-caw-rounded-16 mo-caw-element-to-toggle mo-caw-light-mode mo-caw-cursor-pointer" onclick="window.open('https:&#47;&#47;plugins.miniorange.com/wordpress', '_blank');">
					<div class="card-body">
						<div class="row">
							<div class="col-md-8">
								<h6 class="card-title p-2 mb-0 pb-0 mo-caw-element-to-toggle mo-caw-light-mode">Checkout more of our awesome plugins!</h6>
								<p class="card-text p-2 mo-caw-text-grey-medium">Create custom and protect WP REST APIs’, Sync Products and Orders into WooCommerce, SSO into WordPress and a lot more...</p>
							</div>
							<div class="col-md-4 p-0">
								<img id="mo-caw-review-img" class="position-absolute" src="<?php echo esc_attr( MO_CUSTOM_API_URL ) . 'classes/Common/Resources/Images/review.jpeg'; ?>" alt="Checkout more of our plugins" height="140px">
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<div class="card mo-caw-card p-0 border-0 mo-caw-shadow mo-caw-rounded-16 mo-caw-element-to-toggle mo-caw-light-mode mo-caw-cursor-pointer" onclick="window.open('https:&#47;&#47;www.youtube.com/playlist?list=PL2vweZ-PcNpfvGGLwQYVdqAK7HpCERrX6', '_blank');">
					<div class="card-body">
						<div class="text-center">
							<svg width="55" height="40" viewBox="0 0 69 50" fill="none">
								<g clip-path="url(#clip0_475_5535)">
									<path d="M66.6475 7.79861C66.2566 6.29127 65.4941 4.91707 64.436 3.81286C63.3778 2.70864 62.0609 1.91295 60.6164 1.505C55.328 0 34.0439 0 34.0439 0C34.0439 0 12.7588 0.0455554 7.4704 1.55056C6.02584 1.95853 4.70891 2.75427 3.65075 3.85853C2.5926 4.96279 1.83013 6.33705 1.43928 7.84444C-0.160334 17.6494 -0.780855 32.59 1.48321 42.0028C1.87409 43.5101 2.63658 44.8843 3.69473 45.9885C4.75289 47.0927 6.0698 47.8884 7.51432 48.2964C12.8027 49.8014 34.0873 49.8014 34.0873 49.8014C34.0873 49.8014 55.3716 49.8014 60.6598 48.2964C62.1043 47.8885 63.4213 47.0928 64.4795 45.9886C65.5377 44.8844 66.3002 43.5102 66.6911 42.0028C68.3783 32.1839 68.8982 17.2525 66.6475 7.79861Z" fill="#FF0000"/>
									<path d="M27.269 35.5718L44.9258 24.9002L27.269 14.2285V35.5718Z" fill="white"/>
								</g>
								<defs>
									<clipPath id="clip0_475_5535">
									<rect width="68.1567" height="50" fill="white"/>
									</clipPath>
								</defs>
							</svg>
						</div>
						<h6 class="card-title px-2 mb-0 pb-0 pt-3 mo-caw-element-to-toggle mo-caw-light-mode">Learn how to use the plugin on YouTube</h6>
						<p class="card-text p-2 mo-caw-text-grey-medium">Have a look at our YouTube videos to understand more about the plugin and the features.</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
