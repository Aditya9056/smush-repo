<?php
/**
 * Provides Bulk Smushing user interface
 * 
 * @package SmushItPro/Admin
 * 
 * @version 1.0
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 * 
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if (!class_exists('WpSmProBulk')) {

	class WpSmProBulk {

		public function __construct() {
			add_action('admin_init', array(&$this, 'admin_init'));
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_action('wp_ajax_wp_smpro_check', array(&$this, 'check_status'));
		}

		/**
		 * Add Bulk option settings page
		 */
		function admin_menu() {
			$bulk_page_suffix = add_media_page('Bulk Smush.it', 'Bulk Smush.it', 'edit_others_posts', 'wp-smpro-bulk', array(
			    &$this,
			    'bulk_ui'
			));

			// enqueue js only on this screen
			add_action('admin_print_scripts-' . $bulk_page_suffix, array(&$this, 'enqueue'));
		}

		/**
		 * Register js
		 */
		function admin_init() {
			/* Register our script. */
			wp_register_script('wp-smpro-queue', WP_SMPRO_URL . 'js/wp-smpro-queue.js', array('jquery'), WP_SMPRO_VERSION);
			//@todo enqueue minified script if not debugging
			//wp_register_script( 'wp-smpro-queue-debug', trailingslashit(WP_SMPRO_DIR).'js/wp-smpro-queue.js' );
			wp_register_style('wp-smpro-queue', WP_SMPRO_URL . 'css/wp-smpro-queue.css');
		}

		/**
		 * enqueue js
		 */
		function enqueue() {
			wp_enqueue_script('wp-smpro-queue');
			wp_enqueue_style('wp-smpro-queue');
		}

		function check_status() {

			$id = $_GET[attachment_id];
			if (empty($id) || $id <= 0) {
				echo 0;
				die();
			}
			$smush_meta = get_post_meta($id, 'smush_meta', true);

			if (empty($smush_meta) || empty($smush_meta['full'])) {
				echo 1;
				die();
			}

			$code = intval($smush_meta['full']['status_code']);
			if ($code === 4 || $code === 6) {
				echo 2;
				die();
			}

			echo -1;
			die();
		}

		/**
		 * The images that still need to be smushed
		 * 
		 * @global type $wpdb
		 * @return type
		 */
		function smushed_count() {
			global $wpdb;

			$cache_key = 'wp-smpro-to-smush-count';

			$query = "SELECT COUNT(p.ID) FROM {$wpdb->posts} as p "
				. "LEFT JOIN {$wpdb->postmeta} pm ON "
				. "(p.ID = pm.post_id) "
				. "WHERE (p.post_type = 'attachment') "
				. "AND ("
				. "p.post_mime_type = 'image/jpeg' "
				. "OR p.post_mime_type = 'image/png' "
				. "OR p.post_mime_type = 'image/gif'"
				. ") "
				. "AND ( "
				. "pm.meta_key = 'wp-smpro-is-smushed' "
				. "AND CAST(pm.meta_value AS CHAR) = '1'"
				. ") ";

			$count = wp_cache_get($cache_key, 'count');
			if (false === $count) {
				$count = $wpdb->get_var($wpdb->prepare($query, 1));
				wp_cache_set($cache_key, $count);
			}

			return $count;
		}

		function start_id() {
			global $wpdb;

			$query = "SELECT p.ID FROM {$wpdb->posts} p " .
				"INNER JOIN {$wpdb->postmeta} pm ON "
				. "(p.ID = pm.post_id) " .
				"LEFT JOIN {$wpdb->postmeta} pmm ON "
				. "(p.ID = pmm.post_id AND pmm.meta_key = 'wp-smpro-is-smushed') "
				. "WHERE p.post_type = 'attachment' "
				. "AND ("
				. "p.post_mime_type = 'image/jpeg' "
				. "OR p.post_mime_type = 'image/png' "
				. "OR p.post_mime_type = 'image/gif'"
				. ") "
				. "AND ( "
				. "("
				. "pm.meta_key = 'wp-smpro-is-smushed' "
				. "AND CAST(pm.meta_value AS CHAR) = '0'"
				. ") "
				. "OR  pmm.post_id IS NULL"
				. ") "
				. "GROUP BY p.ID "
				. "ORDER BY p.post_date ASC LIMIT 0, 1";
			$id = $wpdb->get_var($query);

			return $id;
		}

		/**
		 * Display the UI
		 */
		function bulk_ui() {
			global $wpdb;

			$idstr = isset($_REQUEST['ids']) ? $_REQUEST['ids'] : '';
			$ids = (!empty($idstr)) ? explode(',', $idstr) : array();
			$start_id = 'null';
			if (!empty($ids)) {
				$total = count($ids);
				$progress = 0;
			} else {
				$total = $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
					. " AND (post_mime_type= 'image/jpeg' OR post_mime_type= 'image/png' OR post_mime_type= 'image/gif')");
				$progress = (int) $this->smushed_count();
				$start_id = $this->start_id();
			}

			$remaining = $total - $progress;
			?>
			<script type="text/javascript">
				var wp_smpro_total = <?php echo $total; ?>;
				var wp_smpro_progress = <?php echo $progress; ?>;
				var wp_smpro_ids = [<?php echo $idstr; ?>];
				var wp_smpro_start_id = "<?php echo $start_id; ?>";
			</script>

			<div class="wrap">
				<div id="icon-upload" class="icon32"><br/></div>
				<h2><?php _e('Bulk WP Smush.it Pro', WP_SMPRO_DOMAIN) ?></h2>
				<div class="bulk_queue_wrap">

					<?php
					if ($total < 1) {
						_e("<p>You don't appear to have uploaded any images yet.</p>", WP_SMPRO_DOMAIN);
						?>
						<?php
					} else {
						$disabled = '';
						?>
						<div class="status-div"> 
							<span class="single-status">
								<?php
								if ($remaining === 0) {
									$disabled = ' disabled="disabled"';
									_e('All the images are already smushed', WP_SMPRO_DOMAIN);
								} else {
									printf(__('Ready to send %1d of %2d attachments for smushing', WP_SMPRO_DOMAIN), $remaining, $total);
								}
								?>
							</span>
						</div>
						<?php
						$percent = ($progress / $total) * 100;
						$this->progress_ui($percent, 'wp-smpro-sent');
						?>
						<?php
						$percent = ($progress / $total) * 100;
						$this->progress_ui($percent, 'wp-smpro-received');
						?>
						<input type="submit" id="wp-sm-pro-begin" class="button button-primary"<?php echo $disabled; ?> value="Start" />
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
		 * @param type $progress
		 * @return string
		 */
		function progress_ui($progress, $id) {
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
		 * @return int
		 */
		function progress($progress, $total) {
			if ($total < 1) {
				return 100;
			}
			return ($progress / $total) * 100;
		}

	}

}
