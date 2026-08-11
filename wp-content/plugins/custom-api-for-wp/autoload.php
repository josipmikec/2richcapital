<?php
/**
 * This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MO_CUSTOM_API_DIR', plugin_dir_path( __FILE__ ) );
define( 'MO_CUSTOM_API_URL', plugin_dir_url( __FILE__ ) );

mo_caw_include_file( MO_CUSTOM_API_DIR . '/classes' );

use MO_CAW\Common\Constants;

$migration_name = Constants::PLAN_NAMESPACE . '\Update_Migration';
$migration_name::update_migration();

/**
 * Wrapper for require_all().
 *
 * Wrapper to call require_all() in perfect order.
 *
 * @param  string $folder Folder to Traverse.
 * @return void
 **/
function mo_caw_include_file( $folder ) {
	if ( ! is_dir( $folder ) ) {
		return;
	}
	$folder   = mo_caw_sane_dir_path( $folder );
	$realpath = realpath( $folder );
	if ( false !== $realpath && ! is_dir( $folder ) ) {
		return;
	}
	$php_file_paths = mo_caw_get_php_files( $folder );
	mo_caw_require_all( $php_file_paths );
}

/**
 * Function to sanitize dir paths.
 *
 * @param string $folder Dir Path to sanitize.
 *
 * @return string sane path.
 */
function mo_caw_sane_dir_path( $folder ) {
	return str_replace( '/', DIRECTORY_SEPARATOR, $folder );
}

/**
 * Order all php files.
 *
 * Get all php files to require() in perfect order.
 *
 * @param  string $folder Folder to Traverse.
 * @return array Array of php files to require.
 **/
function mo_caw_get_php_files( $folder ) {
	$filepaths      = mo_caw_get_dir_contents( $folder );
	$php_file_paths = array();

	foreach ( $filepaths as $file => $file_path ) {
		if ( strpos( $file_path, '.php' ) !== false ) {
			$php_file_paths[ $file ] = $file_path;
		}
	}

	return $php_file_paths;
}


/**
 * Traverse all sub-directories for files.
 *
 * Get all files in a directory.
 *
 * @param  string $folder  Folder to Traverse.
 * @param  Array  $results Array of files to append to.
 * @return Array $results Array of files found.
 **/
function mo_caw_get_dir_contents( $folder, &$results = array() ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $folder, RecursiveDirectoryIterator::KEY_AS_PATHNAME ), RecursiveIteratorIterator::CHILD_FIRST ) as $file => $info ) {
		if ( $info->isFile() && $info->isReadable() ) {
			$results[ $file ] = realpath( $info->getPathname() );
		}
	}
	return $results;
}


/**
 * All files given as input are passed to require_once().
 *
 * Wrapper to call require_all() in perfect order.
 *
 * @param  Array $filepaths array of files to require.
 * @return void
 **/
function mo_caw_require_all( $filepaths ) {
	/**
	 * Filtering the files on basis of an identifier in filepath which has more priority from the complete set of files.
	 * Sequence of the filter conditions will be the sequence of files to be loaded.
	 */

	$identifier     = array( DIRECTORY_SEPARATOR . 'Utils' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . 'Functionality\Mo-user' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR );
	$priority_files = array();
	foreach ( $identifier as $identifying_string ) {
		$filtered_filepaths = array_filter(
			$filepaths,
			function ( $file_path ) use ( $identifying_string ) {
				return strpos( $file_path, $identifying_string ) !== false;
			}
		);
		sort( $filtered_filepaths );
		$priority_files = array_merge( $priority_files, $filtered_filepaths );
	}

	// Remove priority file paths from the complete filepath list.
	$filepaths = array_diff( $filepaths, $priority_files );

	// Include priority file first.
	foreach ( $priority_files as $file => $file_path ) {
		include_once $file_path;
	}

	sort( $filepaths );

	foreach ( $filepaths as $file => $file_path ) {
		include_once $file_path;
	}
}

/**
 * Function to load all methods.
 *
 * @param array $all_methods Methods needed to be load.
 *
 * @return void
 */
function mo_caw_load_all_methods( $all_methods ) {
	foreach ( $all_methods as $method ) {
		new $method();
	}
}
