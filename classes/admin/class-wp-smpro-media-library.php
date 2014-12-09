<?php
/**
 * @package SmushItPro
 * @subpackage Admin
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmProMediaLibrary' ) ) {

	/**
	 * Show settings in Media settings and add column to media library
	 *
	 */
	class WpSmProMediaLibrary {

		var $current_requests;

		/**
		 * Constructor
		 */
		public function __construct() {

			// get the DEV api key
			$wpmudev_apikey = get_site_option( 'wpmudev_apikey' );

			// add the admin option screens
			add_action( 'admin_init', array( $this, 'admin_init' ) );

			// if there's a key
			if ( ! empty( $wpmudev_apikey ) ) {
				// add extra columns for smushing to media lists
				add_filter( 'manage_media_columns', array( $this, 'columns' ) );
				add_action( 'manage_media_custom_column', array( $this, 'custom_column' ), 10, 2 );
			}

			$this->current_requests = get_option( WP_SMPRO_PREFIX . 'current-requests', array() );
			add_action( 'admin_head-upload.php', array( &$this, 'add_bulk_actions_via_javascript' ) );
			add_action( 'admin_action_bulk_smushit', array( &$this, 'bulk_action_handler' ) );
//			add_filter( 'wp_prepare_attachment_for_js', array( $this, 'insert_image_smush_data' ), '', 3 );
		}

		/**
		 * Print column header for Smush results in the media library
		 *
		 * @param array $defaults The default columns
		 *
		 * @return array columns with our header added
		 */
		function columns( $defaults ) {
			$defaults['smushit'] = 'Smush';

			return $defaults;
		}

		/**
		 * Show our custom smush data for each attachment
		 *
		 * @param string $column_name The name of the column
		 * @param int $id The attachment id
		 *
		 * @return null
		 */
		function custom_column( $column_name, $id ) {
			$status_txt = '';
			// if it isn't our column, get out
			if ( 'smushit' != $column_name ) {
				return;
			}

			$this->set_status( $id );
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
		function column_html( $id, $status_txt = "", $button_txt = "", $show_button = true, $smushed = false, $echo = true ) {
			$allowed_images = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif' );
			// don't proceed if attachment is not image, or if image is not a jpg, png or gif
			if ( ! wp_attachment_is_image( $id ) || ! in_array( get_post_mime_type( $id ), $allowed_images ) ) {
				return;
			}
			$html = '
			<p class="smush-status">' . $status_txt . '</p>';
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
				<button id="wp-smpro-send" class="button button-primary">
	                <span>' . $button_txt . '</span>
				</button>';
				if ( ! $smushed ) {
					$class = ' unsmushed';
				} else {
					$class = ' smushed';
				}

				return '<div class="smush-wrap' . $class . '">' . $html . '</div>';
			} else {
				$html .= '<button id="wp-smpro-send" class="button">
                    <span>' . $button_txt . '</span>
				</button>';
				echo $html;
			}
		}

		/**
		 * Check whether to Show resmush buton, if smush request is 12 hours old
		 *
		 * @param array $smush_meta_full the smush metadata for full image size
		 *
		 * @return boolean true to display the button
		 */
		function show_resmush_button( $id ) {
			$button_show = false;
			$timestamp   = '';

			$is_smushed = get_post_meta( $id, 'wp-smpro-is-smushed', true );

			if ( $is_smushed === '1' ) {
				return $button_show;
			}
			//This post meta is set only if image is sent individually, and we want to show
			//smush button only for single request after 12 hours
			$smush_request_id = get_post_meta( $id, WP_SMPRO_PREFIX . 'request-id', true );

			if ( empty( $smush_request_id ) ) {
				$button_show = false;
			} else {
				//get smush request meta, to fetch timestamp
				$smpro_request_data = get_post_meta( $id, WP_SMPRO_PREFIX . 'request-' . $smush_request_id, true );
				if ( ! empty( $smpro_request_data ) ) {
					$timestamp = ! empty( $smpro_request_data['timestamp'] ) ? $smpro_request_data['timestamp'] : '';
				}
			}

			if ( ! $is_smushed && ! empty( $timestamp ) ) {
				if ( $timestamp <= strtotime( '-12 hours' ) ) {

					//if request is older than 12 hours, remove from sent ids
					$sent_ids = get_option( WP_SMPRO_PREFIX . 'sent-ids' );

					// Search
					$pos = array_search( $id, $sent_ids );
					if ( ! $pos ) {
						//Attachment id is not set in sent ids, show the smush button
						$button_show = true;
					} else {
						unset( $sent_ids[ $pos ] );
						update_option( WP_SMPRO_PREFIX . 'sent-ids', $sent_ids );
						$button_show = true;
					}
				}
			} else {
				$button_show = false;
			}

			return $button_show;
		}

		/**
		 * enqueue common script
		 */
		function admin_init() {
			wp_enqueue_script( 'common' );
		}

		// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
		function add_bulk_actions_via_javascript() {
			global $wp_filter;
			?>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('select[name^="action"] option:last-child').before('<option value="bulk_smushit">Bulk Smush</option>');
				});
			</script>
		<?php
		}
		// Handles the bulk actions POST
		// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
		function bulk_action_handler() {
			check_admin_referer( 'bulk-media' );

			if ( empty( $_REQUEST['media'] ) || ! is_array( $_REQUEST['media'] ) ) {
				return;
			}

			$ids = implode( ',', array_map( 'intval', $_REQUEST['media'] ) );

			// Can't use wp_nonce_url() as it escapes HTML entities
			$url = admin_url( 'upload.php' );
			$url = add_query_arg(
				array(
					'page'     => 'wp-smpro-admin',
					'goback'   => 1,
					'ids'      => $ids,
					'_wpnonce' => wp_create_nonce( 'wp-smpro-admin' )
				),
				$url
			);
			wp_redirect( $url );
			exit();
		}

		/**
		 * Add all attributes to
		 */
		function smush_status( $id ) {
			$response = trim( $this->set_status( $id, false ) );

			return $response;
		}

		function set_status( $id, $echo = true, $text_only = false ) {
			$is_smushed = get_post_meta( $id, "wp-smpro-is-smushed", true );

			// if the image is smushed
			if ( ! empty( $is_smushed ) ) {
				// the status
				$data = get_post_meta( $id, WP_SMPRO_PREFIX . 'smush-data', true );

				$bytes   = isset( $data['stats']['bytes'] ) ? $data['stats']['bytes'] : 0;
				$percent = isset( $data['stats']['percent'] ) ? $data['stats']['percent'] : 0;
				$percent = $percent < 0 ? 0 : $percent;

				if ( $bytes == 0 || $percent == 0 ) {
					$status_txt = __( 'Already Optimized', WP_SMPRO_DOMAIN );
				} elseif ( ! empty( $percent ) && ! empty( $data['stats']['human'] ) ) {
					$status_txt = sprintf( __( "Reduced by %s (  %01.1f%% )", WP_SMPRO_DOMAIN ), $data['stats']['human'], number_format_i18n( $percent, 2, '.', '' ) );
				}

				// check if we need to show the resmush button
				$show_button = $this->show_resmush_button( $id );

				// the button text
				$button_txt = __( 'Re-smush', WP_SMPRO_DOMAIN );
			} else {
				$sent_ids = get_option( WP_SMPRO_PREFIX . 'sent-ids', array() );

				$is_sent = in_array( $id, $sent_ids );

				if ( $is_sent ) {
					// the status
					$status_txt = __( 'Currently smushing', WP_SMPRO_DOMAIN );

					// we need to show the smush button
					$show_button = $this->show_resmush_button( $id );

					if ( ! $show_button ) {
						// the button text
						$button_txt = '';
					} else {
						// the status
						$status_txt = __( 'Not processed', WP_SMPRO_DOMAIN );

						// the button text
						$button_txt = __( 'Smush now!', WP_SMPRO_DOMAIN );
					}
				} else {

					// the status
					$status_txt = __( 'Not processed', WP_SMPRO_DOMAIN );

					// we need to show the smush button
					$show_button = true;

					// the button text
					$button_txt = __( 'Smush now!', WP_SMPRO_DOMAIN );
				}
			}
			if ( $text_only ) {
				return $status_txt;
			}
			$text = $this->column_html( $id, $status_txt, $button_txt, $show_button, $is_smushed, $echo );
			if ( ! $echo ) {
				return $text;
			}
		}
	}

}