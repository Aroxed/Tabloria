<?php

namespace Publishly\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Source {
	public int $id;
	public string $name;
	public string $original_filename;
	public string $file_type;
	public int $row_count;
	public array $columns;

	public function __construct( array $data ) {
		$this->id                = absint( $data['id'] ?? 0 );
		$this->name              = sanitize_text_field( $data['name'] ?? '' );
		$this->original_filename = sanitize_file_name( $data['original_filename'] ?? '' );
		$this->file_type         = sanitize_key( $data['file_type'] ?? 'csv' );
		$this->row_count         = absint( $data['row_count'] ?? 0 );
		$this->columns           = is_array( $data['columns'] ?? null ) ? $data['columns'] : array();
	}
}
