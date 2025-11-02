<?php
/**
 * General helper functions for VW Fraud Checker.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

/**
 * Normalize and sanitize a phone number into E.164 friendly format.
 *
 * @param string $phone Raw phone string.
 *
 * @return string
 */
function vw_fraud_checker_sanitize_phone( $phone ) {
	$phone = \preg_replace( '/[^0-9+]/', '', $phone );

	if ( empty( $phone ) ) {
		return '';
	}

	if ( '+' !== $phone[0] ) {
		$phone = '+' . ltrim( $phone, '0' );
	}

	return $phone;
}

/**
 * Retrieve a plugin option with fallback.
 *
 * @param string $key     Option key suffix.
 * @param mixed  $default Default value if option is empty.
 *
 * @return mixed
 */
function vw_fraud_checker_get_option( $key, $default = '' ) {
	$option = \get_option( 'vw_fraud_checker_' . $key, $default );

	return false === $option ? $default : $option;
}

/**
 * Provide metadata for all supported courier providers.
 *
 * @return array<string, array<string, mixed>>
 */
function vw_fraud_checker_get_supported_providers() {
	return array(
		'mock' => array(
			'label'       => \__( 'Mock Provider', 'vw-fraud-checker' ),
			'description' => \__( 'Synthetic data source for demos, QA and fallback testing.', 'vw-fraud-checker' ),
			'fields'      => array(
				'enabled' => array(
					'type'        => 'toggle',
					'label'       => \__( 'Enable Mock Provider', 'vw-fraud-checker' ),
					'description' => \__( 'Keep enabled to showcase the checker before real courier APIs are connected.', 'vw-fraud-checker' ),
					'default'     => true,
				),
				'api_key' => array(
					'type'        => 'text',
					'label'       => \__( 'API Key', 'vw-fraud-checker' ),
					'description' => \__( 'Optional token used to emulate authentication.', 'vw-fraud-checker' ),
					'default'     => '',
				),
			),
		),
		'pathao' => array(
			'label'       => \__( 'Pathao', 'vw-fraud-checker' ),
			'description' => \__( 'Connect to the Pathao Merchant API to pull delivery and return history.', 'vw-fraud-checker' ),
			'fields'      => array(
				'enabled' => array(
					'type'        => 'toggle',
					'label'       => \__( 'Enable Pathao Sync', 'vw-fraud-checker' ),
					'description' => \__( 'Turn on when your Pathao API credentials are valid.', 'vw-fraud-checker' ),
					'default'     => false,
				),
				'client_id' => array(
					'type'        => 'text',
					'label'       => \__( 'Client ID', 'vw-fraud-checker' ),
					'description' => \__( 'Provided by Pathao merchant support.', 'vw-fraud-checker' ),
					'default'     => '',
				),
				'client_secret' => array(
					'type'        => 'password',
					'label'       => \__( 'Client Secret', 'vw-fraud-checker' ),
					'description' => \__( 'Keep this value private.', 'vw-fraud-checker' ),
					'default'     => '',
				),
				'username' => array(
					'type'        => 'text',
					'label'       => \__( 'Username / Merchant ID', 'vw-fraud-checker' ),
					'description' => \__( 'Account username used for Pathao login.', 'vw-fraud-checker' ),
					'default'     => '',
				),
				'password' => array(
					'type'        => 'password',
					'label'       => \__( 'Password', 'vw-fraud-checker' ),
					'description' => \__( 'Application password or API password.', 'vw-fraud-checker' ),
					'default'     => '',
				),
			),
		),
		'steadfast' => array(
			'label'       => \__( 'Steadfast', 'vw-fraud-checker' ),
			'description' => \__( 'Integrate Steadfast courier performance metrics.', 'vw-fraud-checker' ),
			'fields'      => array(
				'enabled' => array(
					'type'        => 'toggle',
					'label'       => \__( 'Enable Steadfast Sync', 'vw-fraud-checker' ),
					'description' => \__( 'Fetch Steadfast data via API.', 'vw-fraud-checker' ),
					'default'     => false,
				),
				'api_key' => array(
					'type'        => 'text',
					'label'       => \__( 'API Key', 'vw-fraud-checker' ),
					'description' => \__( 'Generated from your Steadfast merchant dashboard.', 'vw-fraud-checker' ),
					'default'     => '',
				),
				'api_secret' => array(
					'type'        => 'password',
					'label'       => \__( 'API Secret', 'vw-fraud-checker' ),
					'description' => \__( 'Use the secret provided alongside the API key.', 'vw-fraud-checker' ),
					'default'     => '',
				),
				'base_url' => array(
					'type'        => 'url',
					'label'       => \__( 'Base URL', 'vw-fraud-checker' ),
					'description' => \__( 'Override API endpoint (leave blank for default).', 'vw-fraud-checker' ),
					'default'     => '',
				),
			),
		),
		'redx' => array(
			'label'       => \__( 'REDX', 'vw-fraud-checker' ),
			'description' => \__( 'Connect REDX courier reports to enrich fraud scoring.', 'vw-fraud-checker' ),
			'fields'      => array(
				'enabled' => array(
					'type'        => 'toggle',
					'label'       => \__( 'Enable REDX Sync', 'vw-fraud-checker' ),
					'description' => \__( 'Collect delivery status from REDX APIs.', 'vw-fraud-checker' ),
					'default'     => false,
				),
				'api_key' => array(
					'type'        => 'text',
					'label'       => \__( 'API Key', 'vw-fraud-checker' ),
					'description' => \__( 'Provided by REDX operations team.', 'vw-fraud-checker' ),
					'default'     => '',
				),
				'api_secret' => array(
					'type'        => 'password',
					'label'       => \__( 'API Secret', 'vw-fraud-checker' ),
					'description' => \__( 'Secret token shared at onboarding.', 'vw-fraud-checker' ),
					'default'     => '',
				),
				'warehouse_code' => array(
					'type'        => 'text',
					'label'       => \__( 'Warehouse / Store Code', 'vw-fraud-checker' ),
					'description' => \__( 'Optional: enforce queries by warehouse.', 'vw-fraud-checker' ),
					'default'     => '',
				),
			),
		),
	);
}

/**
 * Return default option values for each provider field.
 *
 * @return array<string, array<string, mixed>>
 */
function vw_fraud_checker_get_provider_defaults() {
	$definitions = vw_fraud_checker_get_supported_providers();
	$defaults    = array();

	foreach ( $definitions as $slug => $provider ) {
		$defaults[ $slug ] = array();

		foreach ( $provider['fields'] as $field_key => $field ) {
			if ( array_key_exists( 'default', $field ) ) {
				$defaults[ $slug ][ $field_key ] = $field['default'];
				continue;
			}

			$defaults[ $slug ][ $field_key ] = ( 'toggle' === $field['type'] ) ? false : '';
		}
	}

	return $defaults;
}

/**
 * Retrieve provider settings merged with defaults.
 *
 * @return array<string, array<string, mixed>>
 */
function vw_fraud_checker_get_provider_settings() {
	$stored   = vw_fraud_checker_get_option( 'providers', array() );
	$defaults = vw_fraud_checker_get_provider_defaults();
	$output   = $defaults;

	if ( ! is_array( $stored ) ) {
		return $output;
	}

	foreach ( $stored as $slug => $fields ) {
		if ( ! isset( $defaults[ $slug ] ) || ! is_array( $fields ) ) {
			continue;
		}

		foreach ( $defaults[ $slug ] as $field_key => $default_value ) {
			if ( array_key_exists( $field_key, $fields ) ) {
				$output[ $slug ][ $field_key ] = $fields[ $field_key ];
			}
		}
	}

	return $output;
}

/**
 * Return an array of active provider slugs.
 *
 * @param array<string, array<string, mixed>>|null $settings Optional settings array to inspect.
 *
 * @return array<int, string>
 */
function vw_fraud_checker_get_enabled_providers( $settings = null ) {
	$settings = null === $settings ? vw_fraud_checker_get_provider_settings() : $settings;
	$enabled  = array();

	foreach ( $settings as $slug => $fields ) {
		if ( ! empty( $fields['enabled'] ) ) {
			$enabled[] = $slug;
		}
	}

	return $enabled;
}

/**
 * Calculate risk ratio and completion ratio.
 *
 * @param int $delivered Delivered orders.
 * @param int $returned  Returned orders.
 * @param int $cancelled Cancelled orders.
 *
 * @return array<string, float>
 */
function vw_fraud_checker_calculate_ratios( $delivered, $returned, $cancelled ) {
	$delivered = (int) $delivered;
	$returned  = (int) $returned;
	$cancelled = (int) $cancelled;
	$total     = $delivered + $returned + $cancelled;

	if ( $total <= 0 ) {
		return array(
			'total'            => 0,
			'completion_ratio' => 0.0,
			'cancel_ratio'     => 0.0,
			'risk_ratio'       => 0.0,
		);
	}

	$completion = $delivered / $total;
	$cancel     = $cancelled / $total;
	$risk       = ( $returned + $cancelled ) / $total;

	return array(
		'total'            => $total,
			'completion_ratio' => round( $completion, 4 ),
			'cancel_ratio'     => round( $cancel, 4 ),
			'risk_ratio'       => round( $risk, 4 ),
	);
}

/**
 * Convert ratio to a human friendly percentage string.
 *
 * @param float $ratio      Ratio value between 0 and 1.
 * @param int   $precision  Decimal precision.
 *
 * @return string
 */
function vw_fraud_checker_format_percentage( $ratio, $precision = 1 ) {
	$ratio = is_numeric( $ratio ) ? (float) $ratio : 0.0;
	return \number_format_i18n( $ratio * 100, $precision ) . '%';
}
