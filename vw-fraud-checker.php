<?php
/**
 * Plugin Name:       VW Fraud Checker
 * Plugin URI:        https://vendweave.com/
 * Description:       Detect fake customers by phone number. Check delivery, return and cancel ratios from courier records.
 * Version:           0.1.0
 * Author:            vendweave.com
 * Author URI:        https://vendweave.com/
 * Developer:         webexpertrabbi
 * Developer URI:     https://webexpertrabbi.com/
 * Text Domain:       vw-fraud-checker
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'VW_FRAUD_CHECKER_VERSION' ) ) {
	define( 'VW_FRAUD_CHECKER_VERSION', '0.1.0' );
}

define( 'VW_FRAUD_CHECKER_PLUGIN_FILE', __FILE__ );
define( 'VW_FRAUD_CHECKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VW_FRAUD_CHECKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	require_once VW_FRAUD_CHECKER_PLUGIN_DIR . 'includes/class-vw-autoloader.php';

VW_Fraud_Checker\Autoloader::register();

/**
 * Begins execution of the plugin.
 */
function vw_fraud_checker_bootstrap() {
	return VW_Fraud_Checker\Plugin::get_instance();
}

register_activation_hook( __FILE__, array( 'VW_Fraud_Checker\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VW_Fraud_Checker\\Plugin', 'deactivate' ) );

vw_fraud_checker_bootstrap();
