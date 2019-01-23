<?php
/**
 * Lazy-load meta box footer.
 *
 * @since 3.2.0
 * @package WP_Smush
 */

?>

<div class="sui-actions-right">
	<button type="submit" class="sui-button sui-button-blue" id="wp-smush-save-settings">
		<i class="sui-icon-save" aria-hidden="true"></i>
		<?php esc_html_e( 'Save changes', 'wp-smushit' ); ?>
	</button>

	<span class="sui-icon-loader sui-loading sui-hidden"></span>
</div>
