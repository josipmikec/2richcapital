<?php
/**
 * This file deals with API Security feature functionality logic.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Functionality;

/**
 * Class deals with API Security feature functionality.
 */
class API_Security {


	/**
	 * Function responsible to handle API authentication.
	 *
	 * @param object $request API Call Request.
	 *
	 * @return boolean
	 */
	public static function authorize_custom_api_request( $request ) {
		return true;
	}
}
