<?php
/**
 * This file deals with Display of Shortcode content.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common\Functionality;

use Exception;
use MO_CAW\Common\Constants;

/**
 * Class deals with display of shortcode content.
 */
class Display_Shortcode {
	/**
	 * This function will display the basic API data in formatted way.
	 *
	 * @param  array $arguments List of arguments passed in the shortcode.
	 * @return string
	 * @throws Exception Throws an exception in case of executing an API.
	 */
	public static function render_shortcode( $arguments ) {
		try {
			$api_name = $arguments['api'];
			$method   = $arguments['method'] ?? '';
			$api_data = apply_filters( 'mo_caw_execute_external_api', $api_name, $method, array() );
			$api_data = json_decode( $api_data, true );
			ob_start();
			if ( ! empty( $api_data['mo_error'] ) ) {
				throw new Exception( Constants::DEFAULT_API_ERROR_MESSAGE );
			}
			echo '<div style="font-family:Calibri;padding:0 3%;">';
			echo '<style>table{border-collapse:collapse;}th {background-color: #eee; text-align: center; padding: 8px; border-width:1px; border-style:solid; border-color:#212121;}tr:nth-child(odd) {background-color: #f2f2f2;} td{padding:8px;border-width:1px; border-style:solid; border-color:#212121;}</style>';
			echo '<table>';
			echo External_API_Connection::generate_api_response_table_rows( '', $api_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Data already escaped in the function.
			echo '</table>';
		} catch ( Exception $e ) {
			echo '<div style="display:flex; padding: 1%; background: #ffe9e9; border-radius: 20px; border: 3px solid #f97f7f">
					<svg style="margin: -8px 0px 0px 0px; padding: inherit" xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" viewBox="0 0 24 24" fill="none">
						<path d="M12 7V13M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="#ff0000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<circle cx="12" cy="16.5" r="1" fill="#ff0000"/>
					</svg>
					<strong style="color:red">' . esc_html( $e->getMessage() ) . '</strong>
				<div>';
		}
		return ob_get_clean();
	}
}
