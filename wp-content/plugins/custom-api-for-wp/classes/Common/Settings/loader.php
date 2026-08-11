<?php
/**
 * This file deals with creating instances for settings at different init point depending on the plugin plan.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Settings;

use MO_CAW\Common\Constants;
use MO_CAW\Common\Utils;


add_action( Constants::ADMIN_INIT_HOOK, __NAMESPACE__ . '\\admin_init_functionalities' );
add_action( Constants::INIT_HOOK, __NAMESPACE__ . '\\init_functionalities' );
add_action( Constants::REST_API_INIT_HOOK, __NAMESPACE__ . '\\rest_init_functionalities' );

/**
 * All functionalities to be called/executed on admin_init(wp-admin).
 *
 * @return void
 */
function admin_init_functionalities() {
	$class_instances = array();
	$nonce_names     = array( Constants::API_CREATION_NONCE, Constants::SQL_API_CREATION_NONCE, Constants::EXTERNAL_API_CREATION_NONCE, Constants::MO_USER_NONCE );
	foreach ( $nonce_names as $nonce ) {
		$nonce_name = 'MO_CAW_' . $nonce . '_Nonce';
		if ( ! empty( $_POST[ $nonce_name ] ) ) { // phpcs:ignore --WordPress.Security.NonceVerification.Missing Nonce verification is done while actual form submission.
			array_push( $class_instances, $nonce );
		}
	}
	instance_creator( $class_instances );
}

/**
 * All functionality to be called/executed on init.
 *
 * @return void
 */
function init_functionalities() {
	$class_instances = array();
	instance_creator( $class_instances );
}
/**
 * All functionality to be called/executed on rest_api_init.
 *
 * @return void
 */
function rest_init_functionalities() {
	$class_instances = array();
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
	$name_space = Constants::PLAN_NAMESPACE . '\Settings\\';
	foreach ( $class_name_array as $class_name ) {
		$class_instance = $name_space . $class_name;
		$class_instance = Utils::validate_class_name( $name_space, $class_name );
		new $class_instance();
	}
}
