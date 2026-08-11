<?php
/**
 * This file deals with creating instances for functionalities at different init point depending on the plugin plan.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Functionality;

use MO_CAW\Common\Constants;
use MO_CAW\Common\Utils;

add_action( Constants::ADMIN_INIT_HOOK, __NAMESPACE__ . '\\admin_init_functionalities' );
add_action( Constants::INIT_HOOK, __NAMESPACE__ . '\\init_functionalities' );
add_action( Constants::REST_API_INIT_HOOK, __NAMESPACE__ . '\\rest_init_functionalities', 10 );

/**
 * All functionalities to be called/executed on admin_init(wp-admin).
 *
 * @return void
 */
function admin_init_functionalities() {
	$class_instances = array( 'Update_Framework' );
	instance_creator( $class_instances );
}

/**
 * All functionality to be called/executed on init.
 *
 * @return void
 */
function init_functionalities() {

	$class_instances = array( 'Display_Shortcode', 'External_API_Connection' );
	instance_creator( $class_instances );

	register_actions();
}

/**
 * All functionality to be called/executed on rest_api_init.
 *
 * @return void
 */
function rest_init_functionalities() {
	$class_instances = array( 'API_Creation', 'SQL_API_Creation' );
	instance_creator( $class_instances );
}

/**
 * Helps in creation of class of objects of functionalities.
 *
 * @param array $class_name_array Class list without name-space to be loaded.
 *
 * @return void
 */
function instance_creator( $class_name_array ) {
	$name_space = Constants::PLAN_NAMESPACE . '\Functionality\\';
	foreach ( $class_name_array as $class_name ) {
		$class_instance = $name_space . $class_name;
		$class_instance = Utils::validate_class_name( $name_space, $class_name );
		new $class_instance();
	}
}

/**
 * Register actions for functionalities.
 *
 * @return void
 */
function register_actions() {
	$shortcode_class = Utils::validate_class_name( Constants::PLAN_NAMESPACE . '\Functionality\\', 'Display_Shortcode' );
	add_shortcode( 'mo_custom_api_shortcode', array( $shortcode_class, 'render_shortcode' ) );
}
