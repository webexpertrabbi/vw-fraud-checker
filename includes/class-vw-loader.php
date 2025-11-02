<?php
/**
 * Collects and registers WordPress hooks for the plugin.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

use function add_action;
use function add_filter;

/**
 * Lightweight loader patterned after the WordPress plugin boilerplate.
 */
class Loader {
	/**
	 * Stores action hooks to be registered later.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $actions = array();

	/**
	 * Stores filter hooks to be registered later.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $filters = array();

	/**
	 * Add a new action hook to the collection.
	 *
	 * @param string   $hook          WordPress action name.
	 * @param object   $component     Component instance that contains the callback.
	 * @param string   $callback      Callback method name.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Number of arguments the callback accepts.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Add a new filter hook to the collection.
	 *
	 * @param string   $hook          WordPress filter name.
	 * @param object   $component     Component instance that contains the callback.
	 * @param string   $callback      Callback method name.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Number of arguments the callback accepts.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Register all saved actions and filters with WordPress.
	 */
	public function run() {
		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
