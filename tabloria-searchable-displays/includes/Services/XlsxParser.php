<?php
/**
 * XLSX upload handling
 *
 * @package Tabloria
 */

namespace Tabloria\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Placeholder parser that reports XLSX uploads as not yet supported.
 */
class XlsxParser {
	/**
	 * Return an error explaining that Excel uploads are not supported yet.
	 *
	 * @param string $path Absolute file path.
	 *
	 * @return array|WP_Error Always an error.
	 */
	public function parse( string $path ): array|WP_Error {
		return new WP_Error(
			'tabloria_xlsx_not_ready',
			__( 'Excel uploads are planned, but this MVP currently supports CSV files. Please export your spreadsheet as CSV and upload it again.', 'tabloria-searchable-displays' ),
			array( 'status' => 400 )
		);
	}
}
