<?php

/**
 * Adds the Bulk Page and Smush Column to NextGen Gallery
 *
 * @package WP Smush
 * @subpackage NextGen Gallery
 * @version 1.0
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2015, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushNextGenAdmin' ) ) {

	class WpSmushNextGenAdmin extends WpSmushNextGen {

		var $total_count = 0;
		var $smushed_count = 0;

		function __construct() {

			//Update the number of columns
			add_filter( 'ngg_manage_images_number_of_columns', array(
				&$this,
				'wp_smush_manage_images_number_of_columns'
			) );

			//Add a bulk smush option for NextGen gallery
			add_action( 'admin_menu', array( &$this, 'wp_smush_bulk_menu' ) );

		}

		/**
		 * Add a WP Smush page for bulk smush and settings related to Nextgen gallery
		 */
		function wp_smush_bulk_menu() {
			if ( defined( 'NGGFOLDER' ) ) {
				add_submenu_page( NGGFOLDER, esc_html__( 'WP Bulk Smush', 'wp-smushit' ), esc_html__( 'WP Smush', 'wp-smushit' ), 'NextGEN Manage gallery', 'wp-smush-nextgen-bulk', array(
					&$this,
					'wp_smush_bulk'
				) );
			}
		}

		/**
		 * Returns a column name for WP Smush
		 *
		 * @param $columns
		 *
		 * @return mixed
		 */
		function wp_smush_image_column_name( $columns ) {
			//Latest next gen takes string, while the earlier WP Smush plugin shows there use to be a array
			if ( is_array( $columns ) ) {
				$columns['wp_smush_image'] = esc_html__( 'WP Smush', 'wp-smushit' );
			} else {
				$columns = esc_html__( 'WP Smush', 'wp-smushit' );
			}

			return $columns;
		}

		/**
		 * Returns Smush option / Stats, depending if image is already smushed or not
		 *
		 * @param $column_name
		 * @param $id
		 */
		function wp_smush_column_options( $column_name, $id ) {
			global $wpsmushnextgenstats;

			//NExtGen Doesn't returns Column name, weird? yeah, right, it is proper because hook is called for the particular column
			if ( $column_name == 'wp_smush_image' || $column_name == '' ) {
				$supported_image = array( 'image/jpeg', 'image/gif', 'image/png', 'image/jpg' );

				// Registry Object for NextGen Gallery
				$registry = C_Component_Registry::get_instance();

				//Gallery Storage Object
				$storage = $registry->get_utility( 'I_Gallery_Storage' );

				//We'll get the image object in $id itself, else fetch it using Gallery Storage
				if ( is_object( $id ) ) {
					$image = $id;
				} else {
					// get an image object
					$image = $storage->object->_image_mapper->find( $id );
				}

				//Check if it is supported image format, get image type to do that
				// get the absolute path
				$file_path = $storage->get_image_abspath( $image, 'full' );

				//Get image type from file path
				$image_type = $this->get_file_type( $file_path );

				//If image type not supported
				if ( ! in_array( $image_type, $supported_image ) ) {
					return;
				}

				//Check Image metadata, if smushed, print the stats or super smush button
				if ( ! empty( $image->meta_data['wp_smush'] ) ) {
					//Echo the smush stats
					$wpsmushnextgenstats->show_stats( $image->pid, $image->meta_data['wp_smush'], $image_type, false, true );

					return;
				}

				//Print the status of image, if Not smushed
				$this->set_status( $image->pid, true, false );

			}
		}

		/**
		 * Bulk Smush Page
		 */
		function wp_smush_bulk() {

			global $wpsmushit_admin;
			?>
			<div class="wrap"><?php
			//Promotional Text
			$wpsmushit_admin->smush_pro_features();

			//Bulk Smush UI, calls progress UI, Super Smush UI
			$this->bulk_smush_ui(); ?>
			</div><?php

			return;
		}

		/**
		 * Increase the count of columns for Nextgen Gallery Manage page
		 *
		 * @param $count
		 *
		 * @return mixed
		 */
		function wp_smush_manage_images_number_of_columns( $count ) {
			$count ++;

			//Add column Heading
			add_filter( "ngg_manage_images_column_{$count}_header", array( &$this, 'wp_smush_image_column_name' ) );

			//Add Column data
			add_filter( "ngg_manage_images_column_{$count}_content", array(
				&$this,
				'wp_smush_column_options'
			), 10, 2 );

			return $count;
		}

		/**
		 * Set send button status
		 *
		 * @param $id
		 * @param bool $echo
		 * @param bool $text_only
		 *
		 * @return string|void
		 */
		function set_status( $pid, $echo = true, $text_only = false ) {
			$button_txt = '';

			// the status
			$status_txt = __( 'Not processed', 'wp-smushit' );

			// we need to show the smush button
			$show_button = true;

			// the button text
			$button_txt = __( 'Smush Now!', 'wp-smushit' );
			if ( $text_only ) {
				return $status_txt;
			}

			$text = $this->column_html( $pid, $status_txt, $button_txt, $show_button, false, $echo );
			if ( ! $echo ) {
				return $text;
			}
		}

		/**
		 * Print the column html
		 *
		 * @param string $id Media id
		 * @param string $status_txt Status text
		 * @param string $button_txt Button label
		 * @param boolean $show_button Whether to shoe the button
		 *
		 * @return null
		 */
		function column_html( $pid, $status_txt = "", $button_txt = "", $show_button = true, $smushed = false, $echo = true ) {

			$class = $smushed ? '' : ' hidden';
			$html  = '<p class="smush-status' . $class . '">' . $status_txt . '</p>';
			$html .= wp_nonce_field( 'wp_smush_nextgen_' . $pid, '_wp_smush_nonce', '', false );
			// if we aren't showing the button
			if ( ! $show_button ) {
				if ( $echo ) {
					echo $html;

					return;
				} else {
					if ( ! $smushed ) {
						$class = ' currently-smushing';
					} else {
						$class = ' smushed';
					}

					return '<div class="smush-wrap' . $class . '">' . $html . '</div>';
				}
			}
			if ( ! $echo ) {
				$html .= '
				<button  class="button button-primary wp-smush-nextgen-send" data-id="' . $pid . '">
	                <span>' . $button_txt . '</span>
				</button>';
				if ( ! $smushed ) {
					$class = ' unsmushed';
				} else {
					$class = ' smushed';
				}

				return '<div class="smush-wrap' . $class . '">' . $html . '</div>';
			} else {
				$html .= '<button class="button wp-smush-nextgen-send" data-id="' . $pid . '">
                    <span>' . $button_txt . '</span>
				</button>';
				echo $html;
			}
		}

		/**
		 * Print out the progress bar
		 */
		function progress_ui() {
			global $WpSmush, $wpsmushnextgenstats;
			$this->stats     = $wpsmushnextgenstats->get_smush_stats();


			// calculate %ages, avoid divide by zero error with no attachments
			if ( $this->total_count > 0 ) {
				$smushed_pc = $this->smushed_count / $this->total_count * 100;
			} else {
				$smushed_pc = 0;
			}
			$bytes   = ! empty( $this->stats['bytes'] ) ? $this->stats['bytes'] : 0;
			$human   = ! empty( $this->stats['human'] ) ? $this->stats['human'] : $WpSmush->format_bytes( $bytes );
			$percent = ! empty( $this->stats['percent'] ) ? number_format_i18n( $this->stats['percent'], 2, '.', '' ) : '';

			$progress_ui = '<div id="progress-ui">';

			// display the progress bars
			$progress_ui .= '<div id="wp-smush-progress-wrap">
                                                <div id="wp-smush-fetched-progress" class="wp-smush-progressbar"><div style="width:' . $smushed_pc . '%"></div></div>
                                                <p id="wp-smush-compression">'
			                . __( "Reduced by ", 'wp-smushit' )
			                . '<span id="human">' . $human . '</span> ( <span id="percent">' . $percent . '</span>% )
                                                </p>
                                        </div>';

			// status divs to show completed count/ total count
			$progress_ui .= '<div id="wp-smush-progress-status">

                            <p id="fetched-status">' .
			                sprintf(
				                __(
					                '<span class="done-count">%d</span> of <span class="total-count">%d</span> total attachments have been smushed', 'wp-smushit'
				                ), $this->smushed_count, $this->total_count
			                ) .
			                '</p>
                                        </div>
				</div>';
			// print it out
			echo $progress_ui;
		}

		/**
		 *
		 */
		function bulk_smush_ui() {
			global $WpSmush, $wpsmushnextgenstats;

			//Set the counts
			$this->total_count     = $wpsmushnextgenstats->total_count();
			$this->smushed_count   = $wpsmushnextgenstats->get_smushed_images_count();
			$this->remaining_count = $this->total_count > 0 ? $this->total_count - $this->smushed_count : 0;

			?>
			<div class="bulk-smush">
			<h3><?php _e( 'Smush in Bulk', 'wp-smushit' ) ?></h3>
			<?php
				$this->get_nextgen_attachments();

			//Nothing to smush
			if ( $this->remaining_count == 0 ) {
				?>
				<p><?php _e( "Congratulations, all your images are currently Smushed!", 'wp-smushit' ); ?></p>
				<?php
				$this->progress_ui();
			} else {
				?>
				<div class="smush-instructions">
					<h4 class="smush-remaining-images-notice"><?php printf( _n( "%d attachment in NextGen Gallery has not been smushed yet.", "%d image attachments in NextGen Gallery have not been smushed yet.", $this->remaining_count, 'wp-smushit' ), $this->remaining_count ); ?></h4>
					<p><?php _e( "Please be aware, smushing a large number of images can take a while depending on your server and network speed.
						<strong>You must keep this page open while the bulk smush is processing</strong>, but you can leave at any time and come back to continue where it left off.", 'wp-smushit' ); ?></p>

					<?php if ( ! $WpSmush->is_pro() ) { ?>
						<p class="error">
							<?php printf( __( "Free accounts are limited to bulk smushing %d attachments per request. You will need to click to start a new bulk job after each %d attachments.", 'wp-smushit' ), $this->max_free_bulk, $this->max_free_bulk ); ?>
							<a href="<?php echo $this->upgrade_url; ?>"><?php _e( 'Remove limits &raquo;', 'wp-smushit' ); ?></a>
						</p>
					<?php } ?>

				</div>

				<!-- Bulk Smushing -->
				<?php wp_nonce_field( 'wp-smush-bulk', '_wpnonce' ); ?>
				<br/><?php
					$this->progress_ui();
				?>
				<p class="smush-final-log"></p>
				<?php
//				$this->setup_button();
			}

			$auto_smush = get_site_option( WP_SMUSH_PREFIX . 'auto' );
			if ( ! $auto_smush && $this->remaining_count == 0 ) {
				?>
				<p><?php printf( __( 'When you <a href="%s">upload some images</a> they will be available to smush here.', 'wp-smushit' ), admin_url( 'media-new.php' ) ); ?></p>
				<?php
			} else { ?>
				<p>
				<?php
				// let the user know that there's an alternative
				printf( __( 'You can also smush images individually from your <a href="%s">Media Library</a>.', 'wp-smushit' ), admin_url( 'upload.php' ) );
				?>
				</p><?php
			}
			?>
			</div><?php
		}
	}//End of Class

}//End Of if class not exists