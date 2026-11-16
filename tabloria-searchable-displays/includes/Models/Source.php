<?php
/**
 * Source model
 *
 * @package Tabloria
 */

namespace Tabloria\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value object describing an imported spreadsheet source.
 */
class Source {
	/**
	 * Source ID.
	 *
	 * @var int
	 */
	public int $id;
	/**
	 * Source name.
	 *
	 * @var string
	 */
	public string $name;
	/**
	 * Original uploaded filename.
	 *
	 * @var string
	 */
	public string $original_filename;
	/**
	 * Source file type.
	 *
	 * @var string
	 */
	public string $file_type;
	/**
	 * Number of imported rows.
	 *
	 * @var int
	 */
	public int $row_count;
	/**
	 * Detected column definitions.
	 *
	 * @var array
	 */
	public array $columns;

	/**
	 * Fill the model from an array.
	 *
	 * @param array $data Values keyed by property name.
	 */
	public function __construct( array $data ) {
		$this->id                = absint( $data['id'] ?? 0 );
		$this->name              = sanitize_text_field( $data['name'] ?? '' );
		$this->original_filename = sanitize_file_name( $data['original_filename'] ?? '' );
		$this->file_type         = sanitize_key( $data['file_type'] ?? 'csv' );
		$this->row_count         = absint( $data['row_count'] ?? 0 );
		$this->columns           = is_array( $data['columns'] ?? null ) ? $data['columns'] : array();
	}
}
