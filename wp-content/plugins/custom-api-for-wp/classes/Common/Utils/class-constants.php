<?php
/**
 * This file defines common constants for the plugin.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common;

/**
 * This Class has common constants required by the plugin.
 */
class Constants {

	// Plugin detail constants.
	public const PLAN_NAME      = 'wp_rest_custom_api_standard';
	public const PLUGIN_FILE    = 'custom-api-for-wordpress.php';
	public const PLAN_NAMESPACE = '\MO_CAW\Common';

	// minOrange constants.
	public const MINIORANGE            = 'https://miniorange.com';
	public const HOST_NAME             = 'https://login.xecurify.com';
	public const SEND_NOTIFICATION_API = '/moas/api/notify/send';
	public const CUSTOMER_KEY_API      = '/moas/rest/customer/key';
	public const CUSTOMER_ADD_API      = '/moas/rest/customer/add';

	// miniOrange API status constants.
	public const SUCCESS_STATUS                          = 'SUCCESS';
	public const TRANSACTION_LIMIT_EXCEEDED_STATUS       = 'TRANSACTION_LIMIT_EXCEEDED';
	public const CUSTOMER_USERNAME_ALREADY_EXISTS_STATUS = 'CUSTOMER_USERNAME_ALREADY_EXISTS';

	// Default miniOrange user detail constants.
	public const DEFAULT_CUSTOMER_KEY = '16555';
	public const DEFAULT_API_KEY      = 'fFd2XcvTGDemZvbw1bcUesNJWEqKbbUq';

	// Hook name constants.
	public const INIT_HOOK          = 'init';
	public const ADMIN_INIT_HOOK    = 'admin_init';
	public const REST_API_INIT_HOOK = 'rest_api_init';
	public const ADMIN_MENU_HOOK    = 'admin_menu';
	public const ADMIN_HEAD_HOOK    = 'admin_head';
	public const ADMIN_FOOTER_HOOK  = 'admin_footer';
	public const ADMIN_NOTICES_HOOK = 'admin_notices';

	// Class nonce constants.
	public const API_CREATION_NONCE          = 'API_Creation';
	public const SQL_API_CREATION_NONCE      = 'SQL_API_Creation';
	public const EXTERNAL_API_CREATION_NONCE = 'External_API_Connection';
	public const MO_USER_NONCE               = 'MO_User';

	// Tab constants.
	public const CUSTOM_API_TAB           = 'custom-api';
	public const CUSTOM_SQL_API_TAB       = 'custom-sql-api';
	public const CONNECT_EXTERNAL_API_TAB = 'connect-external-api';
	public const PRICING_PLAN_TAB         = 'pricing-plan';
	public const USER_ACCOUNT_TAB         = 'user-account';

	// Feature name constants.
	public const GUI_ENDPOINT      = 'gui_endpoint';
	public const SQL_ENDPOINT      = 'sql_endpoint';
	public const EXTERNAL_ENDPOINT = 'external_endpoint';

	// Plugin operation status constants.
	public const MESSAGE_STATUS_SUCCESS = 'success';
	public const MESSAGE_STATUS_INFO    = 'info';
	public const MESSAGE_STATUS_WARNING = 'warning';
	public const MESSAGE_STATUS_DANGER  = 'danger';

	// Plugin operation constants.
	public const ADD    = 'add';
	public const VIEW   = 'view';
	public const EDIT   = 'edit';
	public const DELETE = 'delete';
	public const TEST   = 'test';

	// Plugin operation messages.
	public const SAVE_SUCCESS                = 'Settings saved successfully.';
	public const SAVE_ERROR                  = 'Failed to save API settings. Please make sure to change settings or try again.';
	public const DELETION_SUCCESS            = 'API deleted successfully.';
	public const DELETION_ERROR              = 'Failed to delete API. Please try again.';
	public const NO_REQUEST_COLUMN_SELECTED  = 'There is no request column selected. Please select at least one column';
	public const FILTER_FIELDS_MISSING       = 'Condition or column missing for Filters <em>(Based on column, condition and parameter)</em>. Please verify the respective settings.';
	public const SQL_ENDPOINT_ALREADY_EXISTS = 'A SQL-based custom API with same name, method and namespace already exists. Try again with different parameters.';
	public const GUI_ENDPOINT_ALREADY_EXISTS = 'A GUI-based custom API with same name, method and namespace already exists. Try again with different parameters.';
	public const FEATURE_NOT_SUPPORTED       = 'You are not allowed to access this feature with current plan.';
	public const API_DISABLED                = 'Sorry, the endpoint has been disabled, please contact website administrator.';
	public const DUPLICATE_PARAMETER_NAME    = 'Duplicate parameter names found.';
	public const NO_NUMERIC_PARAMETER_NAME   = 'Parameter name cannot be numeric.';

	// HTTP method constants.
	public const HTTP_GET    = 'get';
	public const HTTP_POST   = 'post';
	public const HTTP_PUT    = 'put';
	public const HTTP_PATCH  = 'patch';
	public const HTTP_DELETE = 'delete';

	// Authorization constants.
	public const NO_AUTHORIZATION       = 'no-auth';
	public const BASIC_AUTHORIZATION    = 'basic-authorization';
	public const BEARER_TOKEN           = 'bearer-token';
	public const API_KEY_AUTHENTICATION = 'api-key-authentication';

	// Request body constants.
	public const NO_BODY               = 'no-body';
	public const XML                   = 'xml';
	public const JSON                  = 'json';
	public const GRAPH_QL              = 'graphql';
	public const X_WWW_FORM_URLENCODED = 'x-www-form-urlencoded';

	// Response type constants.
	public const RESPONSE_CONTENT_SUCCESS_DEFAULT        = 'SUCCESS';
	public const RESPONSE_CONTENT_ERROR_DEFAULT          = 'ERROR';
	public const RESPONSE_CONTENT_AUTHENTICATION_DEFAULT = 'DEFAULT';

	// External API connection status messages constants.
	public const EXTERNAL_API_NAME_NOT_FOUND   = 'API name not recognized';
	public const SHORTCODE_SETTINGS_NOT_FOUND  = 'Shortcode settings not found';
	public const INVALID_HEADERS_FORMAT        = 'Headers not passed as an array.';
	public const EXTERNAL_API_EXCEPTION_PREFIX = 'There was an error when trying to execute External API: ';
	public const UNAUTHORIZED_ACCESS           = 'User role not allowed to access External API. Please contact your administrator.';
	public const DEFAULT_API_ERROR_MESSAGE     = 'There was an error processing your request. Please contact your administrator';

	// External API constants.
	public const SIMPLE_API_EXTERNAL_API_TYPE = 'simple_api';

	public const DISABLED = 'disabled';

	// Plan name constants.
	public const STANDARD_PLAN_NAME = 'wp_rest_custom_api_standard';

	// Generic constants.
	public const DEFAULT              = 'default';
	public const CUSTOM               = 'custom';
	public const TABLE                = 'table';
	public const RAW                  = 'raw';
	public const SUCCESS              = 'SUCCESS';
	public const ERROR                = 'ERROR';
	public const BAD_REQUEST          = 'BAD_REQUEST';
	public const UNAUTHORIZED         = 'UNAUTHORIZED';
	public const INVALID_FORMAT       = 'invalid_format';
	public const FORBIDDEN            = 'FORBIDDEN';
	public const ENDPOINT_DEACTIVATED = 'ENDPOINT_DEACTIVATED';

	// Default API detail keys, values, etc.
	public const X_WWW_HEADER_NAME = 'application/x-www-form-urlencoded';
	public const JSON_HEADER_NAME  = 'application/json';
	public const XML_HEADER_NAME   = 'text/xml';
}
