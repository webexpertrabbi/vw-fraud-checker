<?php
/**
 * Database layer for storing courier metrics.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

/**
 * Handles plugin database tables and CRUD helpers.
 */
class Database {
	/**
	 * Return the fully qualified table name for fraud data metrics.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'vw_fraud_data';
	}

	/**
	 * Create plugin tables if they do not exist.
	 */
	public function create_tables() {
		global $wpdb;

		$table_name = $this->get_table_name();
		$charset    = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			phone VARCHAR(20) NOT NULL,
			courier VARCHAR(50) NOT NULL,
			delivered INT UNSIGNED NOT NULL DEFAULT 0,
			returned INT UNSIGNED NOT NULL DEFAULT 0,
			cancelled INT UNSIGNED NOT NULL DEFAULT 0,
			complete_ratio DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
			cancel_ratio DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY phone (phone),
			KEY courier (courier)
		) {$charset};";

		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( $sql );
	}

	/**
	 * Drop plugin tables.
	 */
	public function drop_tables() {
		global $wpdb;

		$table_name = $this->get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Prepare metrics before persisting them.
	 *
	 * @param array<string, mixed> $metrics Raw metrics array.
	 *
	 * @return array<string, mixed>
	 */
	public function prepare_metrics( array $metrics ) {
		$defaults = array(
			'phone'           => '',
			'courier'         => '',
			'delivered'       => 0,
			'returned'        => 0,
			'cancelled'       => 0,
			'complete_ratio'  => 0,
			'cancel_ratio'    => 0,
			'updated_at'      => \current_time( 'mysql' ),
		);

		$metrics = array_merge( $defaults, $metrics );

		$metrics['phone']   = vw_fraud_checker_sanitize_phone( $metrics['phone'] );
		$metrics['courier'] = \sanitize_text_field( $metrics['courier'] );

		$ratios = vw_fraud_checker_calculate_ratios(
			$metrics['delivered'],
			$metrics['returned'],
			$metrics['cancelled']
		);

		$metrics['complete_ratio'] = $ratios['completion_ratio'];
		$metrics['cancel_ratio']    = $ratios['cancel_ratio'];

		if ( empty( $metrics['updated_at'] ) ) {
			$metrics['updated_at'] = \current_time( 'mysql' );
		}

		return $metrics;
	}

	/**
	 * Insert or update a metrics row.
	 *
	 * @param array<string, mixed> $metrics Metrics payload.
	 */
	public function upsert_metrics( array $metrics ) {
		global $wpdb;

		$metrics = $this->prepare_metrics( $metrics );
		$table   = $this->get_table_name();

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE phone = %s AND courier = %s",
				$metrics['phone'],
				$metrics['courier']
			)
		);

		if ( $existing_id ) {
			$wpdb->update(
				$table,
				$metrics,
				array( 'id' => $existing_id ),
				array(
					'%s',
					'%s',
					'%d',
					'%d',
					'%d',
					'%f',
					'%f',
					'%s',
				),
				array( '%d' )
			);
			return;
		}

		$wpdb->insert(
			$table,
			$metrics,
			array( '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%s' )
		);
	}

	/**
	 * Retrieve aggregated stats for a given phone number.
	 *
	 * @param string $phone Phone number to search for.
	 *
	 * @return array<string, mixed>
	 */
	public function get_metrics_by_phone( $phone ) {
		global $wpdb;

		$phone = vw_fraud_checker_sanitize_phone( $phone );
		$table = $this->get_table_name();

		$sql = "SELECT
			SUM(delivered) AS delivered,
			SUM(returned) AS returned,
			SUM(cancelled) AS cancelled,
			MAX(updated_at) AS updated_at
			FROM {$table}
			WHERE phone = %s";

		$prepared = $wpdb->prepare( $sql, $phone );
		$row      = $wpdb->get_row( $prepared, \ARRAY_A );

		if ( empty( $row ) ) {
			return array();
		}

		$delivered = (int) $row['delivered'];
		$returned  = (int) $row['returned'];
		$cancelled = (int) $row['cancelled'];

		$ratios = vw_fraud_checker_calculate_ratios( $delivered, $returned, $cancelled );

		return array_merge(
			array(
				'phone'     => $phone,
				'updated_at' => $row['updated_at'],
			),
			array(
				'delivered'        => $delivered,
				'returned'         => $returned,
				'cancelled'        => $cancelled,
				'total_orders'     => $ratios['total'],
				'complete_ratio'   => $ratios['completion_ratio'],
				'cancel_ratio'     => $ratios['cancel_ratio'],
				'risk_ratio'       => $ratios['risk_ratio'],
			)
		);
	}

	/**
	 * Fetch per-provider rows for a phone number ordered by recency.
	 *
	 * @param string $phone Phone number to search.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_provider_metrics( $phone ) {
		global $wpdb;

		$phone = vw_fraud_checker_sanitize_phone( $phone );
		$table = $this->get_table_name();

		$sql = "SELECT id, phone, courier, delivered, returned, cancelled, complete_ratio, cancel_ratio, updated_at
			FROM {$table}
			WHERE phone = %s
			ORDER BY updated_at DESC";

		$prepared = $wpdb->prepare( $sql, $phone );
		$rows     = $wpdb->get_results( $prepared, \ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$delivered = (int) $row['delivered'];
			$returned  = (int) $row['returned'];
			$cancelled = (int) $row['cancelled'];

			$ratios = vw_fraud_checker_calculate_ratios( $delivered, $returned, $cancelled );

			$row['delivered']        = $delivered;
			$row['returned']         = $returned;
			$row['cancelled']        = $cancelled;
			$row['total_orders']     = $ratios['total'];
			$row['complete_ratio']   = $ratios['completion_ratio'];
			$row['cancel_ratio']     = $ratios['cancel_ratio'];
			$row['risk_ratio']       = $ratios['risk_ratio'];
		}

		return $rows;
	}

	/**
	 * Return summary statistics across all stored records.
	 *
	 * @return array<string, mixed>
	 */
	public function get_summary_stats() {
		global $wpdb;

		$table = $this->get_table_name();
		$sql   = "SELECT COUNT(DISTINCT phone) AS customers,
			SUM(delivered) AS delivered,
			SUM(returned) AS returned,
			SUM(cancelled) AS cancelled
			FROM {$table}";

		$row = $wpdb->get_row( $sql, \ARRAY_A );

		if ( empty( $row ) ) {
			return array(
				'customers'        => 0,
				'delivered'        => 0,
				'returned'         => 0,
				'cancelled'        => 0,
				'total_orders'     => 0,
				'completion_ratio' => 0,
				'cancel_ratio'     => 0,
				'risk_ratio'       => 0,
			);
		}

		$delivered = (int) $row['delivered'];
		$returned  = (int) $row['returned'];
		$cancelled = (int) $row['cancelled'];

		$ratios = vw_fraud_checker_calculate_ratios( $delivered, $returned, $cancelled );

		return array_merge(
			array(
				'customers' => (int) $row['customers'],
			),
			array(
				'delivered'        => $delivered,
				'returned'         => $returned,
				'cancelled'        => $cancelled,
				'total_orders'     => $ratios['total'],
				'completion_ratio' => $ratios['completion_ratio'],
				'cancel_ratio'     => $ratios['cancel_ratio'],
				'risk_ratio'       => $ratios['risk_ratio'],
			)
		);
	}

	/**
	 * Breakdown totals per courier/provider.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_provider_breakdown() {
		global $wpdb;

		$table = $this->get_table_name();
		$sql   = "SELECT courier,
			COUNT(DISTINCT phone) AS customers,
			SUM(delivered) AS delivered,
			SUM(returned) AS returned,
			SUM(cancelled) AS cancelled,
			MAX(updated_at) AS updated_at
			FROM {$table}
			GROUP BY courier
			ORDER BY courier";

		$rows = $wpdb->get_results( $sql, \ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$delivered = (int) $row['delivered'];
			$returned  = (int) $row['returned'];
			$cancelled = (int) $row['cancelled'];
			$ratios    = vw_fraud_checker_calculate_ratios( $delivered, $returned, $cancelled );

			$row['customers']        = (int) $row['customers'];
			$row['delivered']        = $delivered;
			$row['returned']         = $returned;
			$row['cancelled']        = $cancelled;
			$row['total_orders']     = $ratios['total'];
			$row['completion_ratio'] = $ratios['completion_ratio'];
			$row['cancel_ratio']     = $ratios['cancel_ratio'];
			$row['risk_ratio']       = $ratios['risk_ratio'];
		}

		return $rows;
	}

	/**
	 * Top risky customers sorted by return/cancel ratio.
	 *
	 * @param int $limit Number of customers to return.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_top_risk_customers( $limit = 5 ) {
		global $wpdb;

		$table = $this->get_table_name();
		$limit = max( 1, (int) $limit );

		$sql = "SELECT phone,
			SUM(delivered) AS delivered,
			SUM(returned) AS returned,
			SUM(cancelled) AS cancelled,
			MAX(updated_at) AS updated_at
			FROM {$table}
			GROUP BY phone
			HAVING SUM(delivered + returned + cancelled) > 0
			ORDER BY (SUM(returned) + SUM(cancelled)) / SUM(delivered + returned + cancelled) DESC
			LIMIT %d";

		$query = $wpdb->prepare( $sql, $limit );
		$rows  = $wpdb->get_results( $query, \ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$delivered = (int) $row['delivered'];
			$returned  = (int) $row['returned'];
			$cancelled = (int) $row['cancelled'];
			$ratios    = vw_fraud_checker_calculate_ratios( $delivered, $returned, $cancelled );

			$row['delivered']        = $delivered;
			$row['returned']         = $returned;
			$row['cancelled']        = $cancelled;
			$row['total_orders']     = $ratios['total'];
			$row['risk_ratio']       = $ratios['risk_ratio'];
			$row['completion_ratio'] = $ratios['completion_ratio'];
		}

		return $rows;
	}

	/**
	 * Recent activity feed (individual courier records).
	 *
	 * @param int $limit Number of rows to fetch.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_activity( $limit = 5 ) {
		global $wpdb;

		$table = $this->get_table_name();
		$limit = max( 1, (int) $limit );

		$sql = $wpdb->prepare(
			"SELECT id, phone, courier, delivered, returned, cancelled, complete_ratio, cancel_ratio, updated_at
			FROM {$table}
			ORDER BY updated_at DESC
			LIMIT %d",
			$limit
		);

		$rows = $wpdb->get_results( $sql, \ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$delivered = (int) $row['delivered'];
			$returned  = (int) $row['returned'];
			$cancelled = (int) $row['cancelled'];
			$ratios    = vw_fraud_checker_calculate_ratios( $delivered, $returned, $cancelled );

			$row['delivered']        = $delivered;
			$row['returned']         = $returned;
			$row['cancelled']        = $cancelled;
			$row['total_orders']     = $ratios['total'];
			$row['risk_ratio']       = $ratios['risk_ratio'];
		}

		return $rows;
	}

	/**
	 * Delete an individual metrics record by its ID.
	 *
	 * @param int $id Record ID.
	 */
	public function delete_entry( $id ) {
		global $wpdb;

		$id = (int) $id;

		if ( $id <= 0 ) {
			return;
		}

		$wpdb->delete( $this->get_table_name(), array( 'id' => $id ), array( '%d' ) );
	}
}
