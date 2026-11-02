<?php

use Tabloria\Database;
use Tabloria\Services\CsvParser;
use Tabloria\Services\DisplayGenerator;
use Tabloria\Services\FrontendRenderer;
use Tabloria\Services\SpreadsheetSanitizer;

$wp_load = getenv( 'TABLORIA_WP_LOAD' ) ?: dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Unable to find wp-load.php. Set TABLORIA_WP_LOAD.\n" );
	exit( 1 );
}

require_once $wp_load;

if ( ! class_exists( SpreadsheetSanitizer::class ) ) {
	require_once dirname( __DIR__ ) . '/includes/Services/SpreadsheetSanitizer.php';
}
if ( ! class_exists( CsvParser::class ) ) {
	require_once dirname( __DIR__ ) . '/includes/Services/ColumnDetector.php';
	require_once dirname( __DIR__ ) . '/includes/Services/CsvParser.php';
}
if ( ! class_exists( DisplayGenerator::class ) ) {
	require_once dirname( __DIR__ ) . '/includes/Services/DisplayGenerator.php';
}
if ( ! class_exists( FrontendRenderer::class ) ) {
	require_once dirname( __DIR__ ) . '/includes/Database.php';
	require_once dirname( __DIR__ ) . '/includes/Services/FrontendRenderer.php';
}

class Tabloria_Test_Database extends Database {
	public function get_display( int $display_id ): ?array {
		return array(
			'id'           => $display_id,
			'source_id'    => 1,
			'name'         => '<script>Bad</script>',
			'display_type' => 'product_catalog',
			'status'       => 'published',
			'config'       => array(
				'title'          => '<script>Bad</script>',
				'displayType'    => 'product_catalog',
				'fieldRoles'     => array(
					'title' => 'name',
					'image' => 'image',
					'badge' => 'status',
				),
				'visibleFields'  => array( 'name', 'image', 'status' ),
				'defaultFilters' => array(),
				'visitorFilters' => array(),
				'search'         => array( 'enabled' => false ),
				'filters'        => array( 'enabled' => false ),
				'pagination'     => array( 'enabled' => false, 'perPage' => 12 ),
				'style'          => array(
					'accentColor'  => '#3525cd',
					'density'      => 'comfortable',
					'imageShape'   => 'rounded',
					'displayWidth' => 'normal',
				),
			),
		);
	}

	public function get_source( int $source_id ): ?array {
		return array(
			'id'      => $source_id,
			'columns' => array(
				array( 'key' => 'name', 'label' => 'Name', 'type' => 'text', 'confidence' => 1 ),
				array( 'key' => 'image', 'label' => 'Image', 'type' => 'image', 'confidence' => 1 ),
				array( 'key' => 'status', 'label' => 'Status', 'type' => 'status', 'confidence' => 1 ),
			),
		);
	}

	public function get_rows( int $source_id, int $limit = 0 ): array {
		return array(
			array(
				'name'   => '<script>alert(1)</script>',
				'image'  => 'javascript:alert(1)',
				'status' => '<img src=x onerror=alert(1)>',
			),
		);
	}
}

class Tabloria_Fixture_Test_Database extends Database {
	private array $columns;
	private array $rows;
	private array $config_overrides;

	public function __construct( array $columns, array $rows, array $config_overrides = array() ) {
		$this->columns          = $columns;
		$this->rows             = $rows;
		$this->config_overrides = $config_overrides;
	}

	public function get_display( int $display_id ): ?array {
		$config = array(
			'title'          => 'Suspicious JS Fixture',
			'displayType'    => 'product_catalog',
			'fieldRoles'     => array(
				'title' => 'title',
				'image' => 'image',
				'badge' => 'status',
			),
			'visibleFields'  => array( 'title', 'website', 'image', 'status', 'notes' ),
			'defaultFilters' => array(),
			'visitorFilters' => array( 'status' ),
			'search'         => array( 'enabled' => true ),
			'filters'        => array( 'enabled' => true ),
			'pagination'     => array( 'enabled' => false, 'perPage' => 12 ),
			'style'          => array(
				'accentColor'  => '#3525cd',
				'density'      => 'comfortable',
				'imageShape'   => 'rounded',
				'displayWidth' => 'normal',
			),
		);
		if ( ! empty( $this->config_overrides ) ) {
			$config = array_replace_recursive( $config, $this->config_overrides );
		}

		return array(
			'id'           => $display_id,
			'source_id'    => 1,
			'name'         => 'Suspicious JS Fixture',
			'display_type' => $config['displayType'],
			'status'       => 'published',
			'config'       => $config,
		);
	}

	public function get_source( int $source_id ): ?array {
		return array(
			'id'      => $source_id,
			'columns' => $this->columns,
		);
	}

	public function get_rows( int $source_id, int $limit = 0 ): array {
		return $this->rows;
	}
}

function tabloria_test_assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function tabloria_test_assert_same( mixed $expected, mixed $actual, string $message ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message . ' Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . '.' );
	}
}

function tabloria_test_assert_contains( string $needle, string $haystack, string $message ): void {
	if ( ! str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( $message . ' Missing: ' . $needle );
	}
}

function tabloria_test_assert_not_contains( string $needle, string $haystack, string $message ): void {
	if ( str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( $message . ' Found: ' . $needle );
	}
}

function tabloria_test_assert_no_match( string $pattern, string $haystack, string $message ): void {
	if ( preg_match( $pattern, $haystack ) ) {
		throw new RuntimeException( $message . ' Pattern: ' . $pattern );
	}
}

function tabloria_test_csv_file( array $rows ): string {
	$path   = tempnam( sys_get_temp_dir(), 'tabloria-csv-' );
	$handle = fopen( $path, 'wb' );
	foreach ( $rows as $row ) {
		fputcsv( $handle, $row );
	}
	fclose( $handle );

	return $path;
}

$tests = array(
	'sanitizer rejects unsafe typed values' => static function (): void {
		$sanitizer = new SpreadsheetSanitizer();

		tabloria_test_assert_same( '', $sanitizer->sanitize_value( 'javascript:alert(1)', 'url' ), 'Unsafe URL should be rejected.' );
		tabloria_test_assert_same( '', $sanitizer->sanitize_value( 'data:text/html,<svg onload=alert(1)>', 'image' ), 'Unsafe image URL should be rejected.' );
		tabloria_test_assert_same( '', $sanitizer->sanitize_value( 'not-an-email', 'email' ), 'Invalid email should be rejected.' );
		tabloria_test_assert_same( "'=HYPERLINK(\"https://evil.test\",\"x\")", $sanitizer->sanitize_value( '=HYPERLINK("https://evil.test","x")', 'text' ), 'Formula-looking text should be neutralized.' );
		tabloria_test_assert_same( '-12', $sanitizer->sanitize_value( '-12', 'number' ), 'Negative numbers should not be treated as formulas.' );
		tabloria_test_assert_same( 'https://example.com/photo.jpg', $sanitizer->sanitize_value( 'https://example.com/photo.jpg', 'image' ), 'Safe image URL should be preserved.' );
	},

	'csv parser sanitizes malicious cells by detected type' => static function (): void {
		$path = tabloria_test_csv_file(
			array(
				array( 'Name', 'Email', 'Website', 'Image', 'Status', 'Notes', 'Price' ),
				array( '<script>alert(1)</script>', 'bad@@example', 'javascript:alert(1)', 'data:text/html,<svg onload=alert(1)>', '<img src=x onerror=alert(1)>', '=HYPERLINK("https://evil.test","x")', '$12' ),
				array( 'Valid', 'person@example.com', 'https://example.com/page', 'https://example.com/photo.jpg', 'In Stock', '-12', '-5' ),
			)
		);

		$result = ( new CsvParser() )->parse( $path );
		unlink( $path );

		tabloria_test_assert_true( ! is_wp_error( $result ), 'Parser should accept a valid CSV shape.' );

		$row = $result['rows'][0];
		tabloria_test_assert_same( '', $row['name'], 'Script tags and their contents should be stripped from text cells.' );
		tabloria_test_assert_same( '', $row['email'], 'Invalid email should be blank.' );
		tabloria_test_assert_same( '', $row['website'], 'javascript: URL should be blank.' );
		tabloria_test_assert_same( '', $row['image'], 'data: image should be blank.' );
		tabloria_test_assert_same( '', $row['status'], 'HTML-only status should be blank.' );
		tabloria_test_assert_same( "'=HYPERLINK(\"https://evil.test\",\"x\")", $row['notes'], 'Formula-looking notes should be neutralized.' );

		tabloria_test_assert_same( 'person@example.com', $result['rows'][1]['email'], 'Valid email should remain.' );
		tabloria_test_assert_same( 'https://example.com/page', $result['rows'][1]['website'], 'Valid URL should remain.' );
		tabloria_test_assert_same( 'https://example.com/photo.jpg', $result['rows'][1]['image'], 'Valid image URL should remain.' );
		tabloria_test_assert_same( '-5', $result['rows'][1]['price'], 'Negative price value should remain text-safe.' );
	},

	'csv parser enforces column and binary limits' => static function (): void {
		$headers = array();
		for ( $i = 1; $i <= SpreadsheetSanitizer::MAX_COLUMNS + 1; $i++ ) {
			$headers[] = 'Column ' . $i;
		}

		$wide_path = tabloria_test_csv_file( array( $headers ) );
		$result    = ( new CsvParser() )->parse( $wide_path );
		unlink( $wide_path );

		tabloria_test_assert_true( is_wp_error( $result ), 'Too-wide CSV should be rejected.' );
		tabloria_test_assert_same( 'tabloria_csv_too_many_columns', $result->get_error_code(), 'Too-wide CSV should return the expected error code.' );

		$binary_path = tempnam( sys_get_temp_dir(), 'tabloria-bin-' );
		file_put_contents( $binary_path, "Name\0Value\nA,B\n" );
		$binary = ( new CsvParser() )->parse( $binary_path );
		unlink( $binary_path );

		tabloria_test_assert_true( is_wp_error( $binary ), 'Binary-looking CSV should be rejected.' );
		tabloria_test_assert_same( 'tabloria_csv_binary', $binary->get_error_code(), 'Binary-looking CSV should return the expected error code.' );
	},

	'sanitizer enforces row schema before storage' => static function (): void {
		$sanitizer = new SpreadsheetSanitizer();
		$columns   = $sanitizer->sanitize_columns(
			array(
				array( 'key' => 'name', 'label' => 'Name', 'type' => 'text', 'confidence' => 2 ),
				array( 'key' => 'image', 'label' => 'Image', 'type' => 'image', 'confidence' => 1 ),
				array( 'key' => 'weird', 'label' => 'Weird', 'type' => 'definitely-not-real', 'confidence' => -5 ),
			)
		);
		$rows      = $sanitizer->sanitize_rows(
			array(
				array(
					'name'  => '<b>Ada</b>',
					'image' => 'javascript:alert(1)',
					'weird' => '<script>bad()</script>',
					'extra' => 'drop me',
				),
			),
			$columns
		);

		tabloria_test_assert_same( 'text', $columns[2]['type'], 'Unknown column types should become text.' );
		tabloria_test_assert_same( 1.0, $columns[0]['confidence'], 'Confidence should be clamped to 1.' );
		tabloria_test_assert_same( 0.0, $columns[2]['confidence'], 'Confidence should be clamped to 0.' );
		tabloria_test_assert_same( array( 'name', 'image', 'weird' ), array_keys( $rows[0] ), 'Unknown row keys should be dropped.' );
		tabloria_test_assert_same( 'Ada', $rows[0]['name'], 'Text should be stripped of HTML.' );
		tabloria_test_assert_same( '', $rows[0]['image'], 'Unsafe image should be blanked.' );
		tabloria_test_assert_same( '', $rows[0]['weird'], 'Fallback text cells should strip script tags and contents.' );
	},

	'display config validation rejects unknown fields and shapes' => static function (): void {
		$source = array(
			'id'      => 12,
			'columns' => array(
				array( 'key' => 'name', 'label' => 'Name', 'type' => 'text' ),
				array( 'key' => 'status', 'label' => 'Status', 'type' => 'status' ),
			),
		);
		$config = ( new DisplayGenerator() )->sanitize_config(
			array(
				'displayType'    => '<script>',
				'title'          => '<b>Directory</b>',
				'fieldRoles'     => array( 'title' => 'name', 'image' => 'missing', 'onclick' => 'status' ),
				'visibleFields'  => array( 'name', 'missing', 'status', 'status' ),
				'visitorFilters' => array( 'status', 'missing' ),
				'defaultFilters' => array( 'not-array', array( 'field' => 'status', 'operator' => 'evil', 'value' => '<img>' ) ),
				'sort'           => 'not-array',
				'search'         => 'not-array',
				'filters'        => array( 'enabled' => true ),
				'pagination'     => array( 'enabled' => true, 'perPage' => 999 ),
				'style'          => 'not-array',
				'layoutOptions'  => array(
					'cards' => array(
						'urlDisplay'  => 'button',
						'urlLabel'    => '<b>View the Lesson</b>',
						'buttonStyle' => 'evil',
					),
				),
			),
			$source
		);

		tabloria_test_assert_same( 'searchable_listings', $config['displayType'], 'Unknown display type should fall back.' );
		tabloria_test_assert_same( 'Directory', $config['title'], 'Config title should strip tags.' );
		tabloria_test_assert_same( array( 'title' => 'name', 'image' => '' ), $config['fieldRoles'], 'Roles should be allowlisted and field-checked.' );
		tabloria_test_assert_same( array( 'name', 'status' ), $config['visibleFields'], 'Visible fields should be allowlisted and unique.' );
		tabloria_test_assert_same( array( 'status' ), $config['visitorFilters'], 'Visitor filters should be allowlisted.' );
		tabloria_test_assert_same( 'equals', $config['defaultFilters'][0]['operator'], 'Unknown filter operators should fall back.' );
		tabloria_test_assert_same( 100, $config['pagination']['perPage'], 'Per-page value should be capped.' );
		tabloria_test_assert_same( 'button', $config['layoutOptions']['cards']['urlDisplay'], 'Card URL display should allow buttons.' );
		tabloria_test_assert_same( 'View the Lesson', $config['layoutOptions']['cards']['urlLabel'], 'Card URL label should be sanitized.' );
		tabloria_test_assert_same( 'theme', $config['layoutOptions']['cards']['buttonStyle'], 'Unknown card button styles should fall back.' );
	},

	'frontend renderer omits unsafe image URLs and escapes CSV text' => static function (): void {
		$html  = ( new FrontendRenderer( new Tabloria_Test_Database() ) )->render( 9 );
		$lower = strtolower( $html );

		tabloria_test_assert_not_contains( '<script', $lower, 'Rendered HTML should not contain script tags.' );
		tabloria_test_assert_not_contains( 'javascript:', $lower, 'Rendered HTML should not contain javascript URLs.' );
		tabloria_test_assert_not_contains( 'onerror', $lower, 'Rendered HTML should not contain event handlers from CSV.' );
		tabloria_test_assert_not_contains( '<img', $lower, 'Invalid image URL should not render an img tag.' );
	},

	'js payload fixture is sanitized before rendering' => static function (): void {
		$path   = dirname( __DIR__ ) . '/samples/suspicious-js-payloads.csv';
		$result = ( new CsvParser() )->parse( $path );

		tabloria_test_assert_true( ! is_wp_error( $result ), 'Suspicious JS fixture should parse.' );
		tabloria_test_assert_same( '', $result['rows'][0]['title'], 'Script title should be stripped during parsing.' );
		tabloria_test_assert_same( '', $result['rows'][0]['website'], 'javascript: URL should be blank during parsing.' );
		tabloria_test_assert_same( '', $result['rows'][0]['image'], 'javascript: image should be blank during parsing.' );
		tabloria_test_assert_same( '', $result['rows'][0]['status'], 'Event-handler HTML should be stripped during parsing.' );

		$html  = ( new FrontendRenderer( new Tabloria_Fixture_Test_Database( $result['columns'], $result['rows'] ) ) )->render( 77 );
		$lower = strtolower( $html );

		tabloria_test_assert_not_contains( '<script', $lower, 'Rendered fixture should not contain script tags.' );
		tabloria_test_assert_not_contains( 'onerror', $lower, 'Rendered fixture should not contain event handlers.' );
		tabloria_test_assert_not_contains( 'data:text/html', $lower, 'Rendered fixture should not contain data HTML URLs.' );
		tabloria_test_assert_no_match( '/(?:src|href)\s*=\s*([\"\'])\s*javascript:/i', $html, 'Rendered fixture should not contain executable javascript URLs.' );
		tabloria_test_assert_contains( 'class="tabloria-card__link"', $html, 'Valid URL fields should render as card links.' );
		tabloria_test_assert_contains( 'href="https://example.com/safe-page"', $html, 'Valid URL fields should render as clickable links.' );
		tabloria_test_assert_contains( '>Website</a>', $html, 'Website URL links should use the column label.' );
		tabloria_test_assert_contains( '2 &lt; 3 &amp;&amp; 5 &gt; 4', $html, 'Plain less-than/greater-than text should be escaped in HTML.' );
		tabloria_test_assert_contains( '&#039;=HYPERLINK(&quot;javascript:alert(1)&quot;,&quot;Click&quot;)', $html, 'Formula-like JavaScript text should be neutralized and escaped.' );
	},

	'frontend renderer can render card URL fields as styled buttons' => static function (): void {
		$columns = array(
			array( 'key' => 'title', 'label' => 'Title', 'type' => 'text' ),
			array( 'key' => 'website', 'label' => 'Lesson Link', 'type' => 'url' ),
			array( 'key' => 'status', 'label' => 'Status', 'type' => 'status' ),
			array( 'key' => 'notes', 'label' => 'Notes', 'type' => 'text' ),
		);
		$rows = array(
			array(
				'title'   => 'Lesson 1',
				'website' => 'https://example.com/lesson-1',
				'status'  => 'Published',
				'notes'   => 'Intro lesson',
			),
		);
		$html = ( new FrontendRenderer(
			new Tabloria_Fixture_Test_Database(
				$columns,
				$rows,
				array(
					'layoutOptions' => array(
						'cards' => array(
							'urlDisplay'  => 'button',
							'urlLabel'    => 'View the Lesson',
							'buttonStyle' => 'primary',
						),
					),
				)
			)
		) )->render( 78 );

		tabloria_test_assert_contains( 'class="tabloria-card__button tabloria-card__button--primary"', $html, 'Card URL fields should render with button classes.' );
		tabloria_test_assert_contains( 'href="https://example.com/lesson-1"', $html, 'Card URL buttons should keep the row-specific URL.' );
		tabloria_test_assert_contains( '>View the Lesson</a>', $html, 'Card URL buttons should use the custom label.' );
	},
);

$tests['hide rows until search defaults and database persistence'] = static function (): void {
	$generator = new DisplayGenerator();
	$source = array( 'columns' => array( array( 'key' => 'title', 'label' => 'Title', 'type' => 'text' ) ) );
	$config = $generator->generate( $source, 'simple_data_table' );
	tabloria_test_assert_same( false, $config['search']['hideRowsUntilSearch'], 'New displays must default to OFF.' );
	$legacy = $generator->sanitize_config( array( 'search' => array( 'enabled' => true ) ), $source );
	tabloria_test_assert_same( false, $legacy['search']['hideRowsUntilSearch'], 'Legacy displays must default to OFF.' );
	$config['search']['hideRowsUntilSearch'] = true;
	$config = $generator->sanitize_config( $config, $source );
	$database = new Database();
	$id = $database->create_display( array( 'name' => 'Search setting test', 'config' => $config ) );
	try {
		tabloria_test_assert_same( true, $database->get_display( $id )['config']['search']['hideRowsUntilSearch'], 'Enabled setting must survive save/load.' );
		$config['search']['hideRowsUntilSearch'] = false;
		$database->update_display( $id, array( 'config' => $generator->sanitize_config( $config, $source ) ) );
		tabloria_test_assert_same( false, $database->get_display( $id )['config']['search']['hideRowsUntilSearch'], 'Disabled setting must survive update/load.' );
	} finally {
		$database->delete_display( $id );
	}
};

$tests['hide rows until search applies to initial HTML for tables and cards'] = static function (): void {
	$columns = array( array( 'key' => 'title', 'label' => 'Title', 'type' => 'text' ) );
	$rows = array( array( 'title' => '123 Alpha' ), array( 'title' => '12345 Beta' ) );
	foreach ( array( 'simple_data_table', 'product_catalog' ) as $type ) {
		foreach ( array( null, false, true ) as $hide ) {
			foreach ( array( false, true ) as $search_enabled ) {
				$search = array( 'enabled' => $search_enabled );
				if ( null !== $hide ) {
					$search['hideRowsUntilSearch'] = $hide;
				}
				$html = ( new FrontendRenderer( new Tabloria_Fixture_Test_Database( $columns, $rows, array(
					'displayType' => $type,
					'visibleFields' => array( 'title' ),
					'search' => $search,
				) ) ) )->render( 79 );
				$expected = $hide && $search_enabled;
				tabloria_test_assert_contains( 'data-hide-rows-until-search="' . ( $expected ? 'true' : 'false' ) . '"', $html, 'Frontend flag must match the search configuration.' );
				preg_match_all( '/<(?:tr|article)\b[^>]*data-tabloria-item[^>]*>/', $html, $items );
				tabloria_test_assert_same( 2, count( $items[0] ), 'Keep rows available to the existing search logic.' );
				foreach ( $items[0] as $item ) {
					tabloria_test_assert_same( (bool) $expected, str_contains( $item, ' hidden' ), 'Rows must have the correct visibility before JavaScript runs.' );
				}
			}
		}
	}
};

$tests['display control options preserve legacy defaults and render configured values'] = static function (): void {
	$generator = new DisplayGenerator();
	$source = array(
		'columns' => array(
			array( 'key' => 'title', 'label' => 'Title', 'type' => 'text' ),
			array( 'key' => 'status', 'label' => 'Status', 'type' => 'status' ),
		),
	);
	$legacy = $generator->sanitize_config( array( 'search' => array( 'enabled' => true ), 'filters' => array( 'enabled' => true ), 'pagination' => array( 'enabled' => true ) ), $source );
	tabloria_test_assert_same( 'Search all fields...', $legacy['search']['placeholder'], 'Legacy Displays should keep the original search placeholder.' );
	tabloria_test_assert_same( true, $legacy['filters']['showColumnNames'], 'Legacy Displays should retain column prefixes.' );
	tabloria_test_assert_same( true, $legacy['pagination']['showResultCount'], 'Legacy Displays should retain result counts.' );

	$columns = $source['columns'];
	$rows = array( array( 'title' => 'Offer one', 'status' => 'Available' ) );
	$html = ( new FrontendRenderer( new Tabloria_Fixture_Test_Database( $columns, $rows, array(
		'visitorFilters' => array( 'status' ),
		'search' => array( 'enabled' => true, 'placeholder' => 'Search all offers' ),
		'filters' => array( 'enabled' => true, 'showColumnNames' => false, 'hoverColor' => '#1256a1' ),
		'pagination' => array( 'enabled' => true, 'perPage' => 12, 'showResultCount' => false ),
	) ) ) )->render( 80 );
	tabloria_test_assert_contains( 'placeholder="Search all offers"', $html, 'Configured search placeholder should render.' );
	tabloria_test_assert_contains( '>Available</option>', $html, 'Filter values should render without their column label when requested.' );
	tabloria_test_assert_not_contains( 'Status: Available', $html, 'Column label prefix should be omitted when disabled.' );
	tabloria_test_assert_contains( '--tabloria-dropdown-hover: #1256a1', $html, 'Configured dropdown color should render as a CSS variable.' );
	tabloria_test_assert_contains( 'data-show-result-count="false"', $html, 'Result count visibility should be passed to the frontend.' );
	tabloria_test_assert_contains( 'class="tabloria-display__status" aria-live="polite" hidden', $html, 'Hidden result count should not flash before JavaScript loads.' );
};

$tests['custom empty message is sanitized and rendered'] = static function (): void {
	$source = array( 'columns' => array( array( 'key' => 'title', 'label' => 'Title', 'type' => 'text' ) ) );
	$config = ( new DisplayGenerator() )->sanitize_config(
		array( 'search' => array( 'enabled' => true, 'emptyMessage' => '<b>Nothing here</b>' ) ),
		$source
	);
	tabloria_test_assert_same( 'Nothing here', $config['search']['emptyMessage'], 'Empty message should be sanitized.' );

	$html = ( new FrontendRenderer( new Tabloria_Fixture_Test_Database(
		$source['columns'],
		array( array( 'title' => 'One' ) ),
		array( 'search' => array( 'enabled' => true, 'emptyMessage' => 'Try another category.' ) )
	) ) )->render( 81 );
	tabloria_test_assert_contains( 'Try another category.', $html, 'Configured empty message should render.' );
};

$tests['placement filter reuses one display with different exact values'] = static function (): void {
	$columns = array(
		array( 'key' => 'title', 'label' => 'Title', 'type' => 'text' ),
		array( 'key' => 'category', 'label' => 'Category', 'type' => 'status' ),
	);
	$rows = array(
		array( 'title' => 'Alpha item', 'category' => 'Alpha' ),
		array( 'title' => 'Beta item', 'category' => 'Beta' ),
	);
	$renderer = new FrontendRenderer( new Tabloria_Fixture_Test_Database( $columns, $rows ) );
	$html = $renderer->render( 82, array( 'filterField' => 'category', 'filterValue' => 'Beta' ) );
	tabloria_test_assert_contains( 'Beta item', $html, 'Selected placement value should remain.' );
	tabloria_test_assert_not_contains( 'Alpha item', $html, 'Other placement values should be excluded.' );

	$preview = $renderer->render_preview_data( 82, array( 'filterField' => 'category', 'filterValue' => 'alpha' ) );
	tabloria_test_assert_same( 1, count( $preview['rows'] ), 'Preview should use the same page-specific filter.' );
	tabloria_test_assert_same( 'Alpha item', $preview['rows'][0]['title'], 'Placement matching should be case-insensitive.' );

	$invalid = $renderer->render( 82, array( 'filterField' => 'missing', 'filterValue' => 'Beta' ) );
	tabloria_test_assert_contains( 'Alpha item', $invalid, 'Unknown placement fields must be ignored.' );
	tabloria_test_assert_contains( 'Beta item', $invalid, 'Ignoring an invalid filter must not hide rows.' );
};

$tests['coupon display supports coupon and deal-only rows'] = static function (): void {
	$database = new Database();
	$database_display_id = $database->create_display(
		array(
			'name' => 'Coupon type persistence',
			'display_type' => 'coupon_display',
			'config' => array( 'displayType' => 'coupon_display' ),
		)
	);
	try {
		tabloria_test_assert_same( 'coupon_display', $database->get_display( $database_display_id )['display_type'], 'Coupon display type should survive database storage.' );
	} finally {
		$database->delete_display( $database_display_id );
	}

	$columns = array(
		array( 'key' => 'offer', 'label' => 'Offer', 'type' => 'text' ),
		array( 'key' => 'coupon_code', 'label' => 'Coupon Code', 'type' => 'text' ),
		array( 'key' => 'affiliate_url', 'label' => 'Affiliate URL', 'type' => 'url' ),
	);
	$generated = ( new DisplayGenerator() )->generate( array( 'columns' => $columns ), 'coupon_display' );
	tabloria_test_assert_same( 'offer', $generated['fieldRoles']['title'], 'Coupon offer title should be detected.' );
	tabloria_test_assert_same( 'coupon_code', $generated['fieldRoles']['coupon_code'], 'Coupon code column should be detected.' );
	tabloria_test_assert_same( 'affiliate_url', $generated['fieldRoles']['affiliate_url'], 'Affiliate URL column should be detected.' );
	$rows = array(
		array( 'offer' => 'Coupon offer', 'coupon_code' => 'SAVE20', 'affiliate_url' => 'https://example.com/coupon' ),
		array( 'offer' => 'Link-only deal', 'coupon_code' => '', 'affiliate_url' => 'https://example.com/deal' ),
	);
	$html = ( new FrontendRenderer( new Tabloria_Fixture_Test_Database(
		$columns,
		$rows,
		array(
			'displayType' => 'coupon_display',
			'fieldRoles' => array( 'title' => 'offer', 'coupon_code' => 'coupon_code', 'affiliate_url' => 'affiliate_url' ),
			'visibleFields' => array( 'offer', 'coupon_code', 'affiliate_url' ),
			'layoutOptions' => array(
				'coupons' => array(
					'showCouponLabel' => 'Reveal offer',
					'copyCodeLabel' => 'Copy it',
					'dealLabel' => 'Visit website',
					'copiedLabel' => 'Copied now',
				),
			),
		)
	) ) )->render( 83 );

	tabloria_test_assert_contains( 'data-tabloria-show-coupon', $html, 'Coupon row should have a reveal control.' );
	tabloria_test_assert_contains( 'href="https://example.com/coupon"', $html, 'Reveal control should open the affiliate link.' );
	tabloria_test_assert_contains( 'target="_blank"', $html, 'Affiliate links should open in a new tab.' );
	tabloria_test_assert_contains( 'data-coupon-code="SAVE20"', $html, 'Coupon code should be available to the copy control.' );
	tabloria_test_assert_contains( '>Copy it</button>', $html, 'Copy text should be configurable.' );
	tabloria_test_assert_contains( 'href="https://example.com/deal"', $html, 'Deal-only row should keep its affiliate link.' );
	tabloria_test_assert_contains( '>Visit website</a>', $html, 'Deal-only button text should be configurable.' );
};

$failures = 0;
foreach ( $tests as $name => $test ) {
	try {
		$test();
		echo "PASS {$name}\n";
	} catch ( Throwable $exception ) {
		$failures++;
		echo "FAIL {$name}: {$exception->getMessage()}\n";
	}
}

if ( $failures > 0 ) {
	echo "{$failures} unit test(s) failed.\n";
	exit( 1 );
}

echo count( $tests ) . " unit tests passed.\n";
