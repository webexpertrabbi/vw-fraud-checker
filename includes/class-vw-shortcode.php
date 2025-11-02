<?php
/**
 * Shortcode integration for frontend checks.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

/**
 * Register and render the [vw_fraud_checker] shortcode.
 */
class Shortcode {
	/**
	 * Database dependency.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Shortcode tag.
	 */
	const TAG = 'vw_fraud_checker';

	/**
	 * Inject required dependencies.
	 *
	 * @param Database $database Database layer instance.
	 */
	public function __construct( Database $database ) {
		$this->database = $database;
	}

	/**
	 * Register the shortcode with WordPress.
	 */
	public function register() {
		\add_shortcode( self::TAG, array( $this, 'render_shortcode' ) );
	}

	/**
	 * Enqueue public assets if shortcode is present.
	 */
	public function enqueue_assets() {
		if ( ! $this->is_shortcode_used_on_page() ) {
			return;
		}

		\wp_enqueue_style( 'vw-fraud-checker-public', VW_FRAUD_CHECKER_PLUGIN_URL . 'assets/css/public.css', array(), VW_FRAUD_CHECKER_VERSION );
		\wp_enqueue_script( 'vw-fraud-checker-public', VW_FRAUD_CHECKER_PLUGIN_URL . 'assets/js/public.js', array( 'jquery' ), VW_FRAUD_CHECKER_VERSION, true );
		\wp_localize_script(
			'vw-fraud-checker-public',
			'vwFraudChecker',
			array(
				'nonce'    => \wp_create_nonce( 'vw_fraud_checker_nonce' ),
				'endpoints' => array(
					'check' => \rest_url( 'vw/v1/check' ),
				),
			)
		);
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = \shortcode_atts(
			array(
				'title' => \__( 'Check Customer Risk', 'vw-fraud-checker' ),
			),
			$atts,
			self::TAG
		);

		ob_start();
		?>
		<div class="vw-fraud-checker-form" data-shortcode="vw-fraud-checker">
			<h2><?php echo \esc_html( $atts['title'] ); ?></h2>
			<form>
				<label for="vw-fraud-checker-phone"><?php echo \esc_html__( 'Phone Number', 'vw-fraud-checker' ); ?></label>
				<input type="tel" id="vw-fraud-checker-phone" name="phone" placeholder="<?php echo \esc_attr__( 'e.g. +8801XXXXXXXXX', 'vw-fraud-checker' ); ?>" required>
				<button type="submit" class="button button-primary"><?php echo \esc_html__( 'Check Now', 'vw-fraud-checker' ); ?></button>
			</form>
			<div class="vw-fraud-checker-results" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Detect if shortcode exists in global post object.
	 *
	 * @return bool
	 */
	private function is_shortcode_used_on_page() {
		if ( \is_admin() ) {
			return false;
		}

		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return \has_shortcode( $post->post_content, self::TAG );
	}
}
