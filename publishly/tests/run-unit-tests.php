<?php

use Publishly\Database;
use Publishly\Services\CsvParser;
use Publishly\Services\DisplayGenerator;
use Publishly\Services\FrontendRenderer;
use Publishly\Services\SpreadsheetSanitizer;

$wp_load = getenv( 'PUBLISHLY_WP_LOAD' ) ?: dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Unable to find wp-load.php. Set PUBLISHLY_WP_LOAD.\n" );
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

class Publishly_Test_Database extends Database {
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

class Publishly_Fixture_Test_Database extends Database {
	private array $columns;
	private array $rows;

	public function __construct( array $columns, array $rows ) {
		$this->columns = $columns;
		$this->rows    = $rows;
	}

	public function get_display( int $display_id ): ?array {
		return array(
			'id'           => $display_id,
			'source_id'    => 1,
			'name'         => 'Suspicious JS Fixture',
			'display_type' => 'product_catalog',
			'status'       => 'published',
			'config'       => array(
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
			),
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

function publishly_test_assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function publishly_test_assert_same( mixed $expected, mixed $actual, string $message ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message . ' Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . '.' );
	}
}

function publishly_test_assert_contains( string $needle, string $haystack, string $message ): void {
	if ( ! str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( $message . ' Missing: ' . $needle );
	}
}

function publishly_test_assert_not_contains( string $needle, string $haystack, string $message ): void {
	if ( str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( $message . ' Found: ' . $needle );
	}
}

function publishly_test_assert_no_match( string $pattern, string $haystack, string $message ): void {
	if ( preg_match( $pattern, $haystack ) ) {
		throw new RuntimeException( $message . ' Pattern: ' . $pattern );
	}
}

function publishly_test_csv_file( array $rows ): string {
	$path   = tempnam( sys_get_temp_dir(), 'publishly-csv-' );
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

		publishly_test_assert_same( '', $sanitizer->sanitize_value( 'javascript:alert(1)', 'url' ), 'Unsafe URL should be rejected.' );
		publishly_test_assert_same( '', $sanitizer->sanitize_value( 'data:text/html,<svg onload=alert(1)>', 'image' ), 'Unsafe image URL should be rejected.' );
		publishly_test_assert_same( '', $sanitizer->sanitize_value( 'not-an-email', 'email' ), 'Invalid email should be rejected.' );
		publishly_test_assert_same( "'=HYPERLINK(\"https://evil.test\",\"x\")", $sanitizer->sanitize_value( '=HYPERLINK("https://evil.test","x")', 'text' ), 'Formula-looking text should be neutralized.' );
		publishly_test_assert_same( '-12', $sanitizer->sanitize_value( '-12', 'number' ), 'Negative numbers should not be treated as formulas.' );
		publishly_test_assert_same( 'https://example.com/photo.jpg', $sanitizer->sanitize_value( 'https://example.com/photo.jpg', 'image' ), 'Safe image URL should be preserved.' );
	},

	'csv parser sanitizes malicious cells by detected type' => static function (): void {
		$path = publishly_test_csv_file(
			array(
				array( 'Name', 'Email', 'Website', 'Image', 'Status', 'Notes', 'Price' ),
				array( '<script>alert(1)</script>', 'bad@@example', 'javascript:alert(1)', 'data:text/html,<svg onload=alert(1)>', '<img src=x onerror=alert(1)>', '=HYPERLINK("https://evil.test","x")', '$12' ),
				array( 'Valid', 'person@example.com', 'https://example.com/page', 'https://example.com/photo.jpg', 'In Stock', '-12', '-5' ),
			)
		);

		$result = ( new CsvParser() )->parse( $path );
		unlink( $path );

		publishly_test_assert_true( ! is_wp_error( $result ), 'Parser should accept a valid CSV shape.' );

		$row = $result['rows'][0];
		publishly_test_assert_same( '', $row['name'], 'Script tags and their contents should be stripped from text cells.' );
		publishly_test_assert_same( '', $row['email'], 'Invalid email should be blank.' );
		publishly_test_assert_same( '', $row['website'], 'javascript: URL should be blank.' );
		publishly_test_assert_same( '', $row['image'], 'data: image should be blank.' );
		publishly_test_assert_same( '', $row['status'], 'HTML-only status should be blank.' );
		publishly_test_assert_same( "'=HYPERLINK(\"https://evil.test\",\"x\")", $row['notes'], 'Formula-looking notes should be neutralized.' );

		publishly_test_assert_same( 'person@example.com', $result['rows'][1]['email'], 'Valid email should remain.' );
		publishly_test_assert_same( 'https://example.com/page', $result['rows'][1]['website'], 'Valid URL should remain.' );
		publishly_test_assert_same( 'https://example.com/photo.jpg', $result['rows'][1]['image'], 'Valid image URL should remain.' );
		publishly_test_assert_same( '-5', $result['rows'][1]['price'], 'Negative price value should remain text-safe.' );
	},

	'csv parser enforces column and binary limits' => static function (): void {
		$headers = array();
		for ( $i = 1; $i <= SpreadsheetSanitizer::MAX_COLUMNS + 1; $i++ ) {
			$headers[] = 'Column ' . $i;
		}

		$wide_path = publishly_test_csv_file( array( $headers ) );
		$result    = ( new CsvParser() )->parse( $wide_path );
		unlink( $wide_path );

		publishly_test_assert_true( is_wp_error( $result ), 'Too-wide CSV should be rejected.' );
		publishly_test_assert_same( 'publishly_csv_too_many_columns', $result->get_error_code(), 'Too-wide CSV should return the expected error code.' );

		$binary_path = tempnam( sys_get_temp_dir(), 'publishly-bin-' );
		file_put_contents( $binary_path, "Name\0Value\nA,B\n" );
		$binary = ( new CsvParser() )->parse( $binary_path );
		unlink( $binary_path );

		publishly_test_assert_true( is_wp_error( $binary ), 'Binary-looking CSV should be rejected.' );
		publishly_test_assert_same( 'publishly_csv_binary', $binary->get_error_code(), 'Binary-looking CSV should return the expected error code.' );
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

		publishly_test_assert_same( 'text', $columns[2]['type'], 'Unknown column types should become text.' );
		publishly_test_assert_same( 1.0, $columns[0]['confidence'], 'Confidence should be clamped to 1.' );
		publishly_test_assert_same( 0.0, $columns[2]['confidence'], 'Confidence should be clamped to 0.' );
		publishly_test_assert_same( array( 'name', 'image', 'weird' ), array_keys( $rows[0] ), 'Unknown row keys should be dropped.' );
		publishly_test_assert_same( 'Ada', $rows[0]['name'], 'Text should be stripped of HTML.' );
		publishly_test_assert_same( '', $rows[0]['image'], 'Unsafe image should be blanked.' );
		publishly_test_assert_same( '', $rows[0]['weird'], 'Fallback text cells should strip script tags and contents.' );
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
			),
			$source
		);

		publishly_test_assert_same( 'searchable_listings', $config['displayType'], 'Unknown display type should fall back.' );
		publishly_test_assert_same( 'Directory', $config['title'], 'Config title should strip tags.' );
		publishly_test_assert_same( array( 'title' => 'name', 'image' => '' ), $config['fieldRoles'], 'Roles should be allowlisted and field-checked.' );
		publishly_test_assert_same( array( 'name', 'status' ), $config['visibleFields'], 'Visible fields should be allowlisted and unique.' );
		publishly_test_assert_same( array( 'status' ), $config['visitorFilters'], 'Visitor filters should be allowlisted.' );
		publishly_test_assert_same( 'equals', $config['defaultFilters'][0]['operator'], 'Unknown filter operators should fall back.' );
		publishly_test_assert_same( 100, $config['pagination']['perPage'], 'Per-page value should be capped.' );
	},

	'frontend renderer omits unsafe image URLs and escapes CSV text' => static function (): void {
		$html  = ( new FrontendRenderer( new Publishly_Test_Database() ) )->render( 9 );
		$lower = strtolower( $html );

		publishly_test_assert_not_contains( '<script', $lower, 'Rendered HTML should not contain script tags.' );
		publishly_test_assert_not_contains( 'javascript:', $lower, 'Rendered HTML should not contain javascript URLs.' );
		publishly_test_assert_not_contains( 'onerror', $lower, 'Rendered HTML should not contain event handlers from CSV.' );
		publishly_test_assert_not_contains( '<img', $lower, 'Invalid image URL should not render an img tag.' );
	},

	'js payload fixture is sanitized before rendering' => static function (): void {
		$path   = dirname( __DIR__ ) . '/samples/suspicious-js-payloads.csv';
		$result = ( new CsvParser() )->parse( $path );

		publishly_test_assert_true( ! is_wp_error( $result ), 'Suspicious JS fixture should parse.' );
		publishly_test_assert_same( '', $result['rows'][0]['title'], 'Script title should be stripped during parsing.' );
		publishly_test_assert_same( '', $result['rows'][0]['website'], 'javascript: URL should be blank during parsing.' );
		publishly_test_assert_same( '', $result['rows'][0]['image'], 'javascript: image should be blank during parsing.' );
		publishly_test_assert_same( '', $result['rows'][0]['status'], 'Event-handler HTML should be stripped during parsing.' );

		$html  = ( new FrontendRenderer( new Publishly_Fixture_Test_Database( $result['columns'], $result['rows'] ) ) )->render( 77 );
		$lower = strtolower( $html );

		publishly_test_assert_not_contains( '<script', $lower, 'Rendered fixture should not contain script tags.' );
		publishly_test_assert_not_contains( 'onerror', $lower, 'Rendered fixture should not contain event handlers.' );
		publishly_test_assert_not_contains( 'data:text/html', $lower, 'Rendered fixture should not contain data HTML URLs.' );
		publishly_test_assert_no_match( '/(?:src|href)\s*=\s*([\"\'])\s*javascript:/i', $html, 'Rendered fixture should not contain executable javascript URLs.' );
		publishly_test_assert_contains( '2 &lt; 3 &amp;&amp; 5 &gt; 4', $html, 'Plain less-than/greater-than text should be escaped in HTML.' );
		publishly_test_assert_contains( '&#039;=HYPERLINK(&quot;javascript:alert(1)&quot;,&quot;Click&quot;)', $html, 'Formula-like JavaScript text should be neutralized and escaped.' );
	},
);

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
