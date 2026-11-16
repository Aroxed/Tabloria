<?php
/**
 * Plugin activation routine
 *
 * @package Tabloria
 */

namespace Tabloria;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the tasks needed when the plugin is activated.
 */
class Activator {
	/**
	 * Create the database tables and store the plugin version on activation.
	 */
	public static function activate(): void {
		Database::install();
		update_option( 'tabloria_version', TABLORIA_VERSION );
	}
}
