<?php
/**
 * Cron scheduling for background refresh.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

use const HOUR_IN_SECONDS;
use function __;
use function wp_clear_scheduled_hook;
use function wp_next_scheduled;
use function wp_schedule_event;

/**
 * Manage background tasks for data refresh.
 */
class Cron {
	/**
	 * Custom cron event name.
	 */
	const EVENT_NAME = 'vw_fraud_checker_refresh';

	/**
	 * Database dependency.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * API dependency for fetching fresh data.
	 *
	 * @var API
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param Database $database Database layer instance.
	 * @param API      $api      API adapter manager.
	 */
	public function __construct( Database $database, API $api ) {
		$this->database = $database;
		$this->api      = $api;
	}

	/**
	 * Register custom schedules for the plugin.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function register_custom_schedules( $schedules ) {
		$schedules['vw_fraud_checker_12h'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 12 Hours (VW Fraud Checker)', 'vw-fraud-checker' ),
		);

		return $schedules;
	}

	/**
	 * Triggered by the cron event to refresh courier data.
	 */
	public function run_scheduled_refresh() {
		// TODO: Implement sync loop for configured couriers using $this->api.
	}

	/**
	 * Schedule the cron event if it is not already scheduled.
	 */
	public static function schedule_event() {
		if ( \wp_next_scheduled( self::EVENT_NAME ) ) {
			return;
		}

		\wp_schedule_event( time(), 'vw_fraud_checker_12h', self::EVENT_NAME );
	}

	/**
	 * Clear the scheduled event on plugin deactivation.
	 */
	public static function clear_scheduled_event() {
		\wp_clear_scheduled_hook( self::EVENT_NAME );
	}
}
