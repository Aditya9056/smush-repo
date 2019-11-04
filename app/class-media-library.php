<?php
/**
 * Media library class.
 *
 * Responsible for displaying a UI (stats + action links) in the media library and the editor.
 *
 * @since 3.4.0
 * @package Smush\App
 */

namespace Smush\App;

use Smush\Core\Modules\Smush;
use Smush\WP_Smush;
use WP_Query;

/**
 * Class Media_Library
 */
class Media_Library {

	/**
	 * Media_Library constructor.
	 */
	public function __construct() {
		// Media library columns.
		add_filter( 'manage_media_columns', array( $this, 'columns' ) );
		add_filter( 'manage_upload_sortable_columns', array( $this, 'sortable_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'custom_column' ), 10, 2 );

		// Manage column sorting.
		add_action( 'pre_get_posts', array( $this, 'smushit_orderby' ) );

		// Smush image filter from Media Library.
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_query' ) );
		// Smush image filter from Media Library (list view).
		add_action( 'restrict_manage_posts', array( $this, 'add_filter_dropdown' ) );
	}

	/**
	 * Print column header for Smush results in the media library using the `manage_media_columns` hook.
	 *
	 * @param array $defaults  Defaults array.
	 *
	 * @return array
	 */
	public function columns( $defaults ) {
		$defaults['smushit'] = 'Smush';

		return $defaults;
	}

	/**
	 * Add the Smushit Column to sortable list
	 *
	 * @param array $columns  Columns array.
	 *
	 * @return array
	 */
	public function sortable_column( $columns ) {
		$columns['smushit'] = 'smushit';

		return $columns;
	}

	/**
	 * Print column data for Smush results in the media library using
	 * the `manage_media_custom_column` hook.
	 *
	 * @param string $column_name  Column name.
	 * @param int    $id           Attachment ID.
	 */
	public function custom_column( $column_name, $id ) {
		if ( 'smushit' === $column_name ) {
			echo WP_Smush::get_instance()->core()->mod->smush->set_status( $id );
		}
	}


	/**
	 * Order by query for smush columns.
	 *
	 * @param WP_Query $query  Query.
	 *
	 * @return WP_Query
	 */
	public function smushit_orderby( $query ) {
		global $current_screen;

		// Filter only media screen.
		if ( ! is_admin() || ( ! empty( $current_screen ) && 'upload' !== $current_screen->base ) ) {
			return $query;
		}

		if ( isset( $_REQUEST['smush-filter'] ) && 'ignored' === $_REQUEST['smush-filter'] ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => WP_SMUSH_PREFIX . 'ignore-bulk',
						'value'   => 'true',
						'compare' => 'EXISTS',
					),
				)
			);

			return $query;
		}

		$orderby = $query->get( 'orderby' );

		if ( isset( $orderby ) && 'smushit' === $orderby ) {
			$query->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     => Smush::$smushed_meta_key,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => Smush::$smushed_meta_key,
						'compare' => 'NOT EXISTS',
					),
				)
			);
			$query->set( 'orderby', 'meta_value_num' );
		}

		return $query;
	}

	/**
	 * Add our filter to the media query filter in Media Library.
	 *
	 * @since 2.9.0
	 *
	 * @see wp_ajax_query_attachments()
	 *
	 * @param array $query  Query.
	 *
	 * @return mixed
	 */
	public function filter_media_query( $query ) {
		if ( isset( $_POST['query']['stats'] ) && 'null' === $_POST['query']['stats'] ) {
			$query['meta_query'] = array(
				array(
					'key'     => WP_SMUSH_PREFIX . 'ignore-bulk',
					'value'   => 'true',
					'compare' => 'EXISTS',
				),
			);
		}

		return $query;
	}

	/**
	 * Adds a search dropdown in Media Library list view to filter out images that have been
	 * ignored with bulk Smush.
	 *
	 * @since 3.2.0
	 */
	public function add_filter_dropdown() {
		$scr = get_current_screen();

		if ( 'upload' !== $scr->base ) {
			return;
		}

		$ignored = filter_input( INPUT_GET, 'smush-filter', FILTER_SANITIZE_STRING );

		?>
		<select class="smush-filters" name="smush-filter" id="smush_filter">
			<option value="" <?php selected( $ignored, '' ); ?>><?php esc_html_e( 'Smush: All images', 'wp-smushit' ); ?></option>
			<option value="ignored" <?php selected( $ignored, 'ignored' ); ?>><?php esc_html_e( 'Smush: Bulk ignored', 'wp-smushit' ); ?></option>
		</select>
		<?php
	}

}
