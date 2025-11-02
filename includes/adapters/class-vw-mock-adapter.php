<?php
/**
 * Mock courier adapter used during development and testing.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker\Adapters;

use VW_Fraud_Checker\Adapter_Interface;

/**
 * Provides deterministic data for early development without hitting real APIs.
 */
class Mock_Adapter implements Adapter_Interface {
	/**
	 * Return sample courier metrics for a phone number.
	 *
	 * @param string $phone Normalized phone number.
	 *
	 * @return array<string, mixed>
	 */
	public function get_data_by_phone( $phone ) {
		return array(
			'phone'          => $phone,
			'courier'        => 'mock',
			'delivered'      => 3,
			'returned'       => 1,
			'cancelled'      => 0,
			'complete_ratio' => 0.75,
			'cancel_ratio'   => 0.00,
			'updated_at'     => gmdate( 'Y-m-d H:i:s' ),
		);
	}
}
