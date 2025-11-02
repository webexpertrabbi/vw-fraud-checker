<?php
/**
 * Admin dashboard functionality.
 *
 * @package VW_Fraud_Checker
 */

namespace VW_Fraud_Checker;

use function add_menu_page;
use function add_query_arg;
use function add_submenu_page;
use function admin_url;
use function checked;
use function check_admin_referer;
use function current_time;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_js;
use function esc_url;
use function esc_url_raw;
use function get_option;
use function mysql2date;
use function number_format_i18n;
use function register_setting;
use function sanitize_key;
use function sanitize_text_field;
use function settings_fields;
use function submit_button;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_kses_post;
use function wp_nonce_field;
use function wp_nonce_url;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_verify_nonce;
use function __;

/**
 * Handles admin menu, settings and data management UI.
 */
class Admin {
	/**
	 * Slug for the top-level admin menu.
	 */
	const MENU_SLUG = 'vw-fraud-checker';

	/**
	 * Import action identifier.
	 */
	const ACTION_IMPORT = 'vw_fraud_checker_import_metrics';

	/**
	 * Delete action identifier.
	 */
	const ACTION_DELETE = 'vw_fraud_checker_delete_metric';

	/**
	 * Database dependency.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * API dependency.
	 *
	 * @var API
	 */
	private $api;

	/**
	 * Setup dependencies.
	 *
	 * @param Database $database Database layer instance.
	 * @param API      $api      API adapter manager.
	 */
	public function __construct( Database $database, API $api ) {
		$this->database = $database;
		$this->api      = $api;
	}

	/**
	 * Register the plugin menu in the WordPress admin.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Fraud Checker', 'vw-fraud-checker' ),
			__( 'Fraud Checker', 'vw-fraud-checker' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' ),
			'dashicons-shield'
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'vw-fraud-checker' ),
			__( 'Dashboard', 'vw-fraud-checker' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Customer Lookup', 'vw-fraud-checker' ),
			__( 'Customer Lookup', 'vw-fraud-checker' ),
			'manage_options',
			self::MENU_SLUG . '-lookup',
			array( $this, 'render_lookup_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Courier Providers', 'vw-fraud-checker' ),
			__( 'Courier Providers', 'vw-fraud-checker' ),
			'manage_options',
			self::MENU_SLUG . '-providers',
			array( $this, 'render_providers_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Data Manager', 'vw-fraud-checker' ),
			__( 'Data Manager', 'vw-fraud-checker' ),
			'manage_options',
			self::MENU_SLUG . '-data',
			array( $this, 'render_data_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'vw_fraud_checker_providers_group',
			'vw_fraud_checker_providers',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_providers' ),
				'default'           => vw_fraud_checker_get_provider_defaults(),
			)
		);
	}

	/**
	 * Enqueue admin assets for plugin screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'vw-fraud-checker-admin', VW_FRAUD_CHECKER_PLUGIN_URL . 'assets/css/admin.css', array(), VW_FRAUD_CHECKER_VERSION );
		wp_enqueue_script( 'vw-fraud-checker-admin', VW_FRAUD_CHECKER_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), VW_FRAUD_CHECKER_VERSION, true );
	}

	/**
	 * Display success and error notices.
	 */
	public function render_notices() {
		if ( ! isset( $_GET['vwfc_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$code = sanitize_text_field( wp_unslash( $_GET['vwfc_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$data = array();

		if ( isset( $_GET['count'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$data['count'] = (int) $_GET['count'];
		}

		if ( isset( $_GET['providers'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$data['providers'] = array_map( 'sanitize_text_field', (array) $_GET['providers'] );
		}

		if ( isset( $_GET['phone'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$data['phone'] = vw_fraud_checker_sanitize_phone( wp_unslash( $_GET['phone'] ) );
		}

		$message = $this->get_notice_message( $code, $data );

		if ( empty( $message ) ) {
			return;
		}

		$classes = in_array( $code, array( 'error', 'invalid' ), true ) ? 'notice notice-error' : 'notice notice-success';

		echo '<div class="' . esc_attr( $classes ) . '"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Render the dashboard overview page.
	 */
	public function render_dashboard_page() {
		$this->ensure_capability();

		$summary            = $this->database->get_summary_stats();
		$provider_breakdown = $this->database->get_provider_breakdown();
		$top_risks          = $this->database->get_top_risk_customers( 6 );
		$recent_activity    = $this->database->get_recent_activity( 8 );

		?>
		<div class="wrap vwfc-wrap">
			<h1 class="vwfc-title"><?php echo esc_html__( 'VW Fraud Checker Dashboard', 'vw-fraud-checker' ); ?></h1>
			<p class="vwfc-subtitle"><?php echo esc_html__( 'Track risky behaviour, compare courier partners and stay ahead of fraud attempts.', 'vw-fraud-checker' ); ?></p>

			<div class="vwfc-cards">
				<div class="vwfc-card">
					<span class="vwfc-card-label"><?php echo esc_html__( 'Total Customers', 'vw-fraud-checker' ); ?></span>
					<strong class="vwfc-card-value"><?php echo esc_html( number_format_i18n( $summary['customers'] ) ); ?></strong>
				</div>
				<div class="vwfc-card">
					<span class="vwfc-card-label"><?php echo esc_html__( 'Orders Analysed', 'vw-fraud-checker' ); ?></span>
					<strong class="vwfc-card-value"><?php echo esc_html( number_format_i18n( $summary['total_orders'] ) ); ?></strong>
				</div>
				<div class="vwfc-card">
					<span class="vwfc-card-label"><?php echo esc_html__( 'Completion Rate', 'vw-fraud-checker' ); ?></span>
					<strong class="vwfc-card-value"><?php echo esc_html( vw_fraud_checker_format_percentage( $summary['completion_ratio'] ) ); ?></strong>
				</div>
				<div class="vwfc-card">
					<span class="vwfc-card-label"><?php echo esc_html__( 'Risk Ratio', 'vw-fraud-checker' ); ?></span>
					<strong class="vwfc-card-value vwfc-card-value--danger"><?php echo esc_html( vw_fraud_checker_format_percentage( $summary['risk_ratio'] ) ); ?></strong>
				</div>
			</div>

			<div class="vwfc-panels">
				<div class="vwfc-panel">
					<h2><?php echo esc_html__( 'Top Risky Customers', 'vw-fraud-checker' ); ?></h2>
					<?php if ( empty( $top_risks ) ) : ?>
						<p><?php echo esc_html__( 'No customer data found yet. Add courier records or refresh from providers.', 'vw-fraud-checker' ); ?></p>
					<?php else : ?>
						<table class="vwfc-table">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Phone', 'vw-fraud-checker' ); ?></th>
									<th><?php echo esc_html__( 'Orders', 'vw-fraud-checker' ); ?></th>
									<th><?php echo esc_html__( 'Risk', 'vw-fraud-checker' ); ?></th>
									<th><?php echo esc_html__( 'Completion', 'vw-fraud-checker' ); ?></th>
									<th><?php echo esc_html__( 'Updated', 'vw-fraud-checker' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $top_risks as $row ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG . '-lookup', 'phone' => rawurlencode( $row['phone'] ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $row['phone'] ); ?></a></td>
									<td><?php echo esc_html( number_format_i18n( $row['total_orders'] ) ); ?></td>
									<td><span class="vwfc-badge vwfc-badge--danger"><?php echo esc_html( vw_fraud_checker_format_percentage( $row['risk_ratio'], 2 ) ); ?></span></td>
									<td><?php echo esc_html( vw_fraud_checker_format_percentage( $row['completion_ratio'], 2 ) ); ?></td>
									<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['updated_at'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<div class="vwfc-panel">
					<h2><?php echo esc_html__( 'Provider Performance', 'vw-fraud-checker' ); ?></h2>
					<?php if ( empty( $provider_breakdown ) ) : ?>
						<p><?php echo esc_html__( 'Connect courier APIs to populate this view.', 'vw-fraud-checker' ); ?></p>
					<?php else : ?>
						<table class="vwfc-table">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Courier', 'vw-fraud-checker' ); ?></th>
									<th><?php echo esc_html__( 'Customers', 'vw-fraud-checker' ); ?></th>
									<th><?php echo esc_html__( 'Orders', 'vw-fraud-checker' ); ?></th>
									<th><?php echo esc_html__( 'Risk', 'vw-fraud-checker' ); ?></th>
									<th><?php echo esc_html__( 'Updated', 'vw-fraud-checker' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $provider_breakdown as $row ) : ?>
								<tr>
									<td><?php echo esc_html( ucwords( $row['courier'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row['customers'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row['total_orders'] ) ); ?></td>
									<td><?php echo esc_html( vw_fraud_checker_format_percentage( $row['risk_ratio'], 2 ) ); ?></td>
									<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $row['updated_at'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>

			<div class="vwfc-panel">
				<h2><?php echo esc_html__( 'Recent Activity', 'vw-fraud-checker' ); ?></h2>
				<?php if ( empty( $recent_activity ) ) : ?>
					<p><?php echo esc_html__( 'No courier updates recorded yet.', 'vw-fraud-checker' ); ?></p>
				<?php else : ?>
					<ul class="vwfc-activity">
					<?php foreach ( $recent_activity as $row ) : ?>
						<li>
							<strong><?php echo esc_html( ucwords( $row['courier'] ) ); ?></strong>
							<span><?php echo esc_html( $row['phone'] ); ?></span>
							<span class="vwfc-activity-meta"><?php echo esc_html( vw_fraud_checker_format_percentage( $row['risk_ratio'], 2 ) ); ?> · <?php echo esc_html( mysql2date( get_option( 'time_format' ), $row['updated_at'] ) ); ?></span>
						</li>
					<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the lookup page that allows manual fraud checks.
	 */
	public function render_lookup_page() {
		$this->ensure_capability();

		$definitions = vw_fraud_checker_get_supported_providers();
		$settings    = vw_fraud_checker_get_provider_settings();
		$this->api->set_provider_settings( $settings );

		$raw_phone    = isset( $_GET['phone'] ) ? sanitize_text_field( wp_unslash( $_GET['phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$phone        = $raw_phone ? vw_fraud_checker_sanitize_phone( $raw_phone ) : '';
		$summary      = array();
		$providers    = array();
		$refresh_info = array();

		if ( $phone ) {
			if ( isset( $_GET['refresh'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'vwfc_refresh_phone' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$refresh_info = $this->refresh_phone_from_providers( $phone );
				$this->redirect_with_notice( self::MENU_SLUG . '-lookup', 'refreshed', array(
					'count'     => $refresh_info['count'],
					'providers' => implode( ',', $refresh_info['providers'] ),
					'phone'     => rawurlencode( $phone ),
				) );
			}

			$summary   = $this->database->get_metrics_by_phone( $phone );
			$providers = $this->database->get_provider_metrics( $phone );
		}

		?>
		<div class="wrap vwfc-wrap">
			<h1 class="vwfc-title"><?php echo esc_html__( 'Customer Lookup', 'vw-fraud-checker' ); ?></h1>
			<p class="vwfc-subtitle"><?php echo esc_html__( 'Search by phone number to review delivery history across all enabled couriers.', 'vw-fraud-checker' ); ?></p>

			<form method="get" class="vwfc-form vwfc-form--lookup">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG . '-lookup' ); ?>">
				<label for="vwfc-phone" class="screen-reader-text"><?php echo esc_html__( 'Phone Number', 'vw-fraud-checker' ); ?></label>
				<input type="tel" id="vwfc-phone" name="phone" value="<?php echo esc_attr( $raw_phone ); ?>" placeholder="<?php echo esc_attr__( '+8801XXXXXXXXX', 'vw-fraud-checker' ); ?>" required>
				<button type="submit" class="button button-primary"><?php echo esc_html__( 'Search', 'vw-fraud-checker' ); ?></button>
				<?php if ( $phone && ! empty( vw_fraud_checker_get_enabled_providers( $settings ) ) ) : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => self::MENU_SLUG . '-lookup', 'phone' => rawurlencode( $phone ), 'refresh' => 1 ), admin_url( 'admin.php' ) ), 'vwfc_refresh_phone' ) ); ?>"><?php echo esc_html__( 'Refresh from Providers', 'vw-fraud-checker' ); ?></a>
				<?php endif; ?>
			</form>

			<?php if ( $phone && empty( $summary ) ) : ?>
				<p class="notice notice-warning"><strong><?php echo esc_html__( 'No cached records found.', 'vw-fraud-checker' ); ?></strong> <?php echo esc_html__( 'Try importing courier data or refreshing from providers.', 'vw-fraud-checker' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $summary ) ) : ?>
				<div class="vwfc-lookup-summary">
					<div class="vwfc-card">
						<span class="vwfc-card-label"><?php echo esc_html__( 'Delivered', 'vw-fraud-checker' ); ?></span>
						<strong class="vwfc-card-value"><?php echo esc_html( number_format_i18n( $summary['delivered'] ) ); ?></strong>
					</div>
					<div class="vwfc-card">
						<span class="vwfc-card-label"><?php echo esc_html__( 'Returned', 'vw-fraud-checker' ); ?></span>
						<strong class="vwfc-card-value"><?php echo esc_html( number_format_i18n( $summary['returned'] ) ); ?></strong>
					</div>
					<div class="vwfc-card">
						<span class="vwfc-card-label"><?php echo esc_html__( 'Cancelled', 'vw-fraud-checker' ); ?></span>
						<strong class="vwfc-card-value"><?php echo esc_html( number_format_i18n( $summary['cancelled'] ) ); ?></strong>
					</div>
					<div class="vwfc-card">
						<span class="vwfc-card-label"><?php echo esc_html__( 'Risk Ratio', 'vw-fraud-checker' ); ?></span>
						<strong class="vwfc-card-value vwfc-card-value--danger"><?php echo esc_html( vw_fraud_checker_format_percentage( $summary['risk_ratio'], 2 ) ); ?></strong>
					</div>
				</div>

				<h2><?php echo esc_html__( 'Courier Breakdown', 'vw-fraud-checker' ); ?></h2>
				<table class="vwfc-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Courier', 'vw-fraud-checker' ); ?></th>
							<th><?php echo esc_html__( 'Delivered', 'vw-fraud-checker' ); ?></th>
							<th><?php echo esc_html__( 'Returned', 'vw-fraud-checker' ); ?></th>
							<th><?php echo esc_html__( 'Cancelled', 'vw-fraud-checker' ); ?></th>
							<th><?php echo esc_html__( 'Risk', 'vw-fraud-checker' ); ?></th>
							<th><?php echo esc_html__( 'Updated', 'vw-fraud-checker' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $providers as $row ) : ?>
						<tr>
							<td><?php echo esc_html( ucwords( $row['courier'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $row['delivered'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $row['returned'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $row['cancelled'] ) ); ?></td>
							<td><?php echo esc_html( vw_fraud_checker_format_percentage( $row['risk_ratio'], 2 ) ); ?></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $row['updated_at'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<div class="vwfc-panel">
				<h2><?php echo esc_html__( 'Enabled Providers', 'vw-fraud-checker' ); ?></h2>
				<ul class="vwfc-provider-list">
				<?php foreach ( $definitions as $slug => $provider ) :
					$enabled = ! empty( $settings[ $slug ]['enabled'] );
					?>
					<li class="<?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>">
						<span class="vwfc-provider-name"><?php echo esc_html( $provider['label'] ); ?></span>
						<span class="vwfc-provider-status"><?php echo esc_html( $enabled ? __( 'Enabled', 'vw-fraud-checker' ) : __( 'Disabled', 'vw-fraud-checker' ) ); ?></span>
					</li>
				<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the courier providers configuration page.
	 */
	public function render_providers_page() {
		$this->ensure_capability();

		$definitions = vw_fraud_checker_get_supported_providers();
		$settings    = vw_fraud_checker_get_provider_settings();

		?>
		<div class="wrap vwfc-wrap">
			<h1 class="vwfc-title"><?php echo esc_html__( 'Courier Provider Integrations', 'vw-fraud-checker' ); ?></h1>
			<p class="vwfc-subtitle"><?php echo esc_html__( 'Store API credentials for each logistics partner. Only enabled providers will contribute live data.', 'vw-fraud-checker' ); ?></p>

			<form method="post" action="options.php" class="vwfc-provider-form">
				<?php settings_fields( 'vw_fraud_checker_providers_group' ); ?>
				<div class="vwfc-provider-grid">
				<?php foreach ( $definitions as $slug => $provider ) :
					$values      = isset( $settings[ $slug ] ) ? $settings[ $slug ] : array();
					$is_enabled  = ! empty( $values['enabled'] );
					$card_classes = 'vwfc-provider-card' . ( $is_enabled ? ' is-enabled' : '' );
					?>
					<section class="<?php echo esc_attr( $card_classes ); ?>">
						<header class="vwfc-provider-card__header">
							<h2><?php echo esc_html( $provider['label'] ); ?></h2>
							<span class="vwfc-provider-card__status <?php echo $is_enabled ? 'is-enabled' : 'is-disabled'; ?>">
								<?php echo esc_html( $is_enabled ? __( 'Enabled', 'vw-fraud-checker' ) : __( 'Disabled', 'vw-fraud-checker' ) ); ?>
							</span>
						</header>
						<p class="description"><?php echo esc_html( $provider['description'] ); ?></p>

						<div class="vwfc-provider-fields">
						<?php foreach ( $provider['fields'] as $field_key => $field ) :
							$field_id   = 'vwfc-' . $slug . '-' . $field_key;
							$field_name = 'vw_fraud_checker_providers[' . $slug . '][' . $field_key . ']';
							$value      = isset( $values[ $field_key ] ) ? $values[ $field_key ] : ( isset( $field['default'] ) ? $field['default'] : '' );

							if ( 'toggle' === $field['type'] ) :
								?>
								<div class="vwfc-field vwfc-field--toggle">
									<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="0">
									<label for="<?php echo esc_attr( $field_id ); ?>">
										<input type="checkbox" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="1" <?php checked( ! empty( $value ) ); ?>>
										<span><?php echo esc_html( $field['label'] ); ?></span>
									</label>
									<?php if ( ! empty( $field['description'] ) ) : ?>
										<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
									<?php endif; ?>
								</div>
							<?php else : ?>
								<div class="vwfc-field">
									<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
									<input type="<?php echo 'password' === $field['type'] ? 'password' : ( 'url' === $field['type'] ? 'url' : 'text' ); ?>" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $value ); ?>" autocomplete="off">
									<?php if ( ! empty( $field['description'] ) ) : ?>
										<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
				</div>
				<?php submit_button( __( 'Save Provider Settings', 'vw-fraud-checker' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render data import and manual entry page.
	 */
	public function render_data_page() {
		$this->ensure_capability();

		$definitions = vw_fraud_checker_get_supported_providers();
		$recent      = $this->database->get_recent_activity( 12 );

		?>
		<div class="wrap vwfc-wrap">
			<h1 class="vwfc-title"><?php echo esc_html__( 'Data Manager', 'vw-fraud-checker' ); ?></h1>
			<p class="vwfc-subtitle"><?php echo esc_html__( 'Add manual courier outcomes or clean up stale entries. CSV import support is coming soon.', 'vw-fraud-checker' ); ?></p>

			<div class="vwfc-panel">
				<h2><?php echo esc_html__( 'Add Courier Metrics', 'vw-fraud-checker' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vwfc-form">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_IMPORT ); ?>">
					<?php wp_nonce_field( self::ACTION_IMPORT ); ?>

					<div class="vwfc-form-grid">
						<div class="vwfc-field">
							<label for="vwfc-import-phone"><?php echo esc_html__( 'Phone Number', 'vw-fraud-checker' ); ?></label>
							<input type="tel" id="vwfc-import-phone" name="phone" placeholder="<?php echo esc_attr__( '+8801XXXXXXXXX', 'vw-fraud-checker' ); ?>" required>
						</div>
						<div class="vwfc-field">
							<label for="vwfc-import-provider"><?php echo esc_html__( 'Courier Provider', 'vw-fraud-checker' ); ?></label>
							<select id="vwfc-import-provider" name="courier" required>
								<option value=""><?php echo esc_html__( 'Select provider', 'vw-fraud-checker' ); ?></option>
								<?php foreach ( $definitions as $slug => $provider ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $provider['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="vwfc-field">
							<label for="vwfc-import-delivered"><?php echo esc_html__( 'Delivered', 'vw-fraud-checker' ); ?></label>
							<input type="number" min="0" id="vwfc-import-delivered" name="delivered" value="0">
						</div>
						<div class="vwfc-field">
							<label for="vwfc-import-returned"><?php echo esc_html__( 'Returned', 'vw-fraud-checker' ); ?></label>
							<input type="number" min="0" id="vwfc-import-returned" name="returned" value="0">
						</div>
						<div class="vwfc-field">
							<label for="vwfc-import-cancelled"><?php echo esc_html__( 'Cancelled', 'vw-fraud-checker' ); ?></label>
							<input type="number" min="0" id="vwfc-import-cancelled" name="cancelled" value="0">
						</div>
					</div>

					<?php submit_button( __( 'Save Metrics', 'vw-fraud-checker' ) ); ?>
				</form>
			</div>

			<div class="vwfc-panel">
				<h2><?php echo esc_html__( 'Recent Entries', 'vw-fraud-checker' ); ?></h2>
				<?php if ( empty( $recent ) ) : ?>
					<p><?php echo esc_html__( 'No manual entries yet.', 'vw-fraud-checker' ); ?></p>
				<?php else : ?>
					<table class="vwfc-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Phone', 'vw-fraud-checker' ); ?></th>
								<th><?php echo esc_html__( 'Courier', 'vw-fraud-checker' ); ?></th>
								<th><?php echo esc_html__( 'Delivered', 'vw-fraud-checker' ); ?></th>
								<th><?php echo esc_html__( 'Returned', 'vw-fraud-checker' ); ?></th>
								<th><?php echo esc_html__( 'Cancelled', 'vw-fraud-checker' ); ?></th>
								<th><?php echo esc_html__( 'Risk', 'vw-fraud-checker' ); ?></th>
								<th><?php echo esc_html__( 'Updated', 'vw-fraud-checker' ); ?></th>
								<th><?php echo esc_html__( 'Actions', 'vw-fraud-checker' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $recent as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['phone'] ); ?></td>
								<td><?php echo esc_html( ucwords( $row['courier'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['delivered'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['returned'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['cancelled'] ) ); ?></td>
								<td><?php echo esc_html( vw_fraud_checker_format_percentage( $row['risk_ratio'], 2 ) ); ?></td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['updated_at'] ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_DELETE ); ?>">
										<input type="hidden" name="id" value="<?php echo esc_attr( $row['id'] ); ?>">
										<?php wp_nonce_field( self::ACTION_DELETE . '_' . $row['id'] ); ?>
										<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this record?', 'vw-fraud-checker' ) ); ?>');"><?php echo esc_html__( 'Delete', 'vw-fraud-checker' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize providers configuration before saving.
	 *
	 * @param array<string, mixed> $value Raw values submitted from the providers form.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_providers( $value ) {
		$definitions = vw_fraud_checker_get_supported_providers();
		$clean       = array();

		$value = is_array( $value ) ? $value : array();

		foreach ( $definitions as $slug => $provider ) {
			$clean[ $slug ] = array();
			$incoming      = isset( $value[ $slug ] ) && is_array( $value[ $slug ] ) ? $value[ $slug ] : array();

			foreach ( $provider['fields'] as $field_key => $field ) {
				$raw_value = isset( $incoming[ $field_key ] ) ? $incoming[ $field_key ] : null;

				switch ( $field['type'] ) {
					case 'toggle':
						$clean[ $slug ][ $field_key ] = (bool) $raw_value;
						break;
					case 'url':
						$clean[ $slug ][ $field_key ] = esc_url_raw( $raw_value );
						break;
					default:
						$clean[ $slug ][ $field_key ] = is_string( $raw_value ) ? sanitize_text_field( $raw_value ) : '';
				}
			}
		}

		$this->api->set_provider_settings( $clean );

		return $clean;
	}

	/**
	 * Handle manual metrics import submissions.
	 */
	public function handle_import_metrics() {
		$this->ensure_capability();

		check_admin_referer( self::ACTION_IMPORT );

		$phone     = isset( $_POST['phone'] ) ? vw_fraud_checker_sanitize_phone( wp_unslash( $_POST['phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$courier   = isset( $_POST['courier'] ) ? sanitize_key( wp_unslash( $_POST['courier'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$delivered = isset( $_POST['delivered'] ) ? max( 0, (int) $_POST['delivered'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$returned  = isset( $_POST['returned'] ) ? max( 0, (int) $_POST['returned'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$cancelled = isset( $_POST['cancelled'] ) ? max( 0, (int) $_POST['cancelled'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		$definitions = vw_fraud_checker_get_supported_providers();

		if ( empty( $phone ) || empty( $courier ) || ! isset( $definitions[ $courier ] ) ) {
			$this->redirect_with_notice( self::MENU_SLUG . '-data', 'invalid' );
		}

		$this->database->upsert_metrics(
			array(
				'phone'     => $phone,
				'courier'   => $courier,
				'delivered' => $delivered,
				'returned'  => $returned,
				'cancelled' => $cancelled,
				'updated_at' => current_time( 'mysql' ),
			)
		);

		$this->redirect_with_notice( self::MENU_SLUG . '-data', 'imported', array( 'count' => 1 ) );
	}

	/**
	 * Handle deletion of a manual entry.
	 */
	public function handle_delete_entry() {
		$this->ensure_capability();

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $id <= 0 ) {
			$this->redirect_with_notice( self::MENU_SLUG . '-data', 'invalid' );
		}

		check_admin_referer( self::ACTION_DELETE . '_' . $id );

		$this->database->delete_entry( $id );

		$this->redirect_with_notice( self::MENU_SLUG . '-data', 'deleted' );
	}

	/**
	 * Ensure the current admin can manage settings.
	 */
	private function ensure_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'vw-fraud-checker' ) );
		}
	}

	/**
	 * Refresh courier data for a phone number via registered adapters.
	 *
	 * @param string $phone Phone number in normalized format.
	 *
	 * @return array<string, mixed>
	 */
	private function refresh_phone_from_providers( $phone ) {
		$results    = $this->api->fetch_metrics( $phone );
		$imported   = 0;
		$providers  = array();

		foreach ( $results as $slug => $payload ) {
			if ( ! is_array( $payload ) || empty( $payload ) ) {
				continue;
			}

			$providers[] = $slug;

			$this->database->upsert_metrics(
				array(
					'phone'     => isset( $payload['phone'] ) ? $payload['phone'] : $phone,
					'courier'   => isset( $payload['courier'] ) ? $payload['courier'] : $slug,
					'delivered' => isset( $payload['delivered'] ) ? (int) $payload['delivered'] : 0,
					'returned'  => isset( $payload['returned'] ) ? (int) $payload['returned'] : 0,
					'cancelled' => isset( $payload['cancelled'] ) ? (int) $payload['cancelled'] : 0,
					'updated_at' => isset( $payload['updated_at'] ) ? $payload['updated_at'] : current_time( 'mysql' ),
				)
			);
			$imported++;
		}

		return array(
			'count'     => $imported,
			'providers' => $providers,
		);
	}

	/**
	 * Retrieve formatted admin notice message.
	 *
	 * @param string $code Notice code.
	 * @param array  $data Additional payload.
	 *
	 * @return string
	 */
	private function get_notice_message( $code, array $data ) {
		switch ( $code ) {
			case 'imported':
				$count = isset( $data['count'] ) ? (int) $data['count'] : 0;
				return sprintf( esc_html__( '%d metrics saved successfully.', 'vw-fraud-checker' ), max( 1, $count ) );
			case 'deleted':
				return esc_html__( 'The entry was deleted.', 'vw-fraud-checker' );
			case 'refreshed':
				$providers = isset( $data['providers'] ) ? explode( ',', $data['providers'] ) : array();
				$providers = array_filter( array_map( 'trim', $providers ) );
				return sprintf(
					esc_html__( 'Data refreshed from %1$d provider(s).', 'vw-fraud-checker' ),
					count( $providers )
				);
			case 'invalid':
				return esc_html__( 'Please provide a valid phone number and courier.', 'vw-fraud-checker' );
		}

		return '';
	}

	/**
	 * Redirect back to plugin page with a status notice.
	 *
	 * @param string $page_slug Target page slug.
	 * @param string $code      Notice code.
	 * @param array  $args      Extra query args.
	 */
	private function redirect_with_notice( $page_slug, $code, array $args = array() ) {
		$url = add_query_arg(
			array_merge(
				array(
					'page'        => $page_slug,
					'vwfc_notice' => $code,
				),
				$args
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}


