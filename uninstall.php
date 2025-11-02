<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package VW_Fraud_Checker
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/class-vw-database.php';

$database = new VW_Fraud_Checker\Database();
$database->drop_tables();

\delete_option( 'vw_fraud_checker_providers' );
