<?php
/**
 * REST API endpoints for VW Fraud Checker.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use function __;
use function current_user_can;
use function register_rest_route;
use function wp_verify_nonce;

/**
 * Register REST routes and callbacks.
 */
class Rest {
	/**
	 * Database dependency.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * API adapter manager.
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
	 * Register the REST routes.
	 */
	public function register_routes() {
		\register_rest_route(
			'vw/v1',
			'/check',
			array(
				'args' => array(
					'phone' => array(
						'required'          => true,
						'sanitize_callback' => __NAMESPACE__ . '\\vw_fraud_checker_sanitize_phone',
					),
					'providers' => array(
						'required' => false,
						'default'  => array(),
					),
				),
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_check' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);
	}

	/**
	 * Ensure the caller is authorized to trigger the endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool|WP_Error
	 */
	public function can_access( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( $nonce && \wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}

		if ( \current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Access denied.', 'vw-fraud-checker' ), array( 'status' => 401 ) );
	}

	/**
	 * Handle fraud check requests.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function handle_check( WP_REST_Request $request ) {
		$phone      = $request->get_param( 'phone' );
		$providers  = (array) $request->get_param( 'providers' );

		if ( empty( $phone ) ) {
			return new WP_Error( 'invalid_phone', __( 'A valid phone number is required.', 'vw-fraud-checker' ), array( 'status' => 400 ) );
		}

		$data = $this->database->get_metrics_by_phone( $phone );

		if ( empty( $data ) ) {
			$api_results = $this->api->fetch_metrics( $phone, $providers );
			$data        = array(
				'providers' => $api_results,
				'cached'    => false,
			);
		} else {
			$data['providers'] = array();
			$data['cached']    = true;
		}

		return $data;
	}
}
