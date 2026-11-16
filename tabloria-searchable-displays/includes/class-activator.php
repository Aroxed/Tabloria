<?php

namespace Tabloria;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	public static function activate(): void {
		Database::install();
		update_option( 'tabloria_version', TABLORIA_VERSION );
	}
}
