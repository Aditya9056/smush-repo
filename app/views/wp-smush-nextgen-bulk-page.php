<?php
/**
 * Render Smush NextGen pages.
 *
 * @package WP_Smush
 */

$this->do_meta_boxes( 'summary' );
$this->do_meta_boxes( 'bulk' );

?>

<div class="sui-footer">
	<?php esc_html_e( 'Made with', 'wp-smushit' ); ?> <i class="sui-icon-heart" aria-hidden="true"></i> <?php esc_html_e( 'by WPMU DEV', 'wp-smushit' ); ?>
</div>
