<?php

if ( ! class_exists( 'WpSmushBackup' ) ) {

	class WpSmushBackup {

		/**
		 * Whether to backp images or not
		 * @var bool
		 */
		var $backup_enabled = false;

		/**
		 * Constructor
		 */
		function __construct() {
			//Initialize Variables and perform other operations
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}

		function admin_init() {

			$this->initialize();

		}

		function initialize() {
			//Whether backup is enabled or not
			$this->backup_enabled = get_option( WP_SMUSH_PREFIX . 'backup' );
		}
	}

	global $wpsmush_backup;
	$wpsmush_backup = new WpSmushBackup();

}