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

/**
 * This class deals with rendering common views for plugin.
 */
class Display_Common {


	/**
	 * Function to display plugin header.
	 *
	 * @return void
	 */
	public static function display_header() {
	}

	/**
	 * Function to display plugin notices.
	 *
	 * @return void
	 */
	public static function display_notice() {
	}
	/**
	 * Function to display plugin side section content.
	 *
	 * @return void
	 */
	public static function display_side_section() {
		?>
		<div class="mo-wcps-adv-section">
			<div class="mo-wcps-adv">
				<div class="mo-wcps-adv-box">
					<div class="mo-wcps-adv-content">
						<div class="mo-wcps-adv-header">
							<div class="mo-wcps-adv-name">Custom API for WordPress</div>
							<img src='<?php echo esc_url( MO_CUSTOM_API_URL . 'resources/images/miniorange.jpeg' ); ?>' width="40px" height='40px'></img>    
						</div>
						<p class="mo-wcps-adv-line">
							<br>
							Create your own REST API endpoints in WordPress to interact with WordPress database to fetch, insert, update, delete data. Also, any external APIs can be connected to WordPress for interaction between WordPress & External application.
						</p>
						<p>
							<input type="button" value='Download' class="mo-wcps-button" onclick="javascript:window.open('https://wordpress.org/plugins/custom-api-for-wp', '_blank');" />
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Function to display plugin navbar.
	 *
	 * @param string $tab Current tab name.
	 *
	 * @return void
	 */
	public static function display_navbar( $tab ) {
	}
}
