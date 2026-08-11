<?php
/**
 * This file deals with migration issues.
 *
 * @package    Custom_Api_For_WordPress
 * @subpackage Custom_Api_For_WordPress/includes
 * @author     miniOrange <info@miniorange.com>
 * @link       https://miniorange.com
 */

namespace MO_CAW\Common;

/**
 * This Class deals with common functions required by complete plugin.
 */
class Update_Migration {

	/**
	 * Function to handle migration issues.
	 *
	 * @return void
	 */
	public static function update_migration() {
		$version_number = DB_Utils::get_option( 'mo_caw_last_version', '1.0.0' );

		if ( version_compare( Utils::get_version_number(), '3.0.0', '>=' ) ) {
			$custom_endpoints = DB_Utils::get_configuration( array( 'type' => Constants::GUI_ENDPOINT ) );

			foreach ( $custom_endpoints as $custom_endpoint ) {
				$configuration = &$custom_endpoint['configuration'];
				if ( ! empty( $configuration['value_specific_filter']['filter_details'] ) ) {

					$filter_details = &$configuration['value_specific_filter']['filter_details'];
					foreach ( $filter_details as $index => &$filter_detail ) {
						if ( is_numeric( $filter_detail['parameter'] ) ) {
							$filter_detail['parameter'] = 'Position_' . $filter_detail['parameter'];
						}
						if ( Constants::HTTP_DELETE === $custom_endpoint['method'] ) {
							$filter_detail['parameter'] = 'column_param' . ( ++$index );
						}
					}
				}
				DB_Utils::update_configuration( $custom_endpoint );
			}
		} elseif ( version_compare( Utils::get_version_number(), $version_number ) ) {

			// Convert configuration structure.
			$gui_config          = DB_Utils::get_option( 'CUSTOM_API_WP_LIST', array() );
			$sql_config          = DB_Utils::get_option( 'custom_api_wp_sql', array() );
			$external_config     = DB_Utils::get_option( 'custom_api_save_ExternalApiConfiguration', array() );
			$adv_external_config = DB_Utils::get_option( 'mo_custom_api_adv_settings', array() );

			DB_Utils::create_custom_tables();

			// Convert Custom GUI endpoint.
			foreach ( $gui_config as $connection_name => $config ) {
				$configuration   = array();
				$detailed_config = array();

				$configuration['type']            = Constants::GUI_ENDPOINT;
				$configuration['connection_name'] = $connection_name;
				$configuration['is_enabled']      = true;
				$configuration['method']          = $config['MethodName'] ? strtolower( $config['MethodName'] ) : Constants::HTTP_GET;
				$configuration['namespace']       = 'mo/v1';

				$detailed_config['table'] = $config['TableName'] ?? '';
				if ( $detailed_config['table'] ) {

					$detailed_config['request_columns']                          = explode( ',', $config['SelectedColumn'] );
					$detailed_config['blocked_roles']                            = array();
					$detailed_config['common_filters']['orderby']['column']      = $config['filter_column'] ?? '';
					$detailed_config['common_filters']['orderby']['option']      = $config['order_condition'] ?? '';
					$detailed_config['value_specific_filter']['filter_relation'] = $config['operator'] ?? '';
					$detailed_config['selected_response_columns']                = array();

					if ( ! empty( $config['SelectedResponseColumn'] ) && ( 'none selected' !== $config['SelectedResponseColumn'] ) ) {
						array_push( $detailed_config['selected_response_columns'], $config['SelectedResponseColumn'] );
					}
					if ( 'no condition' !== $config['SelectedCondtion'] ) {
						$config['SelectedCondtion'] = ( 'Like' === $config['SelectedCondtion'] ) ? 'like' : ( ( 'less than' === $config['SelectedCondtion'] ) ? '<' : $config['SelectedCondtion'] );
						$filter_details             = array(
							array(
								'column'    => $config['ConditionColumn'] ?? '',
								'condition' => $config['SelectedCondtion'] ?? '',
								'parameter' => $config['SelectedParameter'] ?? '',
							),
						);
					}

					if ( ! empty( $config['column_if_op'] ) ) {
						foreach ( $config['column_if_op'] as $index => $column ) {
							if ( 'no condition' !== $config['condition_if_op'] ) {
								$config['SelectedCondtion'] = ( 'Like' === $config['SelectedCondtion'] ) ? 'like' : ( ( 'less than' === $config['SelectedCondtion'] ) ? '<' : $config['SelectedCondtion'] );
								$push_array                 = array(
									'column'    => $column,
									'condition' => $config['condition_if_op'][ $index ] ?? '',
									'parameter' => $config['param_if_op'][ $index ] ?? '',
								);
								array_push( $filter_details, $push_array );
							}
						}
					}

					$detailed_config['value_specific_filter']['filter_details'] = $filter_details;

					$configuration['configuration'] = $detailed_config;

					DB_Utils::update_configuration( $configuration );
				}
			}
			// Convert Custom SQL endpoint.
			foreach ( $sql_config as $connection_name => $config ) {
				$configuration   = array();
				$detailed_config = array();

				$configuration['type']            = Constants::SQL_ENDPOINT;
				$configuration['connection_name'] = $connection_name;
				$configuration['is_enabled']      = true;
				$configuration['method']          = $config['method'] ? strtolower( $config['method'] ) : Constants::HTTP_GET;
				$configuration['namespace']       = 'mo/v1';

				$sql_queries                                   = explode( ';', $config['sql_query'] );
				$configuration['configuration']['sql_queries'] = array_filter( $sql_queries );

				DB_Utils::update_configuration( $configuration );
			}
			// Convert External endpoint.
			foreach ( $external_config as $connection_name => $config ) {
				$configuration   = array();
				$detailed_config = array();

				$configuration['type']                = Constants::EXTERNAL_ENDPOINT;
				$configuration['connection_name']     = $connection_name;
				$configuration['is_enabled']          = true;
				$configuration['method']              = $config['ExternalApiRequestType'] ? strtolower( $config['ExternalApiRequestType'] ) : Constants::HTTP_GET;
				$configuration['api_type']            = Constants::SIMPLE_API_EXTERNAL_API_TYPE;
				$configuration['chained_connections'] = array();
				$configuration['is_loop']             = false;
				$configuration['is_used_by']          = array();

				$detailed_config['endpoint']             = $config['ExternalEndpoint'] ?? site_url();
				$detailed_config['blocked_roles']        = array();
				$detailed_config['response_format']      = $config['ResponseBodyType'] ?? Constants::JSON;
				$detailed_config['body']['request_type'] = $config['ExternalApiBodyRequestType'] ?? Constants::NO_BODY;

				$detailed_config['authorization']['auth_type']   = Constants::NO_AUTHORIZATION;
				$detailed_config['authorization']['auth_config'] = array();

				if ( ! empty( $config['ExternalHeaders'][0] ) ) {
					$updated_headers = array();
					foreach ( $config['ExternalHeaders'] as $header ) {
						$header_details                        = explode( ':', $header );
						$updated_headers[ $header_details[0] ] = $header_details[1];
					}
					$config['ExternalHeaders'] = $updated_headers;
				}
				$detailed_config['header'] = $config['ExternalHeaders'] ?? array();

				$old_post_field = ( ! empty( $config['ExternalApiPostFieldNew'] ) ) ? $config['ExternalApiPostFieldNew'] : $config['ExternalApiPostField'];
				if ( ! empty( $old_post_field ) ) {
					if ( is_array( $old_post_field ) ) {
						if ( ! empty( $old_post_field[0] ) ) {
							$updated_fields = array();
							foreach ( $old_post_field as $field ) {
								$field_details = explode( ':', $field );
								if ( ! empty( $field_details[1] ) ) {
									$updated_fields[ $field_details[0] ] = $field_details[1];
								}
							}
							$old_post_field = $updated_fields;
						}
					}
				}
				$detailed_config['body']['request_value'] = $old_post_field;

				$detailed_config['cron_settings']                           = array();
				$detailed_config['shortcode_settings']                      = array();
				$detailed_config['subsequent_actions']['store_in_database'] = array();

				if ( ! empty( $adv_external_config[ $connection_name ] ) ) {
					$connection_adv_config = $adv_external_config[ $connection_name ];

					$detailed_config['cron_settings']['is_cron_enabled'] = $connection_adv_config[ $connection_name . 'cron' ]['cron_enabled'] ?? '';
					$detailed_config['cron_settings']['date_and_time']   = $connection_adv_config[ $connection_name . 'cron' ]['cron_schedule_initiate'] ?? '';
					$detailed_config['cron_settings']['frequency']       = $connection_adv_config[ $connection_name . 'cron' ]['cron_scheduled_frequency'] ?? '';

					$shortcode_config = $connection_adv_config[ $connection_name . 'shortcode' ];
					$html_config      = $shortcode_config[0]['html'];

					$new_data_keys = array( 'html_code', 'reference_key', 'is_loop' );
					foreach ( $html_config as $index => $details ) {
						$values = array_values( $details );
						$values = array_combine( $new_data_keys, $values );

						$values['is_loop'] = ( 'loop' === $values['is_loop'] ) ? true : false;

						$shortcode_config[0]['html'][ $index ] = $values;
					}

					$detailed_config['shortcode_settings'] = $shortcode_config;

					if ( isset( $connection_adv_config[ $connection_name . 'cron' ]['db_option_name'] ) ) {
						global $wpdb;
						$detailed_config['subsequent_actions']['store_in_database']['table'] = $wpdb->prefix . 'options';

						$columns = array(
							array(
								'option_value' => 'api_response',
								'option_name'  => $connection_adv_config[ $connection_name . 'cron' ]['db_option_name'],
							),
						);
						$detailed_config['subsequent_actions']['store_in_database']['columns'] = $columns;
					}
				}

				$configuration['configuration'] = $detailed_config;

				DB_Utils::update_configuration( $configuration );
			}

			DB_Utils::update_option( 'mo_caw_last_version', Utils::get_version_number() );
		}
	}
}
