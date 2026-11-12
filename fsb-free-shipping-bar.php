<?php
/**
 * Plugin Name:       Free Shipping Progress Bar for WooCommerce
 * Description:       Shows customers how much more they need to spend to unlock free shipping. Works on the cart, checkout and mini-cart.
 * Version:           1.0.0
 * Author:            Sameer Shah
 * License:           GPL-2.0-or-later
 * Text Domain:       fsb-free-shipping-bar
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FSB_Free_Shipping_Bar {

	const OPTION = 'fsb_options';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ) );
	}

	public static function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Admin settings.
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Front end.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_cart_table', array( __CLASS__, 'render' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render' ), 5 );
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', array( __CLASS__, 'render' ) );

		// Keep the bar in sync when the cart changes via AJAX.
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ) );
	}

	/* ---------- Settings ---------- */

	public static function defaults() {
		return array(
			'enabled'   => 1,
			'threshold' => 999,
		);
	}

	public static function get_options() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Free Shipping Bar', 'fsb-free-shipping-bar' ),
			__( 'Free Shipping Bar', 'fsb-free-shipping-bar' ),
			'manage_woocommerce',
			'fsb-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'fsb_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'enabled'   => empty( $input['enabled'] ) ? 0 : 1,
			'threshold' => max( 0, (float) wc_format_decimal( isset( $input['threshold'] ) ? $input['threshold'] : 0 ) ),
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$opts = self::get_options();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Free Shipping Progress Bar', 'fsb-free-shipping-bar' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'fsb_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable bar', 'fsb-free-shipping-bar' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( 1, $opts['enabled'] ); ?> />
								<?php esc_html_e( 'Show the progress bar to customers', 'fsb-free-shipping-bar' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fsb_threshold"><?php esc_html_e( 'Free shipping threshold', 'fsb-free-shipping-bar' ); ?></label></th>
						<td>
							<input id="fsb_threshold" type="number" min="0" step="0.01" class="regular-text"
								name="<?php echo esc_attr( self::OPTION ); ?>[threshold]"
								value="<?php echo esc_attr( $opts['threshold'] ); ?>" />
							<p class="description">
								<?php
								/* translators: %s: currency symbol */
								printf( esc_html__( 'Cart subtotal (in %s) needed to unlock free shipping. Set the same amount in your WooCommerce free shipping method.', 'fsb-free-shipping-bar' ), esc_html( get_woocommerce_currency_symbol() ) );
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ---------- Front end ---------- */

	public static function enqueue_assets() {
		$path = plugin_dir_path( __FILE__ ) . 'assets/style.css';
		wp_enqueue_style(
			'fsb-style',
			plugin_dir_url( __FILE__ ) . 'assets/style.css',
			array(),
			file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0'
		);
	}

	public static function get_markup() {
		$opts = self::get_options();
		if ( empty( $opts['enabled'] ) || (float) $opts['threshold'] <= 0 ) {
			return '';
		}
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return '';
		}

		$threshold = (float) $opts['threshold'];
		$subtotal  = (float) WC()->cart->get_displayed_subtotal();
		$remaining = max( 0, $threshold - $subtotal );
		$percent   = (int) min( 100, floor( ( $subtotal / $threshold ) * 100 ) );

		if ( $remaining > 0 ) {
			/* translators: %s: formatted price */
			$message = sprintf( __( 'Add %s more to get FREE shipping!', 'fsb-free-shipping-bar' ), wc_price( $remaining ) );
		} else {
			$message = __( 'Congratulations! You have unlocked FREE shipping.', 'fsb-free-shipping-bar' );
		}

		ob_start();
		?>
		<div class="fsb-bar<?php echo $remaining <= 0 ? ' fsb-bar--done' : ''; ?>">
			<p class="fsb-bar__text"><?php echo wp_kses_post( $message ); ?></p>
			<div class="fsb-bar__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $percent ); ?>">
				<span class="fsb-bar__fill" style="width:<?php echo esc_attr( $percent ); ?>%"></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render() {
		echo self::get_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_markup().
	}

	public static function fragments( $fragments ) {
		$markup = self::get_markup();
		// Replaces every .fsb-bar on the page after add-to-cart / cart updates.
		$fragments['div.fsb-bar'] = '' !== $markup ? $markup : '<div class="fsb-bar" hidden></div>';
		return $fragments;
	}
}

FSB_Free_Shipping_Bar::init();
