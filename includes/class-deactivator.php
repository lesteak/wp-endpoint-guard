<?php

defined( 'ABSPATH' ) || exit;

class WPEG_Deactivator {

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
