<?php

namespace Publishly;

use Publishly\REST\DisplaysController;
use Publishly\REST\SettingsController;
use Publishly\REST\SourcesController;
use Publishly\REST\UploadController;
use Publishly\Services\FrontendRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	private Database $database;

	public function __construct() {
		$this->database = new Database();
	}

	public function init(): void {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_assets(): void {
		wp_register_style(
			'publishly-admin',
			PUBLISHLY_URL . 'assets/admin.css',
			array(),
			PUBLISHLY_VERSION
		);

		wp_register_script(
			'publishly-admin',
			PUBLISHLY_URL . 'assets/admin.js',
			array(),
			PUBLISHLY_VERSION,
			true
		);

		wp_register_style(
			'publishly-frontend',
			PUBLISHLY_URL . 'assets/frontend.css',
			array(),
			PUBLISHLY_VERSION
		);

		wp_register_script(
			'publishly-frontend',
			PUBLISHLY_URL . 'assets/frontend.js',
			array(),
			PUBLISHLY_VERSION,
			true
		);

		wp_register_script(
			'publishly-block',
			PUBLISHLY_URL . 'assets/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-block-editor' ),
			PUBLISHLY_VERSION,
			true
		);

		wp_localize_script(
			'publishly-block',
			'publishlyBlock',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'publishly/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'adminUrl' => esc_url_raw( admin_url( 'admin.php?page=' ) ),
			)
		);
	}

	public function register_block(): void {
		register_block_type(
			'publishly/display',
			array(
				'api_version'     => 2,
				'editor_script'   => 'publishly-block',
				'style'           => 'publishly-frontend',
				'view_script'     => 'publishly-frontend',
				'render_callback' => array( $this, 'render_display_block' ),
				'attributes'      => array(
					'displayId'     => array(
						'type'    => 'number',
						'default' => 0,
					),
					'showTitle'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'overrideTitle' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_block_type(
			'publishly/display-grid',
			array(
				'api_version'     => 2,
				'editor_script'   => 'publishly-block',
				'style'           => 'publishly-frontend',
				'view_script'     => 'publishly-frontend',
				'render_callback' => array( $this, 'render_display_grid_block' ),
				'attributes'      => array(
					'displayIds' => array(
						'type'    => 'array',
						'default' => array(),
					),
					'columns'    => array(
						'type'    => 'number',
						'default' => 2,
					),
					'gap'        => array(
						'type'    => 'string',
						'default' => 'normal',
					),
					'showTitles' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
	}

	public function register_shortcodes(): void {
		add_shortcode( 'publishly', array( $this, 'render_shortcode' ) );
		add_shortcode( 'publishly_display', array( $this, 'render_shortcode' ) );
	}

	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Publishly', 'publishly' ),
			__( 'Publishly', 'publishly' ),
			'manage_options',
			'publishly',
			array( $this, 'render_admin_app' ),
			'dashicons-screenoptions',
			56
		);

		add_submenu_page(
			'publishly',
			__( 'Create Display', 'publishly' ),
			__( 'Create Display', 'publishly' ),
			'manage_options',
			'publishly',
			array( $this, 'render_admin_app' )
		);

		add_submenu_page(
			'publishly',
			__( 'Published Displays', 'publishly' ),
			__( 'Published Displays', 'publishly' ),
			'manage_options',
			'publishly-displays',
			array( $this, 'render_admin_app' )
		);

		add_submenu_page(
			'publishly',
			__( 'Settings', 'publishly' ),
			__( 'Settings', 'publishly' ),
			'manage_options',
			'publishly-settings',
			array( $this, 'render_admin_app' )
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'publishly' ) ) {
			return;
		}

		wp_enqueue_style( 'publishly-admin' );
		wp_enqueue_script( 'publishly-admin' );
		wp_localize_script(
			'publishly-admin',
			'publishlyAdmin',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'publishly/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'currentPage' => sanitize_key( $_GET['page'] ?? 'publishly' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'adminUrl'    => esc_url_raw( admin_url( 'admin.php?page=' ) ),
				'blockName'   => 'publishly/display',
			)
		);
	}

	public function maybe_enqueue_frontend_assets(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$content = $post->post_content ?? '';
		if (
			has_block( 'publishly/display', $post )
			|| has_block( 'publishly/display-grid', $post )
			|| has_shortcode( $content, 'publishly' )
			|| has_shortcode( $content, 'publishly_display' )
		) {
			wp_enqueue_style( 'publishly-frontend' );
			wp_enqueue_script( 'publishly-frontend' );
		}
	}

	public function register_rest_routes(): void {
		( new SourcesController( $this->database ) )->register_routes();
		( new UploadController( $this->database ) )->register_routes();
		( new DisplaysController( $this->database ) )->register_routes();
		( new SettingsController() )->register_routes();
	}

	public function render_admin_app(): void {
		echo '<div class="wrap publishly-admin-wrap"><div id="publishly-admin-root"></div></div>';
	}

	public function render_display_block( array $attributes ): string {
		$display_id = absint( $attributes['displayId'] ?? 0 );
		if ( ! $display_id ) {
			return '';
		}

		return ( new FrontendRenderer( $this->database ) )->render( $display_id, $attributes );
	}

	public function render_display_grid_block( array $attributes ): string {
		return ( new FrontendRenderer( $this->database ) )->render_grid( $attributes );
	}

	public function render_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'             => 0,
				'show_title'     => 'true',
				'override_title' => '',
			),
			$atts,
			'publishly'
		);

		return ( new FrontendRenderer( $this->database ) )->render(
			absint( $atts['id'] ),
			array(
				'showTitle'     => 'false' !== $atts['show_title'],
				'overrideTitle' => sanitize_text_field( $atts['override_title'] ),
			)
		);
	}
}
