<?php
/**
 * Bulk Smush meta box when CDN is active.
 *
 * @since 3.0
 * @package WP_Smush
 */

?>
<div class="sui-notice smush-notice-sm">
	<p>
		<?php
		esc_html_e(
			'Bulk smushing is disabled whilst your images are being served from the CDN. The CDN
		automatically smushes and serves your images at the correct sizes so you no longer have to bulk smush
		your images.',
			'wp-smushit'
		);
		?>
	</p>
</div>

<span class="sui-description" id="wp-smush-s3-desc">
	<?php
	esc_html_e(
		'Note: You can still smush folders using Directory Smushing, it will use the settings you
	have defined below.',
		'wp-smushit'
	);
	?>
</span>
