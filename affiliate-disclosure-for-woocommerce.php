<?php
/**
 * Plugin Name:       Affiliate Disclosure for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/affiliate-disclosure-for-woocommerce/
 * Description:       Display a custom disclosure text on your WooCommerce external/affiliate (or all) products.
 * Version:           1.3
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * Author:            WPExplorer
 * Author URI:        https://www.wpexplorer.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       affiliate-disclosure-for-woocommerce
 * Domain Path:       /languages/
 *
 * WC tested up to: 9.1
 */

/*
Affiliate Disclosure for WooCommerce is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Affiliate Disclosure for WooCommerce is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Affiliate Disclosure for WooCommerce. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

// Prevent direct user access.
defined( 'ABSPATH' ) || exit;

/**
 * Main Affiliate_Disclosure_for_WooCommerce Class.
 */
if ( ! class_exists( 'Affiliate_Disclosure_for_WooCommerce' ) ) {

	final class Affiliate_Disclosure_for_WooCommerce {

		/**
		 * Affiliate_Disclosure_for_WooCommerce constructor.
		 */
		public function __construct() {
			// Add link to the customizer from the plugins page.
			if ( is_admin() ) {
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			}

			// Register new customizer settings.
			add_action( 'customize_register', array( $this, 'customize_register' ) );
			
			// Need to register the action on all hooks or it won't show up in the Customizer.
			foreach ( $this->get_allowed_hooks() as $hook ) {
				add_action( $hook, array( $this, 'disclosure_notice' ) );
			}
		}

		/**
		 * Add settings link to plugins admin page.
		 */
		public function plugin_action_links( $links ) {
			$plugin_links = array(
				'<a href="' . esc_url( admin_url( '/customize.php?autofocus[section]=adfw_settings' ) ) . '">' . esc_html__( 'Settings', 'affiliate-disclosure-for-woocommerce' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Register Customizer settings.
		 */
		public function customize_register( $wp_customize ) {
			$color_control = class_exists( 'TotalTheme\Customizer\Controls\Color', true ) ? 'totaltheme_color' : 'color';
			$color_control_obj = ( $color_control === 'totaltheme_color' ) ? 'TotalTheme\Customizer\Controls\Color' : 'WP_Customize_Color_Control';
			$unit_control = class_exists( 'TotalTheme\Customizer\Controls\Length_Unit', true ) ? 'totaltheme_length_unit' : 'text';
			$unit_control_obj = ( $unit_control === 'totaltheme_length_unit' ) ? 'TotalTheme\Customizer\Controls\Length_Unit' : 'WP_Customize_Control';

			// Register new customizer tab.
			$wp_customize->add_section( 'adfw_settings', array(
				'title'    => esc_html__( 'Affiliate Disclosure', 'affiliate-disclosure-for-woocommerce' ),
				'priority' => PHP_INT_MAX,
				'panel' => 'woocommerce',
				'description' => esc_html__( 'You can target the "adfw-disclosure-text" classname for additional styling.', 'affiliate-disclosure-for-woocommerce' ),
			) );

			/// Disclosure Text.
			$wp_customize->add_setting( 'adfw_text', array(
				'sanitize_callback' => 'wp_kses_post',
				'transport'         => 'refresh',
			) );
			$wp_customize->add_control( 'adfw_text', array(
				'label'       => esc_html__( 'Affiliate Disclosure Text', 'affiliate-disclosure-for-woocommerce' ),
				'section'     => 'adfw_settings',
				'settings'    => 'adfw_text',
				'type'        => 'textarea',
			) );

			// External/Affiliate only check.
			$wp_customize->add_setting( 'adfw_external_only', array(
				'default'           => true,
				'sanitize_callback' => 'wp_validate_boolean',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( 'adfw_external_only', array(
				'label'       => esc_html__( 'Display on External/Affiliate Products Only?', 'affiliate-disclosure-for-woocommerce' ),
				'section'     => 'adfw_settings',
				'settings'    => 'adfw_external_only',
				'type'        => 'checkbox',
			) );

			// Placement/Hook.
			$wp_customize->add_setting( 'adfw_hook', array(
				'default'           => 'woocommerce_after_add_to_cart_form',
				'sanitize_callback' => array( $this, 'sanitize_callback_hook_name' ),
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( 'adfw_hook', array(
				'label'       => esc_html__( 'Disclosure Placement (Hook)', 'affiliate-disclosure-for-woocommerce' ),
				'section'     => 'adfw_settings',
				'settings'    => 'adfw_hook',
				'type'        => 'select',
				'choices'     => array(
					'woocommerce_before_add_to_cart_form'   => esc_html__( 'Before Add to Cart Form', 'affiliate-disclosure-for-woocommerce' ),
					'woocommerce_after_add_to_cart_form'    => esc_html__( 'After Add to Cart Form', 'affiliate-disclosure-for-woocommerce' ),
					'woocommerce_before_add_to_cart_button' => esc_html__( 'Before Add to Cart Button', 'affiliate-disclosure-for-woocommerce' ),
					'woocommerce_after_add_to_cart_button'  => esc_html__( 'After Add to Cart Button', 'affiliate-disclosure-for-woocommerce' ),
					'woocommerce_product_meta_start'        => esc_html__( 'Product Meta Start', 'affiliate-disclosure-for-woocommerce' ),
					'woocommerce_product_meta_end'          => esc_html__( 'Product Meta End', 'affiliate-disclosure-for-woocommerce' ),
				),
			) );

			// Text Align.
			$wp_customize->add_setting( 'adfw_text_align', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( 'adfw_text_align', array(
				'label'    => esc_html__( 'Text Align', 'affiliate-disclosure-for-woocommerce' ),
				'section'  => 'adfw_settings',
				'settings' => 'adfw_text_align',
				'type'     => 'select',
				'choices'  => array(
					''       => esc_html__( 'Default', 'affiliate-disclosure-for-woocommerce' ),
					'left'   => esc_html__( 'Start', 'affiliate-disclosure-for-woocommerce' ),
					'center' => esc_html__( 'Center', 'affiliate-disclosure-for-woocommerce' ),
					'right'  => esc_html__( 'End', 'affiliate-disclosure-for-woocommerce' ),
				),
			) );

			// Background.
			$wp_customize->add_setting( 'adfw_text_background', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( new $color_control_obj( $wp_customize, 'adfw_text_background', array(
				'label'    => esc_html__( 'Background', 'affiliate-disclosure-for-woocommerce' ),
				'section'  => 'adfw_settings',
				'settings' => 'adfw_text_background',
				'type'     => $color_control,
			) ) );

			// Border
			$wp_customize->add_setting( 'adfw_text_border', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( 'adfw_text_border', array(
				'label'       => esc_html__( 'Border', 'affiliate-disclosure-for-woocommerce' ),
				'section'     => 'adfw_settings',
				'settings'    => 'adfw_text_border',
				'type'        => 'text',
				'description' => esc_html__( 'Use shorthand format (width style color). Example: 1px solid red', 'affiliate-disclosure-for-woocommerce' ),
			) );

			// Color.
			$wp_customize->add_setting( 'adfw_text_color', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( new $color_control_obj( $wp_customize, 'adfw_text_color', array(
				'label'    => esc_html__( 'Text Color', 'affiliate-disclosure-for-woocommerce' ),
				'section'  => 'adfw_settings',
				'settings' => 'adfw_text_color',
				'type'     => $color_control,
			) ) );

			// Font-Size.
			$wp_customize->add_setting( 'adfw_text_font_size', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( 'adfw_text_font_size', array(
				'label'    => esc_html__( 'Font Size', 'affiliate-disclosure-for-woocommerce' ),
				'section'  => 'adfw_settings',
				'settings' => 'adfw_text_font_size',
				'type'     => 'text',
			) );

			// Margin Top.
			$wp_customize->add_setting( 'adfw_text_margin_top', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( new $unit_control_obj( $wp_customize, 'adfw_text_margin_top', array(
				'label'    => esc_html__( 'Margin Top', 'affiliate-disclosure-for-woocommerce' ),
				'section'  => 'adfw_settings',
				'settings' => 'adfw_text_margin_top',
				'type'     => $unit_control,
			) ) );

			// Margin Bottom.
			$wp_customize->add_setting( 'adfw_text_margin_bottom', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( new $unit_control_obj( $wp_customize, 'adfw_text_margin_bottom', array(
				'label'    => esc_html__( 'Margin Bottom', 'affiliate-disclosure-for-woocommerce' ),
				'section'  => 'adfw_settings',
				'settings' => 'adfw_text_margin_bottom',
				'type'     => $unit_control,
			) ) );

			// Padding Top.
			$wp_customize->add_setting( 'adfw_text_padding_top', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( new $unit_control_obj( $wp_customize, 'adfw_text_padding_top', array(
				'label'    => esc_html__( 'Top Padding', 'affiliate-disclosure-for-woocommerce' ),
				'section'  => 'adfw_settings',
				'settings' => 'adfw_text_padding_top',
				'type'     => $unit_control,
			) ) );

			// Padding Bottom.
			$wp_customize->add_setting( 'adfw_text_padding_bottom', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( new $unit_control_obj( $wp_customize, 'adfw_text_padding_bottom', array(
				'label'    => esc_html__( 'Bottom Padding', 'affiliate-disclosure-for-woocommerce' ),
				'section'  => 'adfw_settings',
				'settings' => 'adfw_text_padding_bottom',
				'type'     => $unit_control,
			) ) );

			// Padding Left.
			$wp_customize->add_setting( 'adfw_text_padding_left', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( new $unit_control_obj( $wp_customize, 'adfw_text_padding_left', array(
				'label'    => esc_html__( 'Left Padding', 'affiliate-disclosure-for-woocommerce' ),
				'section'  => 'adfw_settings',
				'settings' => 'adfw_text_padding_left',
				'type'     => $unit_control,
			) ) );

			// Padding Right.
			$wp_customize->add_setting( 'adfw_text_padding_right', array(
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			) );

			$wp_customize->add_control( new $unit_control_obj( $wp_customize, 'adfw_text_padding_right', array(
				'label'    => esc_html__( 'Right Padding', 'affiliate-disclosure-for-woocommerce' ),
				'section'  => 'adfw_settings',
				'settings' => 'adfw_text_padding_right',
				'type'     => $unit_control,
			) ) );

		}

		/**
		 * Potentially display the disclosure notice.
		 */
		public function disclosure_notice(): void {
			$hook_name = $this->get_selected_hook();

			if ( $hook_name !== current_filter() || ! $this->affiliate_check() ) {
				return;
			}

			$text_safe = ( $text = get_theme_mod( 'adfw_text' ) ) ? do_shortcode( wp_kses_post( $text ) ) : '';

			if ( ! $text_safe ) {
				return;
			}

			$inline_style = 'clear:both;';

			$settings = [
				'color'                => 'adfw_text_color',
				'background-color'     => 'adfw_text_background',
				'font-size'            => 'adfw_text_font_size',
				'margin-block-start'   => 'adfw_text_margin_top',
				'margin-block-end'     => 'adfw_text_margin_bottom',
				'padding-block-start'  => 'adfw_text_padding_top',
				'padding-block-end'    => 'adfw_text_padding_bottom',
				'padding-inline-start' => 'adfw_text_padding_left',
				'padding-inline-end'   => 'adfw_text_padding_right',
				'border'               => 'adfw_text_border',
				'text-align'           => 'adfw_text_align',
			];

			foreach ( $settings as $property => $mod_name ) {
				if ( $item_css = $this->generate_css( $property, $mod_name ) ) {
					$inline_style .= $item_css;
				}
			}

			$inline_style = $inline_style ? ' style="' . esc_attr( trim( $inline_style ) ) . '"' : '';

			echo '<div class="adfw-disclosure-text"' . $inline_style . '>' . $text_safe . '</div>';
		}

		/**
		 * Helper function to check if we are currently viewing an affiliate product.
		 */
		public function affiliate_check(): bool {
			global $product;
			return ! wp_validate_boolean( get_theme_mod( 'adfw_external_only', true ) ) || ( is_object( $product ) && $product->is_type( 'external' ) );
		}

		/**
		 * Returns the user selected hook.
		 */
		private function get_selected_hook(): string {
			$hook = 'woocommerce_after_add_to_cart_form';
			$mod = get_theme_mod( 'adfw_hook' );
			if ( $mod && in_array( $mod, $this->get_allowed_hooks(), true ) ) {
				$hook = $mod;
			}
			return (string) apply_filters( 'affiliate_disclosure_for_woocommerce/hook_name', $hook );
		}

		/**
		 * Array of allowed hooks.
		 */
		private function get_allowed_hooks(): array {
			return [
				'woocommerce_before_add_to_cart_form',
				'woocommerce_after_add_to_cart_form',
				'woocommerce_before_add_to_cart_button',
				'woocommerce_after_add_to_cart_button',
				'woocommerce_product_meta_start',
				'woocommerce_product_meta_end',
			];
		}

		/**
		 * Sanitize callback for the hook selection field.
		 */
		public function sanitize_callback_hook_name( $input, $setting ) {
			if ( in_array( $input, $this->get_allowed_hooks(), true ) ) {
				return $input;
			}
		}

		/**
		 * Generate CSS.
		 */
		private function generate_css( string $property, string $mod_name ) {
			$value = get_theme_mod( $mod_name );
			
			if ( ! $value ) {
				return;
			}
			
			$value_safe = sanitize_text_field( $value );

			if ( ! $value_safe ) {
				return;
			}

			switch ( $property ) {
				case 'text-align':
					if ( 'left' === $value_safe ) {
						$value_safe = 'start';
					} elseif ( 'right' === $value_safe ) {
						$value_safe = 'end';
					}
					break;
				case 'color':
				case 'background-color':
					if ( $value_safe && function_exists( 'wpex_parse_color' ) ) {
						$value_safe = wpex_parse_color( $value_safe );
					}
					break;
				case 'font-size':
				case 'margin-block-start':
				case 'margin-block-end':
				case 'padding-block-start':
				case 'padding-block-end':
				case 'padding-inline-start':
				case 'padding-inline-end':
					$value_safe = $this->sanitize_size( $value );
					break;
			}
			if ( $value_safe ) {
				return "{$property}:{$value_safe};";
			}
		}

		/**
		 * Sanitize size.
		 */
		private function sanitize_size( $size ) {
			if ( is_numeric( $size ) ) {
				$size = "{$size}px";
			}
			return $size;
		}

	}

	new Affiliate_Disclosure_for_WooCommerce;

}