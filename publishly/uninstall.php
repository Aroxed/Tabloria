<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = get_option( 'publishly_delete_data_on_uninstall', false );

if ( $delete_data ) {
	global $wpdb;

	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'publishly_rows' );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'publishly_displays' );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'publishly_sources' );

	delete_option( 'publishly_version' );
	delete_option( 'publishly_default_accent_color' );
	delete_option( 'publishly_default_rows_per_page' );
	delete_option( 'publishly_delete_data_on_uninstall' );
}
