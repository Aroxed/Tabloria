<?php
/**
 * Plugin Name: Tabloria - CSV Product Catalog & Searchable Displays
 * Plugin URI: https://tabloria.site/
 * Description: Build a searchable WordPress product catalog from CSV, with filters, cards, and a Gutenberg block.
 * Version: 0.2.4
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

define( 'TABLORIA_VERSION', '0.2.4' );
define( 'TABLORIA_FILE', __FILE__ );
define( 'TABLORIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'TABLORIA_URL', plugin_dir_url( __FILE__ ) );

require_once TABLORIA_DIR . 'includes/Services/class-spreadsheetsanitizer.php';
require_once TABLORIA_DIR . 'includes/class-activator.php';
require_once TABLORIA_DIR . 'includes/class-database.php';
require_once TABLORIA_DIR . 'includes/class-plugin.php';
require_once TABLORIA_DIR . 'includes/REST/class-sourcescontroller.php';
require_once TABLORIA_DIR . 'includes/REST/class-uploadcontroller.php';
require_once TABLORIA_DIR . 'includes/REST/class-displayscontroller.php';
require_once TABLORIA_DIR . 'includes/REST/class-settingscontroller.php';
require_once TABLORIA_DIR . 'includes/Services/class-csvparser.php';
require_once TABLORIA_DIR . 'includes/Services/class-datareplacementservice.php';
require_once TABLORIA_DIR . 'includes/Services/class-sourcesync.php';
require_once TABLORIA_DIR . 'includes/Services/class-columndetector.php';
require_once TABLORIA_DIR . 'includes/Services/class-displaygenerator.php';
require_once TABLORIA_DIR . 'includes/Services/class-dataupdater.php';
require_once TABLORIA_DIR . 'includes/Services/class-frontendrenderer.php';
require_once TABLORIA_DIR . 'includes/Services/class-xlsxparser.php';
require_once TABLORIA_DIR . 'includes/Models/class-source.php';
require_once TABLORIA_DIR . 'includes/Models/class-display.php';

register_activation_hook( __FILE__, array( 'Tabloria\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Tabloria\\Services\\SourceSync', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		$plugin = new Tabloria\Plugin();
		$plugin->init();
	}
);
