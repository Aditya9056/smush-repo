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
if ( ! class_exists( 'WpSmProBulk' ) ) {

	/**
	 * Provides Bulk Smushing user interface
	 *
	 */
	class WpSmProBulk {

		/**
		 * Constructor.
		 * adds a bulk menu and screen
		 * hooks ajax callback for receipt checking
		 */
		public function __construct() {

			// hook scripts and styles
			add_action( 'admin_init', array( $this, 'register' ) );
			// hook custom screen
			add_action( 'admin_menu', array( $this, 'screen' ) );
			// hook ajax call for checking smush status
			add_action( 'wp_ajax_wp_smpro_check', array( $this, 'check_status' ) );
		}

		/**
		 * Add Bulk option settings page
		 */
		function screen() {
			$bulk_page_suffix = add_media_page( 'Bulk Smush.it', 'Bulk Smush.it', 'edit_others_posts', 'wp-smpro-bulk', array(
				$this,
				'bulk_ui'
			) );

			// enqueue js only on this screen
			add_action( 'admin_print_scripts-' . $bulk_page_suffix, array( $this, 'enqueue' ) );
		}

		/**
		 * Register js and css
		 */
		function register() {
			/* Register our script. */
			wp_register_script( 'wp-smpro-queue', WP_SMPRO_URL . 'js/wp-smpro-queue.js', array( 'jquery' ), WP_SMPRO_VERSION );
			//@todo enqueue minified script if not debugging
			//wp_register_script( 'wp-smpro-queue-debug', trailingslashit(WP_SMPRO_DIR).'js/wp-smpro-queue.js' );
			wp_register_style( 'wp-smpro-queue', WP_SMPRO_URL . 'css/wp-smpro-queue.css' );
		}

		/**
		 * enqueue js and css
		 */
		function enqueue() {
			wp_enqueue_script( 'wp-smpro-queue' );
			wp_enqueue_style( 'wp-smpro-queue' );
		}

		/**
		 * Check current smush status of sent attachments
		 */
		function check_status() {

			// the attachment id
			$id = $_GET[ attachment_id ];

			// send 0, means unknown error
			if ( empty( $id ) || $id <= 0 ) {
				echo 0;
				die();
			}
			// otherwise, get smush details
			$smush_meta = get_post_meta( $id, 'smush_meta', true );

			// if can't find, it's still awaited
			if ( empty( $smush_meta ) || empty( $smush_meta['full'] ) ) {
				echo 1;
				die();
			}

			// otherwise, we've received the image
			$code = intval( $smush_meta['full']['status_code'] );

			if ( $code === 4 || $code === 6 ) {
				echo 2;
				die();
			}

			if ( $code === 5 ) {
				// smush failed
				echo - 1;
				die();
			}

			// Not even that, we're still waiting
			echo 1;
			die();
		}

		/**
		 * The images that still need to be smushed
		 *
		 * @global object $wpdb WP database object
		 * @return int count of smushed images
		 */
		function image_count( $type = 'unsmushed' ) {
			// the cache key
			$cache_key = "wp-smpro-to-$type-count";

			// get it from cache
			$count = wp_cache_get( $cache_key );

			// if not in cache, query db
			if ( false === $count ) {
				$meta_query = array(
					'relation' => 'OR',
					array(
						'key'     => 'wp-smpro-is-smushed',
						'compare' => 'NOT EXISTS'
					),
					array(
						'key'   => 'wp-smpro-is-smushed',
						'value' => 0
					)
				);

				$query = array(
					'fields'         => 'ids',
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
					'order'          => 'ASC',
				);

				if ( $type == 'unsmushed' ) {
					$query['meta_query'] = $meta_query;
				}

				$results = new WP_Query( $query );
				$count   = ! empty ( $results->post_count ) ? $results->post_count : '';
				// update cache
				wp_cache_set( $cache_key, $count );
			}

			// send the count
			return $count;
		}

		/**
		 * The first id to start from
		 *
		 * @global object $wpdb WP database object
		 * @return int Attachmment id to start bulk smushing from
		 */
		function start_id() {
			$query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => 1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'wp-smpro-is-smushed',
						'compare' => 'NOT EXISTS'
					),
					array(
						'key'   => 'wp-smpro-is-smushed',
						'value' => 0
					)
				)

			);

			$results = new WP_Query( $query );
			$id      = ! empty ( $results->posts ) ? $results->posts[0] : '';

			return $id;
		}

		/**
		 * Display the UI
		 */
		function bulk_ui() {
			global $wpdb;

			// if a fixed number of ids were sent
			$idstr = isset( $_REQUEST['ids'] ) ? $_REQUEST['ids'] : '';

			// get the ids to bulk smush in an array
			$ids = ( ! empty( $idstr ) ) ? explode( ',', $idstr ) : array();

			// set the start id to string for js
			$start_id = 'null';

			// set up counts and start_ids in each case
			if ( ! empty( $ids ) ) {
				$total    = count( $ids );
				$progress = 0;
			} else {
				$total    = $this->image_count( 'all' );
				$progress = (int) $this->image_count();
				$start_id = $this->start_id();
			}

			// how many remaining?
			$remaining = $total - $progress;

			// print out some js vars
			?>
			<script type="text/javascript">
				var wp_smpro_total = <?php echo $total; ?>;
				var wp_smpro_progress = <?php echo $progress; ?>;
				var wp_smpro_ids = [<?php echo $idstr; ?>];
				var wp_smpro_start_id = "<?php echo $start_id; ?>";
			</script>
			<?php
			// print out html
			?>
			<div class="wrap">
				<div id="icon-upload" class="icon32"><br/></div>
				<h2><?php _e( 'Bulk WP Smush.it Pro', WP_SMPRO_DOMAIN ) ?></h2>

				<div class="bulk_queue_wrap">

					<?php
					if ( $total < 1 ) {
						_e( "<p>You don't appear to have uploaded any images yet.</p>", WP_SMPRO_DOMAIN );
						?>
					<?php
					} else {
						$disabled = '';
						?>
						<div class="status-div"> 
							<span class="single-status">
								<?php
								if ( $remaining === 0 ) {
									$disabled = ' disabled="disabled"';
									_e( 'All the images are already smushed', WP_SMPRO_DOMAIN );
								} else {
									printf( __( 'Ready to send %1d of %2d attachments for smushing', WP_SMPRO_DOMAIN ), $remaining, $total );
								}
								?>
							</span>
						</div>
						<?php

						$percent = ( $progress / $total ) * 100;
						// print progress bars
						$this->progress_ui( $percent, 'wp-smpro-sent' );
						$this->progress_ui( $percent, 'wp-smpro-received' );
						?>
						<input type="submit" id="wp-sm-pro-begin" class="button button-primary"<?php echo $disabled; ?> value="Start"/>
					<?php
					}
					?>
				</div>
			</div>
		<?php
		}

		/**
		 * Show a progress bar
		 *
		 * @param int $progress the progress percent
		 * @param string $id The element id for DOM manipulation
		 */
		function progress_ui( $progress, $id ) {
			$progress_ui = '
				<div id="' . $id . '" class="wp-smpro-progressbar">
				    <div style="width:' . $progress . '%"></div>
				</div>
				';
			echo $progress_ui;
		}

		/**
		 * Calculate progress %age for progress bar
		 *
		 * @param type $progress
		 * @param type $total
		 *
		 * @return int
		 */
		function progress( $progress, $total ) {
			if ( $total < 1 ) {
				return 100;
			}

			return ( $progress / $total ) * 100;
		}

	}

}