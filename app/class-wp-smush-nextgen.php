<?php
/**
 * NextGen admin view: WP_Smush_Nextgen_Page class
 *
 * @package WP_Smush
 */

namespace Smush\App;

use Smush\WP_Smush;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class WP_Smush_Nextgen_Page
 */
class WP_Smush_Nextgen_Page extends WP_Smush_View {

	/**
	 * Function triggered when the page is loaded before render any content.
	 */
	public function on_load() {
		// Localize variables for NextGen Manage gallery page.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Register meta boxes.
	 */
	public function register_meta_boxes() {
		$this->add_meta_box(
			'meta-boxes/summary',
			null,
			array( $this, 'dashboard_summary_metabox' ),
			null,
			null,
			'summary',
			array(
				'box_class'         => 'sui-box sui-summary sui-summary-smush-nextgen',
				'box_content_class' => false,
			)
		);

		$class = WP_Smush::is_pro() ? 'bulk-smush-wrapper wp-smush-pro-install' : 'bulk-smush-wrapper';
		$this->add_meta_box(
			'meta-boxes/bulk',
			__( 'Bulk Smush', 'wp-smushit' ),
			array( $this, 'bulk_metabox' ),
			array( $this, 'bulk_header_metabox' ),
			null,
			'bulk',
			array(
				'box_class' => "sui-box {$class}",
			)
		);
	}

	/**
	 * Enqueue Scripts on Manage Gallery page
	 */
	public function enqueue() {
		$current_screen = get_current_screen();
		if ( ! empty( $current_screen ) && 'nggallery-manage-images' === $current_screen->base ) {
			WP_Smush::get_instance()->core()->nextgen->ng_admin->localize();
		}
	}


	/**
	 * NextGen summary meta box.
	 */
	public function dashboard_summary_metabox() {
		$ng = WP_Smush::get_instance()->core()->nextgen->ng_admin;

		$lossy_enabled = WP_Smush::is_pro() && $this->settings->get( 'lossy' );

		$smushed_image_count = 0;
		if ( $lossy_enabled ) {
			$smushed_image = $ng->ng_stats->get_ngg_images( 'smushed' );
			if ( ! empty( $smushed_image ) && is_array( $smushed_image ) && ! empty( $this->resmush_ids ) && is_array( $this->resmush_ids ) ) {
				// Get smushed images excluding resmush IDs.
				$smushed_image = array_diff_key( $smushed_image, array_flip( $this->resmush_ids ) );
			}
			$smushed_image_count = is_array( $smushed_image ) ? count( $smushed_image ) : 0;
		}

		$this->view(
			'meta-boxes/nextgen/summary-meta-box',
			array(
				'image_count'         => $ng->image_count,
				'lossy_enabled'       => $lossy_enabled,
				'smushed_image_count' => $smushed_image_count,
				'stats_human'         => $ng->stats['human'] > 0 ? $ng->stats['human'] : '0 MB',
				'stats_percent'       => $ng->stats['percent'] > 0 ? number_format_i18n( $ng->stats['percent'], 1 ) : 0,
				'total_count'         => $ng->total_count,
			)
		);
	}

	/**
	 * NextGen bulk Smush header meta box.
	 */
	public function bulk_header_metabox() {
		$this->view(
			'meta-boxes/nextgen/meta-box-header',
			array(
				'title' => __( 'Bulk Smush', 'wp-smushit' ),
			)
		);
	}

	/**
	 * NextGen bulk Smush meta box.
	 */
	public function bulk_metabox() {
		$ng = WP_Smush::get_instance()->core()->nextgen->ng_admin;

		$resmush_ids = get_option( 'wp-smush-nextgen-resmush-list', false );

		$count = $resmush_ids ? count( $resmush_ids ) : 0;

		// Whether to show the remaining re-smush notice.
		$show = $count > 0 ? true : false;

		$count += $ng->remaining_count;

		$url = add_query_arg(
			array(
				'page' => 'smush#wp-smush-settings-box',
			),
			admin_url( 'upload.php' )
		);

		$this->view(
			'meta-boxes/nextgen/meta-box',
			array(
				'all_done'        => ( $ng->smushed_count == $ng->total_count ) && 0 == count( $ng->resmush_ids ),
				'count'           => $count,
				'lossy_enabled'   => WP_Smush::is_pro() && $this->settings->get( 'lossy' ),
				'ng'              => $ng,
				'remaining_count' => $ng->remaining_count,
				'resmush_ids'     => $ng->resmush_ids,
				'show'            => $show,
				'total_count'     => $ng->total_count,
				'url'             => $url,
			)
		);
	}

}
