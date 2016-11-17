<?php
/**
 * @package WP Smush
 * @subpackage Admin
 * @since 2.6
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */

if ( ! class_exists( 'WpSmushAll' ) ) {

	class WpSmushAll {

		function __construct() {

		}

		function admin_menu() {
			global $wpsmushit_admin;
			$wpsmushit_admin->bulk_ui->smush_page_header();
			//Page Content
			$this->ui();
			$wpsmushit_admin->bulk_ui->smush_page_footer();
		}

		function ui() {?>
			<div class="wp-smush-dir-browser">
				<label for="wp-smush-dir"><?php esc_attr_e( "Image Directories", "wp-smushit"); ?>
					<input type="text" value="" class="wp-smush-dir-path" name="smush_dir_path" id="wp-smush-dir">
				</label>
				<a href="#wp-smush-image-list" class="wp-smush-browse wp-smush-button button" rel="dialog"><?php esc_html_e( "Browse", "wp-smushit" ); ?></a>
			</div>
			<dialog id="wp-smush-image-list" title="<?php esc_html_e("List of images to be smushed", "wp-smushit"); ?>" class="wp-smush-list-dialog">
				<div class="wp-smush-loading-wrap">
					<span class="spinner"></span>
					<span class="wp-smush-loading-text"><?php esc_html_e("Loading..", "wp-smushit"); ?></span>
				</div>
			</dialog>
			<button type="button" class="wp-smush-scan wp-smush-button"><?php esc_html_e("Scan", "wp-smushit"); ?></button><?php
		}

	}

	//Class Object
	global $wpsmush_all;
	$wpsmush_all = new WpSmushAll();
}