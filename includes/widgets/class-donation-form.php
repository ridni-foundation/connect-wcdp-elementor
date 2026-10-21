<?php
/**
 * Donation form widget.
 *
 * @package WCDP_Elementor
 */

namespace WCDP_Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use WCDP_Elementor\Data\Product_Resolver;
use WCDP_Elementor\Render\Shortcode_Runner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCDP donation form with a full Style tab.
 *
 * The form is rendered by WCDP — it is a real WooCommerce checkout, and
 * reimplementing it would mean reimplementing payment. What this widget adds
 * is control: WCDP drives almost its entire appearance through custom
 * properties declared on `:root`, so redeclaring those on the widget turns a
 * site-wide palette into a per-instance one, and the descendant selectors
 * cover everything the variables do not reach.
 *
 * Two upstream behaviours shape the implementation:
 *
 * - In popup mode WCDP prints the form on `wp_footer`, outside the widget
 *   wrapper, so style selectors have to name `#wcdp-form` as well as
 *   `{{WRAPPER}}`. Only one form per page is permitted, so that id is unique.
 * - WCDP refuses to render on `elementor_ajax` and returns a placeholder
 *   string instead, so the form cannot re-render live after a content change.
 *   Style controls are unaffected: they are pure CSS and need no re-render.
 */
class Donation_Form extends Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'wcdp-donation-form';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return esc_html__( 'WCDP Donation Form', 'connect-wcdp-elementor' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-form-horizontal';
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'donation', 'donate', 'form', 'checkout', 'fundraising', 'wcdp' );
	}

	/**
	 * Stylesheets.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( 'wcdp-el-base', 'wcdp-el-form' );
	}

	/* ─────────────────────────── Selector helpers ─────────────────────────── */

	/**
	 * Both roots the form can live under.
	 *
	 * Custom properties set here cascade into the whole form, inline or popup.
	 *
	 * @return string
	 */
	private function root(): string {
		return '{{WRAPPER}} .wcdp-form, body #wcdp-form';
	}

	/**
	 * A selector inside the form, valid in both inline and popup mode.
	 *
	 * @param string $suffix Descendant selector.
	 * @return string
	 */
	private function sel( string $suffix ): string {
		return '{{WRAPPER}} .wcdp-form ' . $suffix . ', body #wcdp-form ' . $suffix;
	}

	/**
	 * A selector on the popup chrome, which never exists inside the wrapper.
	 *
	 * @param string $suffix Selector, relative to the overlay.
	 * @return string
	 */
	private function popup_sel( string $suffix = '' ): string {
		return 'body #wcdp-form' . ( '' !== $suffix ? ' ' . $suffix : '' );
	}

	/* ─────────────────────────── Controls ─────────────────────────── */

	/**
	 * Register controls.
	 */
	protected function register_controls(): void {
		$this->register_content_controls();
		$this->register_palette_style();
		$this->register_box_style();
		$this->register_header_style();
		$this->register_amount_options_style();
		$this->register_amount_input_style();
		$this->register_range_style();
		$this->register_labels_style();
		$this->register_buttons_style();
		$this->register_fields_style();
		$this->register_fee_recovery_style();
		$this->register_popup_style();
		$this->register_media_style();
		$this->register_misc_style();
	}

	/**
	 * Content → Donation Form.
	 */
	private function register_content_controls(): void {
		$this->open_content_section( 'section_campaign', esc_html__( 'Donation Form', 'connect-wcdp-elementor' ) );

		$this->register_product_controls();

		// Legacy control names, unchanged from 1.0.x.
		$this->add_control(
			'style',
			array(
				'label'     => esc_html__( 'Form Layout', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '1',
				'separator' => 'before',
				'options'   => array(
					'1' => esc_html__( '1 — Three steps with progress header', 'connect-wcdp-elementor' ),
					'2' => esc_html__( '2 — Everything on one page', 'connect-wcdp-elementor' ),
					'3' => esc_html__( '3 — Three steps, no header', 'connect-wcdp-elementor' ),
					'4' => esc_html__( '4 — Amount only, redirect to checkout', 'connect-wcdp-elementor' ),
					'5' => esc_html__( '5 — Three steps with banner header', 'connect-wcdp-elementor' ),
				),
			)
		);

		$this->add_control(
			'popup',
			array(
				'label'        => esc_html__( 'Open in a Popup', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => '1',
				'default'      => '',
			)
		);

		$this->add_control(
			'button',
			array(
				'label'        => esc_html__( 'Show Trigger Button', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => '1',
				'default'      => '1',
				'condition'    => array( 'popup' => '1' ),
				'description'  => esc_html__( 'Turn off to open the popup from your own link pointing at #wcdp-form.', 'connect-wcdp-elementor' ),
			)
		);

		$this->add_control(
			'label',
			array(
				'label'       => esc_html__( 'Trigger Button Label', 'connect-wcdp-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Donate now!', 'connect-wcdp-elementor' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
				'condition'   => array(
					'popup'  => '1',
					'button' => '1',
				),
			)
		);

		$this->add_control(
			'show_image',
			array(
				'label'        => esc_html__( 'Show Product Image', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => '1',
				'default'      => '',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'        => esc_html__( 'Show Product Title', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => '1',
				'default'      => '',
			)
		);

		$this->add_control(
			'show_short_description',
			array(
				'label'        => esc_html__( 'Show Short Description', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => '1',
				'default'      => '',
			)
		);

		$this->add_control(
			'show_description',
			array(
				'label'        => esc_html__( 'Show Full Description', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => '1',
				'default'      => '',
			)
		);

		$this->add_control(
			'modern_header',
			array(
				'label'        => esc_html__( 'Modern Step Header', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
				'condition'    => array( 'style' => array( '1' ) ),
				'description'  => esc_html__( 'Rebuilds the step header with CSS grid instead of floats and a fixed height. Same design, but it no longer collapses inside flex containers. Enable this if the header looks misaligned.', 'connect-wcdp-elementor' ),
			)
		);

		$this->add_control(
			'concurrency_notice',
			array(
				'type'      => Controls_Manager::RAW_HTML,
				'raw'       => '<div class="elementor-control-field-description">' . esc_html__( 'WCDP allows one donation form per page, and requires the classic [woocommerce_checkout] — the WooCommerce checkout block is unsupported upstream.', 'connect-wcdp-elementor' ) . '</div>',
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Colors.
	 *
	 * The highest-leverage section in the widget: WCDP drives nearly its whole
	 * appearance from these custom properties, and redeclaring them on the
	 * widget root replaces the site-wide palette for this instance only.
	 */
	private function register_palette_style(): void {
		$this->open_style_section( 'section_palette', esc_html__( 'Colors', 'connect-wcdp-elementor' ) );

		$root = $this->root();

		// Grouped under headings so every label stays short: a two-line label
		// pushes the colour picker out of the panel at its minimum width.
		$variables = array(
			array(
				'heading' => esc_html__( 'Brand', 'connect-wcdp-elementor' ),
			),
			array(
				'key'      => 'primary',
				'label'    => esc_html__( 'Primary', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-main-2',
				'global'   => Global_Colors::COLOR_PRIMARY,
				'note'     => esc_html__( 'Active step, buttons and the progress fill.', 'connect-wcdp-elementor' ),
			),
			array(
				'key'      => 'secondary',
				'label'    => esc_html__( 'Secondary', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-main',
				'global'   => Global_Colors::COLOR_SECONDARY,
				'note'     => esc_html__( 'Inactive step and the progress track.', 'connect-wcdp-elementor' ),
			),
			array(
				'key'      => 'error',
				'label'    => esc_html__( 'Error', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-main-3',
			),
			array(
				'heading' => esc_html__( 'Step Markers', 'connect-wcdp-elementor' ),
			),
			array(
				'key'      => 'step_2',
				'label'    => esc_html__( 'Step 2', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-step-2',
			),
			array(
				'key'      => 'step_3',
				'label'    => esc_html__( 'Step 3', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-step-3',
			),
			array(
				'heading' => esc_html__( 'Amount Buttons', 'connect-wcdp-elementor' ),
			),
			array(
				'key'      => 'label_inactive',
				'label'    => esc_html__( 'Background', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-label-inactive',
			),
			array(
				'key'      => 'label_hover',
				'label'    => esc_html__( 'Hover', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-label-inactive-hover',
			),
			array(
				'key'      => 'label_text',
				'label'    => esc_html__( 'Text', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-label-text',
			),
			array(
				'key'      => 'label_text_on',
				'label'    => esc_html__( 'Selected Text', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-label-text-checked',
				'note'     => esc_html__( 'Also used for any text sitting on the primary colour.', 'connect-wcdp-elementor' ),
			),
			array(
				'heading' => esc_html__( 'Fields & Popup', 'connect-wcdp-elementor' ),
			),
			array(
				'key'      => 'controls',
				'label'    => esc_html__( 'Input Text', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-controls',
			),
			array(
				'key'      => 'popup_bg',
				'label'    => esc_html__( 'Popup Background', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-background-color',
			),
			array(
				'key'      => 'overlay',
				'label'    => esc_html__( 'Overlay', 'connect-wcdp-elementor' ),
				'variable' => '--wcdp-overlay-color',
				'alpha'    => true,
			),
		);

		foreach ( $variables as $index => $config ) {
			if ( isset( $config['heading'] ) ) {
				$this->add_control(
					'color_heading_' . $index,
					array(
						'label'     => $config['heading'],
						'type'      => Controls_Manager::HEADING,
						'separator' => 'before',
					)
				);

				continue;
			}

			$control = array(
				'label'     => $config['label'],
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $root => $config['variable'] . ': {{VALUE}};' ),
			);

			if ( isset( $config['global'] ) ) {
				$control['global'] = array( 'default' => $config['global'] );
			}

			if ( ! empty( $config['alpha'] ) ) {
				$control['alpha'] = true;
			}

			if ( isset( $config['note'] ) ) {
				$control['description'] = $config['note'];
			}

			$this->add_control( 'color_' . $config['key'], $control );
		}

		$this->end_controls_section();
	}

	/**
	 * Style → Form Box.
	 */
	private function register_box_style(): void {
		$this->open_style_section( 'section_box', esc_html__( 'Form Box', 'connect-wcdp-elementor' ) );

		$form = '{{WRAPPER}} .wcdp-form, body #wcdp-form .wcdp-form';

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'form_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $form,
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'form_border',
				'selector' => $form,
			)
		);

		$this->add_border_radius( 'form_radius', $form );

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'form_box_shadow',
				'selector' => $form,
			)
		);

		$this->add_padding( 'form_padding', $form );

		$this->add_slider(
			'form_max_width',
			esc_html__( 'Maximum Width', 'connect-wcdp-elementor' ),
			$form,
			'max-width: {{SIZE}}{{UNIT}}; margin-inline: auto;',
			array(
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array( 'px' => array( 'max' => 1200 ) ),
			)
		);

		$this->add_slider(
			'row_spacing',
			esc_html__( 'Spacing Between Rows', 'connect-wcdp-elementor' ),
			$this->sel( '.wcdp-row' ),
			'margin-bottom: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'max' => 80 ) ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Step Header.
	 */
	private function register_header_style(): void {
		$this->open_style_section(
			'section_header',
			esc_html__( 'Step Header', 'connect-wcdp-elementor' ),
			array( 'condition' => array( 'style' => array( '1', '5' ) ) )
		);

		$root = $this->root();

		/* ── Layout 1: circles and progress line ── */

		$this->add_typography( 'header_typography', $this->sel( '.wcdp-header > .wcdp-step' ), array( 'condition' => array( 'style' => '1' ) ) );

		$this->add_slider(
			'header_circle_size',
			esc_html__( 'Step Circle Size', 'connect-wcdp-elementor' ),
			$root,
			'--wcdp-el-step-circle-size: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'em', 'px', 'rem' ),
				'range'      => array( 'em' => array( 'max' => 6 ) ),
				'condition'  => array( 'style' => '1' ),
			)
		);

		$this->add_slider(
			'header_line_height',
			esc_html__( 'Progress Line Thickness', 'connect-wcdp-elementor' ),
			$root,
			'--wcdp-el-step-line-height: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'max' => 30 ) ),
				'condition'  => array(
					'style'         => '1',
					'modern_header' => 'yes',
				),
			)
		);

		$this->add_margin( 'header_margin', $this->sel( '.wcdp-header' ), array( 'condition' => array( 'style' => '1' ) ) );

		/* ── Layout 5: chevron bar ── */

		$this->add_control(
			'header5_heading',
			array(
				'label'     => esc_html__( 'Banner Header', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'style' => '5' ),
			)
		);

		$this->add_typography( 'header5_typography', $this->sel( '.wcdp-style5' ), array( 'condition' => array( 'style' => '5' ) ) );

		$this->add_slider(
			'header5_height',
			esc_html__( 'Height', 'connect-wcdp-elementor' ),
			$this->sel( '.wcdp-style5' ),
			'height: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'em', 'px', 'rem' ),
				'range'      => array( 'em' => array( 'max' => 8 ) ),
				'condition'  => array( 'style' => '5' ),
			)
		);

		$this->add_margin( 'header5_margin', $this->sel( '.wcdp-steps-wrapper' ), array( 'condition' => array( 'style' => '5' ) ) );

		$this->end_controls_section();
	}

	/**
	 * Style → Amount Buttons.
	 */
	private function register_amount_options_style(): void {
		$this->open_style_section( 'section_amount_options', esc_html__( 'Amount Buttons', 'connect-wcdp-elementor' ) );

		$list  = $this->sel( 'ul.wcdp_options' );
		$label = $this->sel( 'ul.wcdp_options > li > label' );

		$this->add_responsive_control(
			'options_columns',
			array(
				'label'       => esc_html__( 'Columns', 'connect-wcdp-elementor' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 6,
				'default'     => '',
				'placeholder' => esc_html__( 'Auto', 'connect-wcdp-elementor' ),
				'selectors'   => array(
					$list => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				),
				'description' => esc_html__( 'Set this per device — three amount buttons rarely fit on a phone.', 'connect-wcdp-elementor' ),
			)
		);

		$this->add_slider(
			'options_gap',
			esc_html__( 'Gap', 'connect-wcdp-elementor' ),
			$list,
			'gap: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
			)
		);

		$this->add_typography( 'options_typography', $label );

		$this->add_padding( 'options_padding', $label );

		$this->add_border_radius( 'options_radius', $label );

		$this->start_controls_tabs( 'options_tabs' );

		$this->start_controls_tab( 'options_tab_normal', array( 'label' => esc_html__( 'Normal', 'connect-wcdp-elementor' ) ) );

		$this->add_color( 'options_color', $label, array( 'global_default' => null ) );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'options_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $label,
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'options_border',
				'selector' => $label,
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'options_tab_hover', array( 'label' => esc_html__( 'Hover', 'connect-wcdp-elementor' ) ) );

		$hover = $this->sel( 'ul.wcdp_options > li > input:hover:not(:disabled):not(:checked) + label' );

		$this->add_color( 'options_color_hover', $hover, array( 'global_default' => null ) );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'options_background_hover',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $hover,
			)
		);

		$this->add_control(
			'options_border_color_hover',
			array(
				'label'     => esc_html__( 'Border Color', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $hover => 'border-color: {{VALUE}};' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'options_tab_selected', array( 'label' => esc_html__( 'Selected', 'connect-wcdp-elementor' ) ) );

		// WCDP paints both the label and any span inside it when checked, so
		// the override has to reach both or the amount text keeps the old colour.
		$checked = $this->sel( 'ul.wcdp_options > li > input:checked + label' )
			. ', ' . $this->sel( 'ul.wcdp_options > li > input:checked + label span' );

		$this->add_color( 'options_color_selected', $checked, array( 'global_default' => null ) );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'options_background_selected',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $this->sel( 'ul.wcdp_options > li > input:checked + label' ),
			)
		);

		$this->add_control(
			'options_border_color_selected',
			array(
				'label'     => esc_html__( 'Border Color', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $this->sel( 'ul.wcdp_options > li > input:checked + label' ) => 'border-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'options_hide_checkmark',
			array(
				'label'        => esc_html__( 'Hide Checkmark', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'selectors'    => array(
					$this->sel( '.wcdp_options > li > input:checked + label:after' ) => 'content: none;',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'      => 'options_box_shadow',
				'selector'  => $label,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'options_focus_color',
			array(
				'label'       => esc_html__( 'Keyboard Focus Ring', 'connect-wcdp-elementor' ),
				'type'        => Controls_Manager::COLOR,
				'selectors'   => array(
					$this->sel( 'ul.wcdp_options > li > input:focus-visible + label' ) => 'box-shadow: 0 0 0 2px #fff, 0 0 0 4px {{VALUE}};',
				),
				'description' => esc_html__( 'Keep this visible — it is how keyboard users see which amount is focused.', 'connect-wcdp-elementor' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Amount Input.
	 */
	private function register_amount_input_style(): void {
		$this->open_style_section( 'section_amount_input', esc_html__( 'Amount Input', 'connect-wcdp-elementor' ) );

		$input = $this->sel( 'input.wcdp-donation-amount' );

		$this->add_typography( 'amount_input_typography', $input );

		$this->add_color( 'amount_input_color', $input, array( 'global_default' => null ) );

		$this->add_control(
			'amount_input_background',
			array(
				'label'     => esc_html__( 'Background', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $input => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'amount_input_border',
				'selector' => $input,
			)
		);

		$this->add_border_radius( 'amount_input_radius', $input );

		$this->add_padding( 'amount_input_padding', $input );

		$this->add_slider(
			'amount_input_width',
			esc_html__( 'Width', 'connect-wcdp-elementor' ),
			$this->sel( '.wcdp-amount > input.wcdp-donation-amount' ),
			'width: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'ch', 'px', '%' ),
				'range'      => array( 'ch' => array( 'max' => 30 ) ),
			)
		);

		$this->add_control(
			'amount_input_focus_color',
			array(
				'label'     => esc_html__( 'Focus Border Color', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array( $this->sel( 'input.wcdp-donation-amount:focus' ) => 'border-color: {{VALUE}}; outline-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'currency_symbol_heading',
			array(
				'label'     => esc_html__( 'Currency Symbol', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$symbol = $this->sel( '.wcdp-amount .woocommerce-Price-currencySymbol' );

		$this->add_typography( 'currency_symbol_typography', $symbol );

		$this->add_color( 'currency_symbol_color', $symbol, array( 'global_default' => null ) );

		$this->end_controls_section();
	}

	/**
	 * Style → Amount Slider.
	 */
	private function register_range_style(): void {
		$this->open_style_section( 'section_range', esc_html__( 'Amount Slider', 'connect-wcdp-elementor' ) );

		$root = $this->root();

		$this->add_control(
			'range_notice',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => '<div class="elementor-control-field-description">' . esc_html__( 'Applies when the product\'s amount layout is set to "Input box with range slider" in WCDP.', 'connect-wcdp-elementor' ) . '</div>',
			)
		);

		$this->add_control(
			'range_thumb_color',
			array(
				'label'     => esc_html__( 'Handle Color', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_PRIMARY ),
				'selectors' => array( $root => '--wcdp-el-range-thumb-color: {{VALUE}};' ),
			)
		);

		$this->add_slider(
			'range_thumb_size',
			esc_html__( 'Handle Size', 'connect-wcdp-elementor' ),
			$root,
			'--wcdp-el-range-thumb-size: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'max' => 48 ) ),
			)
		);

		$this->add_control(
			'range_track_color',
			array(
				'label'     => esc_html__( 'Track Color', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $root => '--wcdp-el-range-track-color: {{VALUE}};' ),
			)
		);

		$this->add_slider(
			'range_track_height',
			esc_html__( 'Track Thickness', 'connect-wcdp-elementor' ),
			$root,
			'--wcdp-el-range-track-height: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'max' => 30 ) ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Labels & Headings.
	 */
	private function register_labels_style(): void {
		$this->open_style_section( 'section_labels', esc_html__( 'Labels & Headings', 'connect-wcdp-elementor' ) );

		$heading = $this->sel( '.wcdp-variation-heading' ) . ', ' . $this->sel( '.wcdp-row > label' );

		$this->add_typography( 'labels_typography', $heading );

		$this->add_color( 'labels_color', $heading );

		$this->add_margin( 'labels_margin', $heading );

		$this->add_control(
			'required_heading',
			array(
				'label'     => esc_html__( 'Required Marker', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_color(
			'required_color',
			$this->sel( 'abbr.required' ),
			array(
				'label'          => esc_html__( 'Color', 'connect-wcdp-elementor' ),
				'global_default' => null,
			)
		);

		$this->add_control(
			'required_hide',
			array(
				'label'        => esc_html__( 'Hide Asterisk', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'selectors'    => array( $this->sel( 'abbr.required' ) => 'display: none;' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Buttons.
	 */
	private function register_buttons_style(): void {
		$this->open_style_section( 'section_buttons', esc_html__( 'Buttons', 'connect-wcdp-elementor' ) );

		// Inside the form and, separately, the popup trigger which lives in the
		// widget wrapper even when the form itself is in the footer.
		$button  = $this->sel( 'button' ) . ', ' . $this->sel( '.wcdp-button' );
		$button .= ', {{WRAPPER}} .wcdp-modal-open';

		$this->add_typography( 'buttons_typography', $button );

		$this->start_controls_tabs( 'buttons_tabs' );

		$this->start_controls_tab( 'buttons_tab_normal', array( 'label' => esc_html__( 'Normal', 'connect-wcdp-elementor' ) ) );

		$this->add_color( 'buttons_color', $button, array( 'global_default' => null ) );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'buttons_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $button,
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'buttons_border',
				'selector' => $button,
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'buttons_tab_hover', array( 'label' => esc_html__( 'Hover', 'connect-wcdp-elementor' ) ) );

		$button_hover  = $this->sel( 'button:hover' ) . ', ' . $this->sel( '.wcdp-button:hover' );
		$button_hover .= ', {{WRAPPER}} .wcdp-modal-open:hover';
		$button_hover .= ', ' . $this->sel( 'button:focus-visible' ) . ', {{WRAPPER}} .wcdp-modal-open:focus-visible';

		$this->add_color( 'buttons_color_hover', $button_hover, array( 'global_default' => null ) );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'buttons_background_hover',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $button_hover,
			)
		);

		$this->add_control(
			'buttons_transition',
			array(
				'label'      => esc_html__( 'Transition Duration', 'connect-wcdp-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array(
					's' => array(
						'min'  => 0,
						'max'  => 2,
						'step' => 0.05,
					),
				),
				'selectors'  => array( $button => 'transition: all {{SIZE}}s ease;' ),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_padding( 'buttons_padding', $button, array( 'separator' => 'before' ) );

		$this->add_border_radius( 'buttons_radius', $button );

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'buttons_box_shadow',
				'selector' => $button,
			)
		);

		$this->add_slider(
			'buttons_row_gap',
			esc_html__( 'Gap in Button Row', 'connect-wcdp-elementor' ),
			$this->root(),
			'--wcdp-el-button-row-gap: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
			)
		);

		$this->add_control(
			'buttons_hide_arrows',
			array(
				'label'        => esc_html__( 'Hide Arrows', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'selectors'    => array( $this->sel( '.wcdp-arrow' ) => 'display: none;' ),
			)
		);

		$this->add_responsive_control(
			'trigger_align',
			array(
				'label'     => esc_html__( 'Trigger Button Alignment', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'separator' => 'before',
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'connect-wcdp-elementor' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'connect-wcdp-elementor' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'connect-wcdp-elementor' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .wcdp-el-form-trigger' => 'text-align: {{VALUE}};',
				),
				'condition' => array(
					'popup'  => '1',
					'button' => '1',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Checkout Fields.
	 */
	private function register_fields_style(): void {
		$this->open_style_section( 'section_fields', esc_html__( 'Checkout Fields', 'connect-wcdp-elementor' ) );

		$field  = $this->sel( '.woocommerce-input-wrapper input' );
		$field .= ', ' . $this->sel( '.woocommerce-input-wrapper textarea' );
		$field .= ', ' . $this->sel( '.woocommerce-input-wrapper select' );
		$field .= ', ' . $this->sel( '.select2-selection' );

		$label = $this->sel( '.form-row > label' );

		$this->add_typography( 'fields_label_typography', $label );

		$this->add_color( 'fields_label_color', $label );

		$this->add_control(
			'fields_input_heading',
			array(
				'label'     => esc_html__( 'Inputs', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_typography( 'fields_typography', $field );

		$this->add_color( 'fields_color', $field, array( 'global_default' => null ) );

		$this->add_control(
			'fields_background',
			array(
				'label'     => esc_html__( 'Background', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $field => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'fields_border',
				'selector' => $field,
			)
		);

		$this->add_border_radius( 'fields_radius', $field );

		$this->add_padding( 'fields_padding', $field );

		$this->add_control(
			'fields_focus_color',
			array(
				'label'     => esc_html__( 'Focus Border Color', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					$this->sel( '.woocommerce-input-wrapper input:focus' )      => 'border-color: {{VALUE}};',
					$this->sel( '.woocommerce-input-wrapper textarea:focus' )   => 'border-color: {{VALUE}};',
					$this->sel( '.woocommerce-input-wrapper select:focus' )     => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'fields_columns',
			array(
				'label'       => esc_html__( 'Field Columns', 'connect-wcdp-elementor' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 3,
				'default'     => '',
				'separator'   => 'before',
				'placeholder' => esc_html__( 'Theme default', 'connect-wcdp-elementor' ),
				'selectors'   => array(
					$this->sel( '#wcdp-step-2 .woocommerce-billing-fields__field-wrapper' ) => 'display: grid; grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr)); gap: var(--wcdp-el-field-gap, 12px);',
				),
				'description' => esc_html__( 'Lay the billing fields out in a grid. Set 1 on mobile.', 'connect-wcdp-elementor' ),
			)
		);

		$this->add_slider(
			'fields_gap',
			esc_html__( 'Column Gap', 'connect-wcdp-elementor' ),
			$this->root(),
			'--wcdp-el-field-gap: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 60 ) ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Fee Recovery.
	 */
	private function register_fee_recovery_style(): void {
		$this->open_style_section( 'section_fee_recovery', esc_html__( 'Fee Recovery Box', 'connect-wcdp-elementor' ) );

		$body     = $this->sel( '.wcdp-fee-recovery__body' );
		$checkbox = $this->sel( '.wcdp-fee-recovery__body-cover-checkbox' );

		$this->add_control(
			'fee_notice',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => '<div class="elementor-control-field-description">' . esc_html__( 'Applies when WCDP fee recovery is enabled.', 'connect-wcdp-elementor' ) . '</div>',
			)
		);

		$this->add_typography( 'fee_typography', $body );

		$this->add_color( 'fee_color', $body );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'fee_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $body,
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'fee_border',
				'selector' => $body,
			)
		);

		$this->add_border_radius( 'fee_radius', $body );

		$this->add_control(
			'fee_checkbox_color',
			array(
				'label'     => esc_html__( 'Checkbox Color', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					$checkbox                                                       => 'border-color: {{VALUE}};',
					$this->sel( '.wcdp-fee-recovery__body-cover-checkbox--svg' )     => 'stroke: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Popup.
	 */
	private function register_popup_style(): void {
		$this->open_style_section(
			'section_popup',
			esc_html__( 'Popup', 'connect-wcdp-elementor' ),
			array( 'condition' => array( 'popup' => '1' ) )
		);

		$popup = $this->popup_sel( '#wcdp-popup' );

		$this->add_control(
			'popup_notice',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => '<div class="elementor-control-field-description">' . esc_html__( 'WCDP prints the popup in the page footer, outside this widget. These rules therefore target the popup by id — safe, because WCDP allows one form per page.', 'connect-wcdp-elementor' ) . '</div>',
			)
		);

		$this->add_slider(
			'popup_width',
			esc_html__( 'Width', 'connect-wcdp-elementor' ),
			$popup,
			'max-width: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'px', '%', 'vw' ),
				'range'      => array( 'px' => array( 'max' => 1400 ) ),
			)
		);

		$this->add_slider(
			'popup_offset',
			esc_html__( 'Top Offset', 'connect-wcdp-elementor' ),
			$popup,
			'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}};',
			array( 'size_units' => array( 'vh', 'px', 'em' ) )
		);

		$this->add_padding( 'popup_padding', $popup );

		$this->add_border_radius( 'popup_radius', $popup );

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'popup_box_shadow',
				'selector' => $popup,
			)
		);

		$this->add_slider(
			'popup_overlay_blur',
			esc_html__( 'Overlay Blur', 'connect-wcdp-elementor' ),
			$this->popup_sel(),
			'backdrop-filter: blur({{SIZE}}{{UNIT}});',
			array(
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'max' => 30 ) ),
				'separator'  => 'before',
			)
		);

		$this->add_control(
			'popup_close_heading',
			array(
				'label'     => esc_html__( 'Close Button', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'popup_close_color',
			array(
				'label'     => esc_html__( 'Color', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $this->popup_sel( '.wcdp-close' ) => 'stroke: {{VALUE}};' ),
			)
		);

		$this->add_slider(
			'popup_close_size',
			esc_html__( 'Size', 'connect-wcdp-elementor' ),
			$this->popup_sel( '.wcdp-close' ),
			'width: {{SIZE}}{{UNIT}};',
			array(
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'max' => 80 ) ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style → Product Media & Text.
	 */
	private function register_media_style(): void {
		$this->open_style_section(
			'section_media',
			esc_html__( 'Product Media & Text', 'connect-wcdp-elementor' ),
			array(
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'show_image',
							'operator' => '===',
							'value'    => '1',
						),
						array(
							'name'     => 'show_title',
							'operator' => '===',
							'value'    => '1',
						),
						array(
							'name'     => 'show_description',
							'operator' => '===',
							'value'    => '1',
						),
						array(
							'name'     => 'show_short_description',
							'operator' => '===',
							'value'    => '1',
						),
					),
				),
			)
		);

		$image = $this->sel( '.wcdp-product-image img' );

		$this->add_slider(
			'media_image_width',
			esc_html__( 'Image Width', 'connect-wcdp-elementor' ),
			$image,
			'width: {{SIZE}}{{UNIT}}; height: auto;',
			array(
				'size_units' => array( '%', 'px' ),
				'condition'  => array( 'show_image' => '1' ),
			)
		);

		$this->add_border_radius( 'media_image_radius', $image, array( 'condition' => array( 'show_image' => '1' ) ) );

		$this->add_margin( 'media_image_margin', $this->sel( '.wcdp-product-image' ), array( 'condition' => array( 'show_image' => '1' ) ) );

		$this->add_control(
			'media_title_heading',
			array(
				'label'     => esc_html__( 'Title', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'show_title' => '1' ),
			)
		);

		$this->add_typography( 'media_title_typography', $this->sel( '.wcdp-title' ), array( 'condition' => array( 'show_title' => '1' ) ) );

		$this->add_color( 'media_title_color', $this->sel( '.wcdp-title' ), array( 'condition' => array( 'show_title' => '1' ) ) );

		$this->add_control(
			'media_description_heading',
			array(
				'label'      => esc_html__( 'Description', 'connect-wcdp-elementor' ),
				'type'       => Controls_Manager::HEADING,
				'separator'  => 'before',
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'show_description',
							'operator' => '===',
							'value'    => '1',
						),
						array(
							'name'     => 'show_short_description',
							'operator' => '===',
							'value'    => '1',
						),
					),
				),
			)
		);

		$description = $this->sel( '.wcdp-description' ) . ', ' . $this->sel( '.wcdp-short-description' );

		$this->add_typography( 'media_description_typography', $description );

		$this->add_color( 'media_description_color', $description );

		$this->end_controls_section();
	}

	/**
	 * Style → Notices, Loader & Branding.
	 */
	private function register_misc_style(): void {
		$this->open_style_section( 'section_misc', esc_html__( 'Notices, Loader & Branding', 'connect-wcdp-elementor' ) );

		$notice = $this->sel( '.woocommerce-error' ) . ', ' . $this->sel( '.woocommerce-info' ) . ', ' . $this->sel( '.wcdp-error-message' );

		$this->add_typography( 'notice_typography', $notice );

		$this->add_color( 'notice_color', $notice, array( 'global_default' => null ) );

		$this->add_control(
			'notice_background',
			array(
				'label'     => esc_html__( 'Notice Background', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $notice => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_border_radius( 'notice_radius', $notice );

		$this->add_padding( 'notice_padding', $notice );

		$this->add_control(
			'loader_heading',
			array(
				'label'     => esc_html__( 'Loader', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'loader_color',
			array(
				'label'     => esc_html__( 'Dots Color', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $this->sel( '.lds-ellipsis div' ) => 'background: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'branding_heading',
			array(
				'label'     => esc_html__( 'WCDP Branding', 'connect-wcdp-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_typography( 'branding_typography', $this->sel( '.wcdp-branding' ) );

		$this->add_color( 'branding_color', $this->sel( '.wcdp-branding' ) );

		$this->add_control(
			'branding_hide',
			array(
				'label'        => esc_html__( 'Hide Branding', 'connect-wcdp-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'selectors'    => array( $this->sel( '.wcdp-branding' ) => 'display: none;' ),
				'description'  => esc_html__( 'Whether WCDP shows this at all is a WCDP setting; this only hides it for this instance.', 'connect-wcdp-elementor' ),
			)
		);

		$this->end_controls_section();
	}

	/* ─────────────────────────── Render ─────────────────────────── */

	/**
	 * Render the widget.
	 */
	protected function render(): void {
		$settings   = $this->get_settings_for_display();
		$product_id = Product_Resolver::resolve( $settings );

		if ( ! $product_id || ! Product_Resolver::is_donable( $product_id ) ) {
			$this->render_placeholder( $this->get_unresolved_message() );

			return;
		}

		// WCDP refuses to run on `elementor_ajax` and returns a stand-in
		// sentence. Intercepting it here gives a clearer explanation than
		// echoing that sentence into the layout.
		if ( $this->is_widget_ajax_render() ) {
			$this->render_placeholder(
				esc_html__( 'WCDP cannot build a checkout form during an editor refresh. Reload the preview to see the form; Style tab changes apply live and need no reload.', 'connect-wcdp-elementor' )
			);

			return;
		}

		$atts = array(
			'id'    => $product_id,
			'style' => (string) ( $settings['style'] ?? '1' ),
		);

		if ( ! empty( $settings['popup'] ) ) {
			$atts['popup']  = '1';
			$atts['button'] = ! empty( $settings['button'] ) ? '1' : '0';
		}

		if ( ! empty( $settings['label'] ) ) {
			$atts['label'] = (string) $settings['label'];
		}

		foreach ( array(
			'show_image'             => 'image',
			'show_title'             => 'title',
			'show_description'       => 'description',
			'show_short_description' => 'short_description',
		) as $control => $attribute ) {
			if ( ! empty( $settings[ $control ] ) ) {
				$atts[ $attribute ] = '1';
			}
		}

		// WCDP echoes `className` onto the form root, which is the only
		// supported way to tag markup it moves to the footer.
		if ( 'yes' === ( $settings['modern_header'] ?? '' ) ) {
			$atts['className'] = 'wcdp-el-form--modern-header';
		}

		$html = Shortcode_Runner::run( 'wcdp_donation_form', $atts );

		if ( '' === trim( $html ) ) {
			// Expected when the popup renders with no trigger button: the form
			// itself was queued for the footer.
			return;
		}

		$is_error = false !== strpos( $html, 'wcdp-error-message' );

		$classes = array( 'wcdp-el-form-host' );

		if ( ! empty( $settings['popup'] ) && ! empty( $settings['button'] ) ) {
			$classes[] = 'wcdp-el-form-trigger';
		}

		printf( '<div class="%s">', esc_attr( implode( ' ', $classes ) ) );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- First-party WCDP shortcode output.
		echo '</div>';

		if ( $is_error && $this->is_editor() ) {
			printf(
				'<div class="wcdp-el-placeholder wcdp-el-placeholder--hint"><span class="wcdp-el-placeholder__text">%s</span></div>',
				esc_html__( 'WCDP returned an error. The usual cause is a second donation form on this page — WCDP permits only one — or donations not being enabled on the selected product.', 'connect-wcdp-elementor' )
			);
		}
	}

	/**
	 * Whether this render is Elementor refreshing a single widget over AJAX.
	 *
	 * @return bool
	 */
	private function is_widget_ajax_render(): bool {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context probe; Elementor verifies its own nonce.

		return 'elementor_ajax' === $action;
	}
}
