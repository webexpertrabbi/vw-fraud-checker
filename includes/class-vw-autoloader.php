<?php
/**
 * Autoloader for VW Fraud Checker classes.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

/**
 * Simple PSR-4-like autoloader that maps plugin classes to files inside includes/.
 */
class Autoloader {
	/**
	 * Register the autoloader with SPL.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Load a class file when a namespaced class is requested.
	 *
	 * @param string $class Class name that should be loaded.
	 */
	private static function autoload( $class ) {
		$namespace = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $namespace ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $namespace ) );
		$relative_class = str_replace( array( '\\', '_' ), array( '/', '-' ), $relative_class );

		$parts     = explode( '/', $relative_class );
		$filename  = array_pop( $parts );
		$subpath   = '';

		if ( ! empty( $parts ) ) {
			$subpath = implode( '/', array_map( 'strtolower', $parts ) ) . '/';
		}

		$filename = strtolower( $filename );

		$path = VW_FRAUD_CHECKER_PLUGIN_DIR . 'includes/' . $subpath . 'class-vw-' . $filename . '.php';

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
