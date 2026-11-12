<?php

namespace Tabloria;

use Tabloria\REST\DisplaysController;
use Tabloria\REST\SettingsController;
use Tabloria\REST\SourcesController;
use Tabloria\REST\UploadController;
use Tabloria\Services\FrontendRenderer;
use Tabloria\Services\SourceSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	private Database $database;

	public function __construct() {
		$this->database = new Database();
	}

	public function init(): void {
		( new SourceSync( $this->database ) )->register_hooks();
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
			'tabloria-admin',
			TABLORIA_URL . 'assets/admin.css',
			array(),
			TABLORIA_VERSION
		);

		wp_register_script(
			'tabloria-admin',
			TABLORIA_URL . 'assets/admin.js',
			array(),
			TABLORIA_VERSION,
			true
		);

		wp_register_style(
			'tabloria-frontend',
			TABLORIA_URL . 'assets/frontend.css',
			array(),
			TABLORIA_VERSION
		);

		wp_register_script(
			'tabloria-frontend',
			TABLORIA_URL . 'assets/frontend.js',
			array(),
			TABLORIA_VERSION,
			true
		);

		wp_register_script(
			'tabloria-block',
			TABLORIA_URL . 'assets/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-block-editor' ),
			TABLORIA_VERSION,
			true
		);

		wp_localize_script(
			'tabloria-block',
			'tabloriaBlock',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'tabloria/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'adminUrl' => esc_url_raw( admin_url( 'admin.php?page=' ) ),
			)
		);
	}

	public function register_block(): void {
		register_block_type(
			'tabloria/display',
			array(
				'api_version'     => 2,
				'editor_script'   => 'tabloria-block',
				'style'           => 'tabloria-frontend',
				'view_script'     => 'tabloria-frontend',
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
					'filterField'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'filterValue'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_block_type(
			'tabloria/display-grid',
			array(
				'api_version'     => 2,
				'editor_script'   => 'tabloria-block',
				'style'           => 'tabloria-frontend',
				'view_script'     => 'tabloria-frontend',
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
		add_shortcode( 'tabloria', array( $this, 'render_shortcode' ) );
		add_shortcode( 'tabloria_display', array( $this, 'render_shortcode' ) );
	}

	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Tabloria', 'tabloria-searchable-displays' ),
			__( 'Tabloria', 'tabloria-searchable-displays' ),
			'manage_options',
			'tabloria',
			array( $this, 'render_admin_app' ),
			'dashicons-screenoptions',
			56
		);

		add_submenu_page(
			'tabloria',
			__( 'Create Display', 'tabloria-searchable-displays' ),
			__( 'Create Display', 'tabloria-searchable-displays' ),
			'manage_options',
			'tabloria',
			array( $this, 'render_admin_app' )
		);

		add_submenu_page(
			'tabloria',
			__( 'Published Displays', 'tabloria-searchable-displays' ),
			__( 'Published Displays', 'tabloria-searchable-displays' ),
			'manage_options',
			'tabloria-displays',
			array( $this, 'render_admin_app' )
		);

		add_submenu_page(
			'tabloria',
			__( 'Settings', 'tabloria-searchable-displays' ),
			__( 'Settings', 'tabloria-searchable-displays' ),
			'manage_options',
			'tabloria-settings',
			array( $this, 'render_admin_app' )
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'tabloria' ) ) {
			return;
		}

		wp_enqueue_style( 'tabloria-admin' );
		wp_enqueue_script( 'tabloria-admin' );
		wp_localize_script(
			'tabloria-admin',
			'tabloriaAdmin',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'tabloria/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'currentPage' => sanitize_key( $_GET['page'] ?? 'tabloria' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'adminUrl'    => esc_url_raw( admin_url( 'admin.php?page=' ) ),
				'blockName'   => 'tabloria/display',
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
			has_block( 'tabloria/display', $post )
			|| has_block( 'tabloria/display-grid', $post )
			|| has_shortcode( $content, 'tabloria' )
			|| has_shortcode( $content, 'tabloria_display' )
		) {
			wp_enqueue_style( 'tabloria-frontend' );
			wp_enqueue_script( 'tabloria-frontend' );
		}
	}

	public function register_rest_routes(): void {
		( new SourcesController( $this->database ) )->register_routes();
		( new UploadController( $this->database ) )->register_routes();
		( new DisplaysController( $this->database ) )->register_routes();
		( new SettingsController() )->register_routes();
	}

	public function render_admin_app(): void {
		echo '<div class="wrap tabloria-admin-wrap"><div id="tabloria-admin-root"></div></div>';
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
				'filter_field'   => '',
				'filter_value'   => '',
			),
			$atts,
			'tabloria'
		);

		return ( new FrontendRenderer( $this->database ) )->render(
			absint( $atts['id'] ),
			array(
				'showTitle'     => 'false' !== $atts['show_title'],
				'overrideTitle' => sanitize_text_field( $atts['override_title'] ),
				'filterField'   => sanitize_key( $atts['filter_field'] ),
				'filterValue'   => sanitize_text_field( $atts['filter_value'] ),
			)
		);
	}
}
