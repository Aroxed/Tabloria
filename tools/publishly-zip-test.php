<?php

$command     = $argv[1] ?? 'install';
$wp_load     = '/var/www/html/wp-load.php';
$zip_path    = '/tmp/publishly.zip';
$plugin_file = 'publishly/publishly.php';
$site_url    = getenv( 'PUBLISHLY_ZIPTEST_SITE_URL' ) ?: 'http://127.0.0.1:8082';

$_SERVER['HTTP_HOST']   = parse_url( $site_url, PHP_URL_HOST ) ?: '127.0.0.1';
$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI'] = '/';

if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php is not ready yet.\n" );
	exit( 1 );
}

define( 'WP_INSTALLING', true );
define( 'FS_METHOD', 'direct' );
require_once $wp_load;
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';

function publishly_ziptest_install_wordpress( string $site_url ): void {
	if ( is_blog_installed() ) {
		return;
	}

	wp_install(
		'Publishly Zip Test',
		'admin',
		'admin@example.com',
		true,
		'',
		'admin'
	);

	update_option( 'siteurl', $site_url );
	update_option( 'home', $site_url );
}

function publishly_ziptest_prepare_filesystem(): void {
	foreach ( array( WP_CONTENT_DIR . '/plugins', WP_CONTENT_DIR . '/upgrade', WP_CONTENT_DIR . '/uploads' ) as $directory ) {
		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}
	}

	if ( function_exists( 'exec' ) ) {
		exec( 'chown -R www-data:www-data ' . escapeshellarg( WP_CONTENT_DIR ) );
	}
}

function publishly_ziptest_status( string $plugin ): void {
	$file = WP_PLUGIN_DIR . '/' . $plugin;
	echo 'plugin_file=' . ( file_exists( $file ) ? 'present' : 'missing' ) . "\n";
	echo 'plugin_active=' . ( is_plugin_active( $plugin ) ? 'yes' : 'no' ) . "\n";

	if ( file_exists( $file ) ) {
		$data = get_plugin_data( $file, false, false );
		echo 'plugin_version=' . ( $data['Version'] ?? 'unknown' ) . "\n";
	}
}

function publishly_ziptest_delete_plugin( string $plugin, bool $delete_data ): void {
	if ( is_plugin_active( $plugin ) ) {
		deactivate_plugins( array( $plugin ) );
	}

	if ( $delete_data ) {
		update_option( 'publishly_delete_data_on_uninstall', true );
	}

	if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
		$result = delete_plugins( array( $plugin ) );
		if ( is_wp_error( $result ) ) {
			fwrite( STDERR, 'delete_error=' . $result->get_error_message() . "\n" );
			exit( 1 );
		}
	}
}

function publishly_ziptest_install_plugin( string $zip_path, string $plugin ): void {
	if ( ! file_exists( $zip_path ) ) {
		fwrite( STDERR, "Zip file not found: {$zip_path}\n" );
		exit( 1 );
	}

	publishly_ziptest_delete_plugin( $plugin, false );

	WP_Filesystem();
	$skin     = new Automatic_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->install( $zip_path );

	if ( is_wp_error( $result ) ) {
		fwrite( STDERR, 'install_error=' . $result->get_error_message() . "\n" );
		exit( 1 );
	}

	if ( true !== $result || ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
		fwrite( STDERR, "install_error=Plugin upgrader did not install Publishly.\n" );
		exit( 1 );
	}

	$activation = activate_plugin( $plugin );
	if ( is_wp_error( $activation ) ) {
		fwrite( STDERR, 'activate_error=' . $activation->get_error_message() . "\n" );
		exit( 1 );
	}
}

publishly_ziptest_install_wordpress( $site_url );
publishly_ziptest_prepare_filesystem();

if ( 'install' === $command ) {
	publishly_ziptest_install_plugin( $zip_path, $plugin_file );
	publishly_ziptest_prepare_filesystem();
	publishly_ziptest_status( $plugin_file );
	exit( 0 );
}

if ( 'delete' === $command || 'delete-with-data' === $command ) {
	publishly_ziptest_delete_plugin( $plugin_file, 'delete-with-data' === $command );
	publishly_ziptest_prepare_filesystem();
	publishly_ziptest_status( $plugin_file );
	exit( 0 );
}

if ( 'status' === $command ) {
	publishly_ziptest_status( $plugin_file );
	exit( 0 );
}

fwrite( STDERR, "Unknown command. Use install, status, delete, or delete-with-data.\n" );
exit( 1 );
