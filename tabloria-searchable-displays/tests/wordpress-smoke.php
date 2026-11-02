<?php

use Tabloria\Database;
use Tabloria\Services\DisplayGenerator;

$wp_load = getenv( 'TABLORIA_WP_LOAD' ) ?: dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Unable to find wp-load.php. Set TABLORIA_WP_LOAD.\n" );
	exit( 1 );
}

require_once $wp_load;

function tabloria_smoke_assert_contains( string $needle, string $haystack, string $message ): void {
	if ( ! str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( $message . ' Missing: ' . $needle );
	}
}

function tabloria_smoke_assert_not_contains( string $needle, string $haystack, string $message ): void {
	if ( str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( $message . ' Found: ' . $needle );
	}
}

$database = new Database();
$source_id = 0;
$display_id = 0;

try {
	$columns = array(
		array( 'key' => 'offer', 'label' => 'Offer', 'type' => 'text' ),
		array( 'key' => 'category', 'label' => 'Category', 'type' => 'status' ),
		array( 'key' => 'coupon_code', 'label' => 'Coupon Code', 'type' => 'text' ),
		array( 'key' => 'affiliate_url', 'label' => 'Affiliate URL', 'type' => 'url' ),
	);
	$rows = array(
		array( 'offer' => 'Running shoes coupon', 'category' => 'Sports', 'coupon_code' => 'RUN20', 'affiliate_url' => 'https://example.com/sports' ),
		array( 'offer' => 'Weekend hotel deal', 'category' => 'Travel', 'coupon_code' => '', 'affiliate_url' => 'https://example.com/travel' ),
	);
	$source_id = $database->create_source(
		array(
			'name' => 'Tabloria 0.2 smoke source',
			'original_filename' => 'tabloria-smoke.csv',
			'file_type' => 'csv',
			'columns' => $columns,
		),
		$rows
	);
	$source = $database->get_source( $source_id );
	$config = ( new DisplayGenerator() )->generate(
		$source,
		'coupon_display',
		array(
			'title' => 'Smoke coupons',
			'fieldRoles' => array(
				'title' => 'offer',
				'coupon_code' => 'coupon_code',
				'affiliate_url' => 'affiliate_url',
			),
			'visibleFields' => array( 'offer', 'category', 'coupon_code', 'affiliate_url' ),
		)
	);
	$display_id = $database->create_display(
		array(
			'source_id' => $source_id,
			'name' => 'Tabloria 0.2 smoke display',
			'display_type' => 'coupon_display',
			'config' => $config,
			'status' => 'published',
		)
	);

	$shortcode_html = do_shortcode( '[tabloria id="' . $display_id . '" filter_field="category" filter_value="Travel"]' );
	tabloria_smoke_assert_contains( 'Weekend hotel deal', $shortcode_html, 'Shortcode page filter should keep Travel.' );
	tabloria_smoke_assert_not_contains( 'Running shoes coupon', $shortcode_html, 'Shortcode page filter should exclude Sports.' );
	tabloria_smoke_assert_contains( 'tabloria-coupon__deal', $shortcode_html, 'Deal-only row should render its direct action.' );

	$block_html = do_blocks( '<!-- wp:tabloria/display {"displayId":' . $display_id . ',"filterField":"category","filterValue":"Sports"} /-->' );
	tabloria_smoke_assert_contains( 'Running shoes coupon', $block_html, 'Gutenberg page filter should keep Sports.' );
	tabloria_smoke_assert_not_contains( 'Weekend hotel deal', $block_html, 'Gutenberg page filter should exclude Travel.' );
	tabloria_smoke_assert_contains( 'data-tabloria-show-coupon', $block_html, 'Coupon row should render its reveal action.' );
	tabloria_smoke_assert_contains( 'data-tabloria-copy-coupon', $block_html, 'Coupon row should render its copy action.' );

	echo "PASS WordPress shortcode and Gutenberg smoke test\n";
} finally {
	global $wpdb;
	if ( $display_id ) {
		$database->delete_display( $display_id );
	}
	if ( $source_id ) {
		$wpdb->delete( Database::rows_table(), array( 'source_id' => $source_id ), array( '%d' ) );
		$wpdb->delete( Database::sources_table(), array( 'id' => $source_id ), array( '%d' ) );
	}
}
