<?php
/**
 * This file deals with loading the plugin's UI logic.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Views;

use MO_CAW\Common\Utils;
use MO_CAW\Common\Constants;

add_action( Constants::ADMIN_MENU_HOOK, __NAMESPACE__ . '\\admin_menu' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\plugin_settings_style' );
add_action( Constants::ADMIN_INIT_HOOK, __NAMESPACE__ . '\\admin_init_functionalities' );
add_action( Constants::ADMIN_HEAD_HOOK, __NAMESPACE__ . '\\admin_head_functionalities' );

if ( ! isset( $_SESSION ) ) {
	session_start();
}

/**
 * All functionalities to be called/executed on admin_init(wp-admin).
 *
 * @return void
 */
function admin_init_functionalities() {
	register_actions();
}

/**
 * All functionalities to be called/executed on admin_head(wp-admin).
 *
 * @return void
 */
function admin_head_functionalities() {
	add_menu_icon_css();
}


/**
 * Links plugin menu in admin menu options.
 *
 * @return void
 */
function admin_menu() {
	$slug = 'custom_api_wp_settings';
	add_menu_page(
		'MO API Settings ' . __( 'Configure Custom API Settings', 'custom-api-for-wp' ),
		'Custom API plugin', // The string is case sensitive.
		'administrator',
		$slug,
		__NAMESPACE__ . '\\display_plugin',
		MO_CUSTOM_API_URL . '/classes/Common/Resources/Images/miniorange.png'
	);
}

/**
 * Loads all scripts and styles.
 *
 * @return void
 */
function plugin_settings_style() {
	if ( isset( $_GET['page'] ) && 'custom_api_wp_settings' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Using this to enqueue styles and script only on the plugin page.
		// Load styles for the plugin.
		wp_enqueue_style( 'mo-caw-bootstrap-style', MO_CUSTOM_API_URL . 'classes/Common/Resources/CSS/Bootstrap/bootstrap.min.css', array(), '5.0.2', false );
		wp_enqueue_style( 'mo-caw-font-awesome-style', MO_CUSTOM_API_URL . 'classes/Common/Resources/CSS/FontAwesome/all.min.css', array(), '6.4.2', false );
		wp_enqueue_style( 'mo-caw-phone-style', MO_CUSTOM_API_URL . 'classes/Common/Resources/CSS/Lib/phone.min.css', array(), Utils::get_version_number(), false );
		wp_enqueue_style( 'mo-caw-plugin-style', MO_CUSTOM_API_URL . 'classes/Common/Resources/CSS/mo-caw-style.min.css', array(), Utils::get_version_number(), false );

		// Load scripts for the plugin.
		wp_enqueue_script( 'mo-caw-jquery-script', MO_CUSTOM_API_URL . 'classes/Common/Resources/JS/Lib/jquery.min.js', array(), '3.7.1', false );
		wp_enqueue_script( 'mo-caw-sortable-script', MO_CUSTOM_API_URL . 'classes/Common/Resources/JS/Lib/Sortable.min.js', array(), '1.15.2', false );
		wp_enqueue_script( 'mo-caw-phone-script', MO_CUSTOM_API_URL . 'classes/Common/Resources/JS/Lib/phone.min.js', array(), Utils::get_version_number(), false );
		wp_enqueue_script( 'mo-caw-bootstrap-bundle-script', MO_CUSTOM_API_URL . 'classes/Common/Resources/JS/Bootstrap/bootstrap.bundle.min.js', array(), '5.0.2', false );
		wp_enqueue_script( 'mo-caw-plugin-script', MO_CUSTOM_API_URL . 'classes/Common/Resources/JS/mo-caw-script.min.js', array(), Utils::get_version_number(), false );
		$tab = '';
		if ( isset( $_GET['tab'] ) ) {
			// Unslash and sanitize the 'tab' parameter.
			$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
			// Optionally, verify a nonce if this value is used for sensitive actions.
			if ( isset( $_GET['_wpnonce'] ) ) {
				$wpnonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
				if ( ! wp_verify_nonce( $wpnonce, 'mo_caw_tab_action' ) ) {
					$tab = '';
				}
			}
		}
		wp_localize_script(
			'mo-caw-plugin-script',
			'moCawData',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonces'  => array(
					'external_api_nonce' => wp_create_nonce( 'mo_caw_external_api_get_response' ),
				),
				'tab'     => $tab,
			)
		);
	}
}

/**
 * Function to initiate plugin display call.
 *
 * @return void
 */
function display_plugin() {
	if ( current_user_can( 'manage_options' ) ) {
		$ui_handler = new UI_Handler();
		$ui_handler->display_complete_content();
	}
}

/**
 * Register actions for common functionalities.
 *
 * @return void
 */
function register_actions() {
	add_action( 'wp_ajax_mo_caw_get_plugin_version_details', array( 'MO_CAW\Common\Utils', 'get_plugin_version_details' ) );
	add_action( 'wp_ajax_mo_caw_get_table_columns', array( 'MO_CAW\Common\DB_Utils', 'get_table_columns' ) );
	add_action( 'wp_ajax_mo_caw_enable_disable_api', array( 'MO_CAW\Common\Views\UI_Handler', 'enable_disable_api' ) );
	add_action( 'wp_ajax_mo_caw_get_api_response', array( 'MO_CAW\Common\Views\External_API_Connection', 'get_api_response' ) );
}

/**
 * Css for plugin icon in admin side menu section.
 *
 * @return void
 */
function add_menu_icon_css() {
	?>
	<style>
		.wp-not-current-submenu .menu-top .toplevel_page_custom_api_wp_settings .menu-top-first .menu-top-last,
		#adminmenu #toplevel_page_custom_api_wp_settings div.wp-menu-image.dashicons-before {
			display: flex;
			justify-content: center;
			align-items: center;
		}
		#adminmenu #toplevel_page_custom_api_wp_settings .wp-menu-image img {
			height: 1.5rem;
			padding: 0px;
		}
	</style>
	<?php
}
