<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$publishly_delete_data = get_option( 'publishly_delete_data_on_uninstall', false );

if ( $publishly_delete_data ) {
	global $wpdb;

	$publishly_tables = array(
		$wpdb->prefix . 'publishly_rows',
		$wpdb->prefix . 'publishly_displays',
		$wpdb->prefix . 'publishly_sources',
	);

	foreach ( $publishly_tables as $publishly_table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall removes plugin-owned custom tables only when the user opted in.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $publishly_table ) );
	}

	delete_option( 'publishly_version' );
	delete_option( 'publishly_default_accent_color' );
	delete_option( 'publishly_default_rows_per_page' );
	delete_option( 'publishly_delete_data_on_uninstall' );
}
