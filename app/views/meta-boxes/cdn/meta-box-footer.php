<?php
/**
 * CDN meta box footer.
 *
 * @since 3.0
 * @package WP_Smush
 */

$enabled     = WP_Smush::get_instance()->core()->mod->cdn->get_status();
$button_text = $enabled ? __( 'Update Settings', 'wp-smushit' ) : __( 'Save & Activate', 'wp-smushit' );

?>

<div class="sui-actions-right">
	<span class="wp-smush-submit-wrap">
		<input type="submit" id="wp-smush-save-settings" class="sui-button sui-button-primary" value="<?php echo esc_attr( $button_text ); ?>">
		<span class="sui-icon-loader sui-loading sui-hidden"></span>
	</span>
</div>


<script type="text/javascript">
	window.WP_Smush.CDN.init();
</script>
