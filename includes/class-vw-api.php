<?php
/**
 * Courier API integration layer.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

/**
 * Adapter-based integration with courier providers.
 */
class API {
	/**
	 * Registered adapters.
	 *
	 * @var array<string, Adapter_Interface>
	 */
	private $adapters = array();

	/**
	 * Provider settings loaded from WP options.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $provider_settings = array();

	/**
	 * Register a courier adapter.
	 *
	 * @param string            $slug     Unique identifier for the adapter.
	 * @param Adapter_Interface $adapter  Adapter instance implementing required contract.
	 */
	public function register_adapter( $slug, Adapter_Interface $adapter ) {
		$this->adapters[ $slug ] = $adapter;
	}

	/**
	 * Inject provider settings from admin configuration.
	 *
	 * @param array<string, array<string, mixed>> $settings Settings keyed by provider slug.
	 */
	public function set_provider_settings( array $settings ) {
		$this->provider_settings = $settings;
	}

	/**
	 * Retrieve provider settings for a specific adapter.
	 *
	 * @param string $slug Provider slug.
	 *
	 * @return array<string, mixed>
	 */
	public function get_provider_settings( $slug ) {
		return isset( $this->provider_settings[ $slug ] ) ? $this->provider_settings[ $slug ] : array();
	}

	/**
	 * Determine if a provider is enabled.
	 *
	 * @param string $slug Provider slug.
	 *
	 * @return bool
	 */
	public function is_provider_enabled( $slug ) {
		$settings = $this->get_provider_settings( $slug );

		return ! empty( $settings['enabled'] );
	}

	/**
	 * Determine if an adapter exists.
	 *
	 * @param string $slug Adapter slug.
	 *
	 * @return bool
	 */
	public function has_adapter( $slug ) {
		return isset( $this->adapters[ $slug ] );
	}

	/**
	 * Fetch metrics for a phone number across one or many adapters.
	 *
	 * @param string $phone Phone number to lookup.
	 * @param array<string> $providers Optional set of provider slugs to restrict the lookup.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function fetch_metrics( $phone, array $providers = array() ) {
		$results = array();

		$phone = vw_fraud_checker_sanitize_phone( $phone );

		foreach ( $this->resolve_adapters( $providers ) as $slug => $adapter ) {
			try {
				$results[ $slug ] = $adapter->get_data_by_phone( $phone );
			} catch ( \Exception $exception ) {
				// TODO: Connect with logger once implemented.
			}
		}

		return $results;
	}

	/**
	 * Resolve adapters based on requested providers.
	 *
	 * @param array<string> $providers Providers requested.
	 *
	 * @return array<string, Adapter_Interface>
	 */
	private function resolve_adapters( array $providers ) {
		if ( empty( $providers ) ) {
			return array_filter(
				$this->adapters,
				function ( $slug ) {
					return $this->is_provider_enabled( $slug );
				},
				ARRAY_FILTER_USE_KEY
			);
		}

		return array_filter(
			$this->adapters,
			function ( $key ) use ( $providers ) {
				return in_array( $key, $providers, true ) && $this->is_provider_enabled( $key );
			},
			ARRAY_FILTER_USE_KEY
		);
	}
}
