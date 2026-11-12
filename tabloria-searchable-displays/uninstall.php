<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$tabloria_delete_data = get_option( 'tabloria_delete_data_on_uninstall', false );

if ( $tabloria_delete_data ) {
	global $wpdb;
	foreach ( (array) get_option( 'tabloria_source_sync_ids', array() ) as $tabloria_source_id ) {
		$tabloria_source_id = absint( $tabloria_source_id );
		wp_clear_scheduled_hook( 'tabloria_sync_csv_source', array( $tabloria_source_id ) );
		delete_option( 'tabloria_source_sync_' . $tabloria_source_id );
	}
	delete_option( 'tabloria_source_sync_ids' );

	$tabloria_tables = array(
		$wpdb->prefix . 'tabloria_rows',
		$wpdb->prefix . 'tabloria_displays',
		$wpdb->prefix . 'tabloria_sources',
	);

	foreach ( $tabloria_tables as $tabloria_table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall removes plugin-owned custom tables only when the user opted in.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $tabloria_table ) );
	}

	delete_option( 'tabloria_version' );
	delete_option( 'tabloria_default_accent_color' );
	delete_option( 'tabloria_default_rows_per_page' );
	delete_option( 'tabloria_delete_data_on_uninstall' );
}
