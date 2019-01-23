<?php
/**
 * Lazy-load meta box.
 *
 * @since 3.2.0
 * @package WP_Smush
 */

?>

<form id="wp-smush-settings-form" method="post">
	<input type="hidden" name="setting_form" id="setting_form" value="lazy_load">
	<?php if ( is_multisite() && is_network_admin() ) : ?>
		<input type="hidden" name="wp-smush-networkwide" id="wp-smush-networkwide" value="1">
		<input type="hidden" name="setting-type" value="network">
	<?php endif; ?>

	<p>
		<?php
		esc_html_e( 'This feature defers the loading of below the fold imagery until the page has loaded. This reduces load on your server and speeds up the page load time.', 'wp-smushit' );
		?>
	</p>

	<div class="sui-notice sui-notice-info smush-notice-sm">
		<p><?php esc_html_e( 'Lazyloading is active.', 'wp-smushit' ); ?></p>
	</div>

	<div class="sui-box-settings-row">
		<div class="sui-box-settings-col-1">
			<span class="sui-settings-label">
				<?php esc_html_e( 'Media Types', 'wp-smushit' ); ?>
			</span>
			<span class="sui-description">
				<?php esc_html_e( 'Choose which media types you want to lazyload.', 'wp-smushit' ); ?>
			</span>
		</div>
		<div class="sui-box-settings-col-2">
			<div class="sui-side-tabs sui-tabs">
				<div data-tabs>
					<div class="active"><?php esc_html_e( 'Images', 'wp-smushit' ); ?></div>
					<div><?php esc_html_e( 'Video', 'wp-smushit' ); ?></div>
				</div>

				<div data-panes>
					<div class="sui-tab-boxed active">
						<small><?php esc_html_e( 'Formats', 'wp-smushit' ); ?></small>
						<label for="format-jpeg" class="sui-checkbox sui-checkbox-stacked sui-checkbox-sm">
							<input type="checkbox" id="format-jpeg" />
							<span aria-hidden="true"></span>
							<span><?php esc_html_e( '.jpeg', 'wp-smushit' ); ?></span>
						</label>
						<label for="format-png" class="sui-checkbox sui-checkbox-stacked sui-checkbox-sm">
							<input type="checkbox" id="format-png" />
							<span aria-hidden="true"></span>
							<span><?php esc_html_e( '.png', 'wp-smushit' ); ?></span>
						</label>
						<label for="format-gif" class="sui-checkbox sui-checkbox-stacked sui-checkbox-sm">
							<input type="checkbox" id="format-gif" />
							<span aria-hidden="true"></span>
							<span><?php esc_html_e( '.gif', 'wp-smushit' ); ?></span>
						</label>
					</div>
					<div class="sui-tab-boxed"><p>Content. Tab 2.</p></div>
				</div>
			</div>
		</div>
	</div>

	<div class="sui-box-settings-row">
		<div class="sui-box-settings-col-1">
			<span class="sui-settings-label">
				<?php esc_html_e( 'Output Locations', 'wp-smushit' ); ?>
			</span>
			<span class="sui-description">
				<?php esc_html_e( 'By default we will lazyload all images, but you can refine this to specific media outputs too.', 'wp-smushit' ); ?>
			</span>
		</div>
		<div class="sui-box-settings-col-2">
			<label for="output-content" class="sui-checkbox sui-checkbox-stacked">
				<input type="checkbox" id="output-content" />
				<span aria-hidden="true"></span>
				<span><?php esc_html_e( 'Content', 'wp-smushit' ); ?></span>
			</label>
			<label for="output-widgets" class="sui-checkbox sui-checkbox-stacked">
				<input type="checkbox" id="output-widgets" />
				<span aria-hidden="true"></span>
				<span><?php esc_html_e( 'Widgets', 'wp-smushit' ); ?></span>
			</label>
			<label for="output-thumbnails" class="sui-checkbox sui-checkbox-stacked">
				<input type="checkbox" id="output-thumbnails" />
				<span aria-hidden="true"></span>
				<span><?php esc_html_e( 'Post Thumbnail', 'wp-smushit' ); ?></span>
			</label>
			<label for="output-gravatars" class="sui-checkbox sui-checkbox-stacked">
				<input type="checkbox" id="output-gravatars" />
				<span aria-hidden="true"></span>
				<span><?php esc_html_e( 'Gravatars', 'wp-smushit' ); ?></span>
			</label>
		</div>
	</div>

	<div class="sui-box-settings-row">
		<div class="sui-box-settings-col-1">
			<span class="sui-settings-label">
				<?php esc_html_e( 'Animation', 'wp-smushit' ); ?>
			</span>
			<span class="sui-description">
				<?php esc_html_e( 'Choose how you want to animate media when they scroll into view.', 'wp-smushit' ); ?>
			</span>
		</div>
		<div class="sui-box-settings-col-2">
			<div class="sui-side-tabs sui-tabs">
				<div data-tabs>
					<div class="active"><?php esc_html_e( 'Fade In', 'wp-smushit' ); ?></div>
					<div><?php esc_html_e( 'Spinner', 'wp-smushit' ); ?></div>
				</div>

				<div data-panes>
					<div class="sui-tab-boxed active">
						<div class="sui-form-field-inline">
							<div class="sui-form-field">
								<label for="fadein-duration" class="sui-label"><?php esc_html_e( 'Duration', 'wp-smushit' ); ?></label>
								<input type="number" placeholder="400" id="fadein-duration" class="sui-form-control sui-input-sm sui-field-has-suffix">
								<span class="sui-field-suffix"><?php esc_html_e( 'ms', 'wp-smushit' ); ?></span>
							</div>
							<div class="sui-form-field">
								<label for="fadein-delay" class="sui-label"><?php esc_html_e( 'Delay', 'wp-smushit' ); ?></label>
								<input type="number" placeholder="0" id="fadein-delay" class="sui-form-control sui-input-sm sui-field-has-suffix">
								<span class="sui-field-suffix"><?php esc_html_e( 'ms', 'wp-smushit' ); ?></span>
							</div>
						</div>
					</div>
					<div class="sui-tab-boxed"><p>Content. Tab 2.</p></div>
				</div>
			</div>
		</div>
	</div>

	<div class="sui-box-settings-row">
		<div class="sui-box-settings-col-1">
			<span class="sui-settings-label">
				<?php esc_html_e( 'Offset', 'wp-smushit' ); ?>
			</span>
			<span class="sui-description">
				<?php esc_html_e( 'Control when to trigger the image to show as it scrolls into the viewport.', 'wp-smushit' ); ?>
			</span>
		</div>
		<div class="sui-box-settings-col-2">
			<label for="offset" class="sui-label"><?php esc_html_e( 'Offset', 'wp-smushit' ); ?></label>
			<input type="text" placeholder="<?php esc_attr_e( 'E.g. 100px', 'wp-smushit' ); ?>" id="offset" class="sui-form-control">
			<div class="sui-description">
				<?php esc_html_e( 'You can use both positive and negative values, % or px to control when the media should be shown. ', 'wp-smushit' ); ?>
			</div>
		</div>
	</div>

	<div class="sui-box-settings-row">
		<div class="sui-box-settings-col-1">
			<span class="sui-settings-label">
				<?php esc_html_e( 'Include / Exclude', 'wp-smushit' ); ?>
			</span>
			<span class="sui-description">
				<?php esc_html_e( 'Disable lazyloading for specific pages, posts or image classes that you wish to prevent lazyloading on.', 'wp-smushit' ); ?>
			</span>
		</div>
		<div class="sui-box-settings-col-2">
			<div class="sui-form-field">
				<strong><?php esc_html_e( 'Post Types', 'wp-smushit' ); ?></strong>
				<div class="sui-description">
					<?php esc_html_e( 'Choose the post types you want to lazyload.', 'wp-smushit' ); ?>
				</div>
				<table class="sui-table">
					<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'wp-smushit' ); ?></th>
						<th><?php esc_html_e( 'Type', 'wp-smushit' ); ?></th>
						<th>&nbsp;</th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Frontpage', 'wp-smushit' ); ?></strong></td>
						<td>frontpage</td>
						<td>
							<label class="sui-toggle" for="include-frontpage">
								<input type="checkbox" name="include-frontpage" id="include-frontpage">
								<span class="sui-toggle-slider"></span>
							</label>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Blog', 'wp-smushit' ); ?></strong></td>
						<td>home</td>
						<td>
							<label class="sui-toggle" for="include-home">
								<input type="checkbox" name="include-home" id="include-home">
								<span class="sui-toggle-slider"></span>
							</label>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Pages', 'wp-smushit' ); ?></strong></td>
						<td>page</td>
						<td>
							<label class="sui-toggle" for="include-page">
								<input type="checkbox" name="include-page" id="include-page">
								<span class="sui-toggle-slider"></span>
							</label>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Posts', 'wp-smushit' ); ?></strong></td>
						<td>single</td>
						<td>
							<label class="sui-toggle" for="include-single">
								<input type="checkbox" name="include-single" id="include-single">
								<span class="sui-toggle-slider"></span>
							</label>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Archives', 'wp-smushit' ); ?></strong></td>
						<td>archive</td>
						<td>
							<label class="sui-toggle" for="include-archive">
								<input type="checkbox" name="include-archive" id="include-archive">
								<span class="sui-toggle-slider"></span>
							</label>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Categories', 'wp-smushit' ); ?></strong></td>
						<td>category</td>
						<td>
							<label class="sui-toggle" for="include-category">
								<input type="checkbox" name="include-category" id="include-category">
								<span class="sui-toggle-slider"></span>
							</label>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Tags', 'wp-smushit' ); ?></strong></td>
						<td>tag</td>
						<td>
							<label class="sui-toggle" for="include-tag">
								<input type="checkbox" name="include-tag" id="include-tag">
								<span class="sui-toggle-slider"></span>
							</label>
						</td>
					</tr>
					</tbody>
				</table>
			</div>

			<div class="sui-form-field">
				<strong><?php esc_html_e( 'Post, Pages & URLs', 'wp-smushit' ); ?></strong>
				<div class="sui-description">
					<?php esc_html_e( 'Add URLs to the posts and/or pages you want to disable lazyloading on.', 'wp-smushit' ); ?>
				</div>
				<textarea class="sui-form-control" placeholder="<?php esc_attr_e( 'E.g. /page', 'wp-smushit' ); ?>"></textarea>
				<div class="sui-description">
					<?php
					printf(
						/* translators: %1$s - opening strong tag, %2$s - closing strong tag */
						esc_html__( 'Add page or post URLs one per line in relative format. I.e. %1$s/example-page%2$s or %1$s/example-page/sub-page/%2$s.', 'wp-smushit' ),
						'<strong>',
						'</strong>'
					);
					?>
				</div>
			</div>

			<div class="sui-form-field">
				<strong><?php esc_html_e( 'Classes & IDs', 'wp-smushit' ); ?></strong>
				<div class="sui-description">
					<?php esc_html_e( 'Additionally, you can specify classes or IDs to avoid lazyloading. This gives you absolute control over each image on a page, not just the page itself.', 'wp-smushit' ); ?>
				</div>
				<textarea class="sui-form-control" placeholder="<?php esc_attr_e( 'Add classes or IDs, one per line', 'wp-smushit' ); ?>"></textarea>
				<div class="sui-description">
					<?php
					printf(
						/* translators: %1$s - opening strong tag, %2$s - closing strong tag */
						esc_html__( 'Add one class or ID per line, including the prefix. E.g %1$s#image-id%2$s or %1$s#image-class%2$s.', 'wp-smushit' ),
						'<strong>',
						'</strong>'
					);
					?>
				</div>
			</div>
		</div>
	</div>

	<div class="sui-box-settings-row">
		<div class="sui-box-settings-col-1">
			<span class="sui-settings-label">
				<?php esc_html_e( 'Scripts', 'wp-smushit' ); ?>
			</span>
			<span class="sui-description">
				<?php esc_html_e( 'By default we will load the required scripts in your footer for max performance benefits. If you are having issues, you can switch this to the header.', 'wp-smushit' ); ?>
			</span>
		</div>
		<div class="sui-box-settings-col-2">
			<div class="sui-form-field">
				<strong><?php esc_attr_e( 'Method', 'wp-smushit' ); ?></strong>
				<div class="sui-description">
					<?php esc_html_e( 'By default we will load the required scripts in your footer for max performance benefits. If you are having issues, you can switch this to the header.', 'wp-smushit' ); ?>
				</div>
				<div class="sui-side-tabs sui-tabs">
					<div data-tabs>
						<div class="active"><?php esc_html_e( 'Footer', 'wp-smushit' ); ?></div>
						<div><?php esc_html_e( 'Header', 'wp-smushit' ); ?></div>
					</div>
					<div class="sui-notice">
						<p><?php esc_html_e( 'Your theme must be using the wp_footer() function.', 'wp-smushit' ); ?></p>
					</div>
				</div>
			</div>

			<div class="sui-form-field">
				<strong><?php esc_attr_e( 'No Script', 'wp-smushit' ); ?></strong>
				<div class="sui-description">
					<?php esc_html_e( 'This feature ensures images will still be shown to users who have Javascript disabled in their browser.', 'wp-smushit' ); ?>
				</div>
				<div class="sui-side-tabs sui-tabs">
					<div data-tabs>
						<div class="active"><?php esc_html_e( 'Enable', 'wp-smushit' ); ?></div>
						<div><?php esc_html_e( 'Disable', 'wp-smushit' ); ?></div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="sui-box-settings-row">
		<div class="sui-box-settings-col-1">
			<span class="sui-settings-label">
				<?php esc_html_e( 'Deactivate', 'wp-smushit' ); ?>
			</span>
			<span class="sui-description">
				<?php
				esc_html_e(
					'No longer wish to use this feature? Turn it off instantly by hitting Deactivate.',
					'wp-smushit'
				);
				?>
			</span>
		</div>
		<div class="sui-box-settings-col-2">
			<button class="sui-button sui-button-ghost" id="smush-cancel-lazyload">
				<i class="sui-icon-power-on-off" aria-hidden="true"></i>
				<?php esc_html_e( 'Deactivate', 'wp-smushit' ); ?>
			</button>
		</div>
	</div>
</form>
