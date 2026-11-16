<?php

namespace Tabloria\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class XlsxParser {
	public function parse( string $path ): array|WP_Error {
		return new WP_Error(
			'tabloria_xlsx_not_ready',
			__( 'Excel uploads are planned, but this MVP currently supports CSV files. Please export your spreadsheet as CSV and upload it again.', 'tabloria-searchable-displays' ),
			array( 'status' => 400 )
		);
	}
}
