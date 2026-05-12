<?php
/**
 * Plugin Name: Publishly
 * Description: A friendly spreadsheet publishing assistant for creating reusable searchable WordPress Displays.
 * Version: 0.1.8
 * Author: Publishly
 * Text Domain: publishly
 * Requires at least: 6.3
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PUBLISHLY_VERSION', '0.1.8' );
define( 'PUBLISHLY_FILE', __FILE__ );
define( 'PUBLISHLY_DIR', plugin_dir_path( __FILE__ ) );
define( 'PUBLISHLY_URL', plugin_dir_url( __FILE__ ) );

require_once PUBLISHLY_DIR . 'includes/Activator.php';
require_once PUBLISHLY_DIR . 'includes/Database.php';
require_once PUBLISHLY_DIR . 'includes/Plugin.php';
require_once PUBLISHLY_DIR . 'includes/REST/SourcesController.php';
require_once PUBLISHLY_DIR . 'includes/REST/UploadController.php';
require_once PUBLISHLY_DIR . 'includes/REST/DisplaysController.php';
require_once PUBLISHLY_DIR . 'includes/REST/SettingsController.php';
require_once PUBLISHLY_DIR . 'includes/Services/CsvParser.php';
require_once PUBLISHLY_DIR . 'includes/Services/ColumnDetector.php';
require_once PUBLISHLY_DIR . 'includes/Services/DisplayGenerator.php';
require_once PUBLISHLY_DIR . 'includes/Services/DataUpdater.php';
require_once PUBLISHLY_DIR . 'includes/Services/FrontendRenderer.php';
require_once PUBLISHLY_DIR . 'includes/Services/XlsxParser.php';
require_once PUBLISHLY_DIR . 'includes/Models/Source.php';
require_once PUBLISHLY_DIR . 'includes/Models/Display.php';

register_activation_hook( __FILE__, array( 'Publishly\\Activator', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		$plugin = new Publishly\Plugin();
		$plugin->init();
	}
);
