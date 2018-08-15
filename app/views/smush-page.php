<?php
/**
 * Render page.
 *
 * @package WP_Smush
 */

$this->do_meta_boxes( 'summary' );
?>

<div class="sui-row-with-sidenav">
	<?php $this->show_tabs(); ?>
	<?php $this->do_meta_boxes( $this->get_current_tab() ); ?>
</div><!-- end row -->
