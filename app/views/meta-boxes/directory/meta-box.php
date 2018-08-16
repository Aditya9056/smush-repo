<?php
/**
 * Directory Smush meta box.
 *
 * @package WP_Smush
 *
 * @var string $root_path    Root path.
 * @var string $upgrade_url  Upgrade URL.
 */

?>

<?php wp_nonce_field( 'smush_get_dir_list', 'list_nonce' ); ?>
<?php wp_nonce_field( 'smush_get_image_list', 'image_list_nonce' ); ?>

<!-- Directory Path -->
<input type="hidden" class="wp-smush-dir-path" value="" />
<input type="hidden" name="wp-smush-base-path" value="<?php echo esc_attr( $root_path ); ?>" />

<div class="wp-smush-scan-result">
	<div class="content">
		<span class="wp-smush-no-image tc">
			<img src="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/smush-no-media.png' ); ?>" alt="<?php esc_html_e( 'Directory Smush - Choose Folder', 'wp-smushit' ); ?>">
		</span>
		<p class="wp-smush-no-images-content tc roboto-regular">
			<?php esc_html_e( 'In addition to smushing your media uploads, you may want to also smush images living outside your uploads directory.', 'wp-smushit' ); ?><br>
			<?php esc_html_e( 'Get started by adding files and folders you wish to optimize.', 'wp-smushit' ); ?>
		</p>
		<span class="wp-smush-upload-images sui-no-padding-bottom tc">
			<button type="button" class="sui-button sui-button-primary wp-smush-browse tc" data-a11y-dialog-show="wp-smush-list-dialog">
				<?php esc_html_e( 'CHOOSE FOLDER', 'wp-smushit' ); ?>
			</button>
		</span>
	</div>
	<!-- Notices -->
	<?php $this->smush_result_notice(); ?>
	<div class="sui-notice sui-notice-info wp-smush-dir-limit sui-hidden">
		<p>
			<?php
			printf(
				/* translators: %1$s: a tag start, %2$s: closing a tag */
				esc_html__( '%1$sUpgrade to pro%2$s to bulk smush all your directory images with one click. Free users can smush 50 images with each click.', 'wp-smushit' ),
				'<a href="' . esc_url( $upgrade_url ) . '" target="_blank" title="' . esc_html__( 'Smush Pro', 'wp-smushit' ) . '">',
				'</a>'
			);
			?>
		</p>
	</div>
	<?php wp_nonce_field( 'wp_smush_all', 'wp-smush-all' ); ?>
</div>

<?php
$current_screen = get_current_screen();
if ( ! empty( $current_screen ) && ! empty( $current_screen->base ) && ( 'toplevel_page_smush' === $current_screen->base || 'toplevel_page_smush-network' === $current_screen->base ) ) {
	$this->view( 'modals/directory-list' );
	$this->view( 'modals/progress-dialog' );
}
