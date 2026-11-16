<?php
/**
 * Display model
 *
 * @package Tabloria
 */

namespace Tabloria\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value object describing a saved display.
 */
class Display {
	/**
	 * Display ID.
	 *
	 * @var int
	 */
	public int $id;
	/**
	 * ID of the source the display reads.
	 *
	 * @var int
	 */
	public int $source_id;
	/**
	 * Display name.
	 *
	 * @var string
	 */
	public string $name;
	/**
	 * Display type slug.
	 *
	 * @var string
	 */
	public string $display_type;
	/**
	 * Display configuration.
	 *
	 * @var array
	 */
	public array $config;
	/**
	 * Publication status.
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Fill the model from an array.
	 *
	 * @param array $data Values keyed by property name.
	 */
	public function __construct( array $data ) {
		$this->id           = absint( $data['id'] ?? 0 );
		$this->source_id    = absint( $data['source_id'] ?? 0 );
		$this->name         = sanitize_text_field( $data['name'] ?? '' );
		$this->display_type = sanitize_key( $data['display_type'] ?? 'searchable_listings' );
		$this->config       = is_array( $data['config'] ?? null ) ? $data['config'] : array();
		$this->status       = sanitize_key( $data['status'] ?? 'draft' );
	}
}
