<?php

namespace Tabloria\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Display {
	public int $id;
	public int $source_id;
	public string $name;
	public string $display_type;
	public array $config;
	public string $status;

	public function __construct( array $data ) {
		$this->id           = absint( $data['id'] ?? 0 );
		$this->source_id    = absint( $data['source_id'] ?? 0 );
		$this->name         = sanitize_text_field( $data['name'] ?? '' );
		$this->display_type = sanitize_key( $data['display_type'] ?? 'searchable_listings' );
		$this->config       = is_array( $data['config'] ?? null ) ? $data['config'] : array();
		$this->status       = sanitize_key( $data['status'] ?? 'draft' );
	}
}
