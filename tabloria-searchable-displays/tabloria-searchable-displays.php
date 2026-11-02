<?php
/**
 * Plugin Name: Tabloria Searchable Displays
 * Plugin URI: https://tabloria.site/
 * Description: Turn CSV rows into searchable catalogs, directories, coupon displays, listings, and responsive data tables.
 * Version: 0.2.1
 * Author: Tabloria
 * Text Domain: tabloria-searchable-displays
 * Requires at least: 6.3
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TABLORIA_VERSION', '0.2.1' );
define( 'TABLORIA_FILE', __FILE__ );
define( 'TABLORIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'TABLORIA_URL', plugin_dir_url( __FILE__ ) );

require_once TABLORIA_DIR . 'includes/Services/SpreadsheetSanitizer.php';
require_once TABLORIA_DIR . 'includes/Activator.php';
require_once TABLORIA_DIR . 'includes/Database.php';
require_once TABLORIA_DIR . 'includes/Plugin.php';
require_once TABLORIA_DIR . 'includes/REST/SourcesController.php';
require_once TABLORIA_DIR . 'includes/REST/UploadController.php';
require_once TABLORIA_DIR . 'includes/REST/DisplaysController.php';
require_once TABLORIA_DIR . 'includes/REST/SettingsController.php';
require_once TABLORIA_DIR . 'includes/Services/CsvParser.php';
require_once TABLORIA_DIR . 'includes/Services/ColumnDetector.php';
require_once TABLORIA_DIR . 'includes/Services/DisplayGenerator.php';
require_once TABLORIA_DIR . 'includes/Services/DataUpdater.php';
require_once TABLORIA_DIR . 'includes/Services/FrontendRenderer.php';
require_once TABLORIA_DIR . 'includes/Services/XlsxParser.php';
require_once TABLORIA_DIR . 'includes/Models/Source.php';
require_once TABLORIA_DIR . 'includes/Models/Display.php';

register_activation_hook( __FILE__, array( 'Tabloria\\Activator', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		$plugin = new Tabloria\Plugin();
		$plugin->init();
	}
);
