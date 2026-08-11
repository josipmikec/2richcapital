<?php
/**
 * This file deals with creating instances for functionalities at different init point depending on the plugin plan.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Standard\Functionality;

use MO_CAW\Common\Constants;
use MO_CAW\Standard\Views\Feedback;

add_action( Constants::ADMIN_FOOTER_HOOK, __NAMESPACE__ . '\\admin_footer_functionalities' );

/**
 * Function to initiate a flow for plugin deactivation, currently helps in displaying feedback form.
 *
 * @return void
 */
function admin_footer_functionalities() {
	Feedback::display_feedback_form();
}
