<?php
/**
 * Core plugin bootstrap.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

use VW_Fraud_Checker\Adapters\Mock_Adapter;
use function load_plugin_textdomain;
use function plugin_basename;

/**
 * Main plugin orchestrator.
 */
class Plugin {
	/**
	 * Holds the singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Loader that maintains and registers all hooks.
	 *
	 * @var Loader
	 */
	private $loader;

	/**
	 * Database layer handler.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Admin area manager.
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Shortcode handler.
	 *
	 * @var Shortcode
	 */
	private $shortcode;

	/**
	 * Courier API adapter manager.
	 *
	 * @var API
	 */
	private $api;

	/**
	 * REST route registrar.
	 *
	 * @var Rest
	 */
	private $rest;

	/**
	 * Cron scheduler.
	 *
	 * @var Cron
	 */
	private $cron;

	/**
	 * Return the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevent direct instantiation.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->initialize_components();
		$this->loader->run();
	}

	/**
	 * Load all required dependency files and instantiate the loader.
	 */
	private function load_dependencies() {
		require_once VW_FRAUD_CHECKER_PLUGIN_DIR . 'includes/helpers.php';

		$this->loader    = new Loader();
		$this->database  = new Database();
		$this->api       = new API();
		$this->admin     = new Admin( $this->database, $this->api );
		$this->shortcode = new Shortcode( $this->database );
		$this->rest      = new Rest( $this->database, $this->api );
		$this->cron      = new Cron( $this->database, $this->api );

		$this->api->register_adapter( 'mock', new Mock_Adapter() );
	}

	/**
	 * Wire core hooks for internationalization, admin, public and cron flows.
	 */
	private function initialize_components() {
		$this->loader->add_action( 'init', $this, 'load_textdomain' );
		$this->loader->add_action( 'rest_api_init', $this->rest, 'register_routes' );

		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Register admin-specific hooks.
	 */
	private function define_admin_hooks() {
		$this->loader->add_action( 'admin_menu', $this->admin, 'register_menu' );
		$this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_assets' );
	}

	/**
	 * Register public-facing hooks.
	 */
	private function define_public_hooks() {
		$this->loader->add_action( 'init', $this->shortcode, 'register' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this->shortcode, 'enqueue_assets' );
	}

	/**
	 * Register cron schedules and hooks.
	 */
	private function define_cron_hooks() {
		$this->loader->add_filter( 'cron_schedules', $this->cron, 'register_custom_schedules' );
		$this->loader->add_action( Cron::EVENT_NAME, $this->cron, 'run_scheduled_refresh' );
	}

	/**
	 * Load plugin textdomain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'vw-fraud-checker', false, dirname( plugin_basename( VW_FRAUD_CHECKER_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Fired on plugin activation.
	 */
	public static function activate() {
		$database = new Database();
		$database->create_tables();

		Cron::schedule_event();
	}

	/**
	 * Fired on plugin deactivation.
	 */
	public static function deactivate() {
		Cron::clear_scheduled_event();
	}
}
