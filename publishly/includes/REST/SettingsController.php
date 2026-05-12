<?php

namespace Publishly\REST;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsController {
	public function register_routes(): void {
		register_rest_route(
			'publishly/v1',
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function show(): WP_REST_Response {
		return new WP_REST_Response( $this->settings() );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response {
		$accent = sanitize_hex_color( $request->get_param( 'defaultAccentColor' ) ?: '#3525cd' ) ?: '#3525cd';
		$rows   = max( 1, min( 100, absint( $request->get_param( 'defaultRowsPerPage' ) ?: 12 ) ) );
		$delete = rest_sanitize_boolean( $request->get_param( 'deleteDataOnUninstall' ) );

		update_option( 'publishly_default_accent_color', $accent );
		update_option( 'publishly_default_rows_per_page', $rows );
		update_option( 'publishly_delete_data_on_uninstall', $delete );

		return new WP_REST_Response( $this->settings() );
	}

	private function settings(): array {
		return array(
			'defaultAccentColor'      => get_option( 'publishly_default_accent_color', '#3525cd' ),
			'defaultRowsPerPage'      => absint( get_option( 'publishly_default_rows_per_page', 12 ) ),
			'deleteDataOnUninstall'   => (bool) get_option( 'publishly_delete_data_on_uninstall', false ),
		);
	}
}
