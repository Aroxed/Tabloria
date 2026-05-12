<?php

namespace Publishly;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	public static function activate(): void {
		Database::install();
		update_option( 'publishly_version', PUBLISHLY_VERSION );
	}
}
