<?php
// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test runner; messages go to the terminal and are never served over HTTP.

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
$page_id = 0;
$original_user_id = get_current_user_id();

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

	$draft_request = new WP_REST_Request( 'POST', '/tabloria/v1/displays/' . $display_id . '/draft-page' );
	wp_set_current_user( 0 );
	$denied_response = rest_do_request( $draft_request );
	if ( ! in_array( $denied_response->get_status(), array( 401, 403 ), true ) ) {
		throw new RuntimeException( 'Anonymous visitors must not create draft pages.' );
	}

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( ! $admins ) {
		throw new RuntimeException( 'An administrator is required for the draft-page smoke test.' );
	}
	wp_set_current_user( $admins[0]->ID );
	$draft_response = rest_do_request( $draft_request );
	if ( 201 !== $draft_response->get_status() ) {
		throw new RuntimeException( 'An administrator should be able to create a draft page: ' . wp_json_encode( $draft_response->get_data() ) );
	}
	$draft_data = $draft_response->get_data();
	$page_id = absint( $draft_data['pageId'] ?? 0 );
	$page = get_post( $page_id );
	if ( ! $page || 'page' !== $page->post_type || 'draft' !== $page->post_status ) {
		throw new RuntimeException( 'The new page should exist as a WordPress draft.' );
	}
	if ( 'Tabloria 0.2 smoke display' !== $page->post_title ) {
		throw new RuntimeException( 'The draft page should use the Display name as its title.' );
	}
	$blocks = parse_blocks( $page->post_content );
	if ( 1 !== count( $blocks ) || 'tabloria/display' !== $blocks[0]['blockName'] || $display_id !== ( $blocks[0]['attrs']['displayId'] ?? 0 ) ) {
		throw new RuntimeException( 'The draft page should contain the saved Display block.' );
	}
	tabloria_smoke_assert_contains( 'Running shoes coupon', do_blocks( $page->post_content ), 'The draft page block should render the saved Display.' );
	if ( empty( $draft_data['editUrl'] ) || ! str_contains( $draft_data['editUrl'], 'post=' . $page_id ) ) {
		throw new RuntimeException( 'The draft-page response should include an editor link.' );
	}

	$missing_response = rest_do_request( new WP_REST_Request( 'POST', '/tabloria/v1/displays/999999999/draft-page' ) );
	if ( 404 !== $missing_response->get_status() ) {
		throw new RuntimeException( 'A missing Display must not create a draft page.' );
	}

	echo "PASS WordPress shortcode, Gutenberg, and draft page smoke test\n";
} finally {
	global $wpdb;
	wp_set_current_user( $original_user_id );
	if ( $page_id ) {
		wp_delete_post( $page_id, true );
	}
	if ( $display_id ) {
		$database->delete_display( $display_id );
	}
	if ( $source_id ) {
		$wpdb->delete( Database::rows_table(), array( 'source_id' => $source_id ), array( '%d' ) );
		$wpdb->delete( Database::sources_table(), array( 'id' => $source_id ), array( '%d' ) );
	}
}
