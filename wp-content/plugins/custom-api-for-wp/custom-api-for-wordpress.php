<?php
/**
 * Main file to handle all functionalities flow.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area.
 *
 * @link    https://www.miniorange.com
 * @since   1.0.0
 * @package Custom_Api_For_WordPress
 *
 * @wordpress-plugin
 * Plugin Name:       Custom API for WP
 * Plugin URI:        https://wordpress.org/plugins/custom-api-for-wp/
 * Description:       This plugin helps in creating custom API endpoints for extracting customized data from the database. The plugin can also be extended to integrate external APIs in WordPress.
 * Version:           4.7.0
 * Author:            miniOrange
 * Author URI:        https://www.miniorange.com
 * License:           Expat
 * License URI:       https://plugins.miniorange.com/mit-license
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

register_activation_hook( __FILE__, array( 'MO_CAW\Common\Utils', 'run_on_plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'MO_CAW\Common\Utils', 'run_on_plugin_deactivation' ) );

require 'autoload.php';
