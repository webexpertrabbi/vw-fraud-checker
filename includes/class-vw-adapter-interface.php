<?php
/**
 * Interface for courier adapters.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

/**
 * Every courier adapter must implement this contract.
 */
interface Adapter_Interface {
	/**
	 * Retrieve courier data for a phone number.
	 *
	 * @param string $phone Phone number in normalized format.
	 *
	 * @return array<string, mixed>
	 */
	public function get_data_by_phone( $phone );
}
