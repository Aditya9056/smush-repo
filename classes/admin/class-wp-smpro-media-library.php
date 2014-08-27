<?php
/**
 * @package SmushItPro
 * @subpackage Admin
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if (!class_exists('WpSmProMediaLibrary')) {

        /**
         * Show settings in Media settings and add column to media library
         *
         */
        class WpSmProMediaLibrary {

                /**
                 * Constructor
                 */
                public function __construct() {

                        // get the DEV api key
                        $wpmudev_apikey = get_site_option('wpmudev_apikey');

                        // add the admin option screens
                        add_action('admin_init', array($this, 'admin_init'));

                        // if there's a key
                        if (!empty($wpmudev_apikey)) {
                                // add extra columns for smushing to media lists
                                add_filter('manage_media_columns', array($this, 'columns'));
                                add_action('manage_media_custom_column', array($this, 'custom_column'), 10, 2);
                        }
                }

                /**
                 * Print column header for Smush.it results in the media library
                 *
                 * @param array $defaults The default columns
                 *
                 * @return array columns with our header added
                 */
                function columns($defaults) {
                        $defaults['smushit'] = 'Smush.it';

                        return $defaults;
                }

                /**
                 * Show our custom smush data for each attachment
                 *
                 * @param string $column_name The name of the column
                 * @param int $id The attachment id
                 *
                 * @return null
                 */
                function custom_column($column_name, $id) {
	                    $status_txt = '';
                        // if it isn't our column, get out
                        if ('smushit' != $column_name) {
                                return;
                        }

                        $is_smushed = get_post_meta($id, "wp-smpro-is-smushed", true);

                        // if the image is smushed
                        if (!empty($is_smushed)) {
                                // the status
	                        $stats = get_post_meta( $id, 'wp-smpro-smush-stats', true );
	                        if ( isset( $stats['compressed_bytes'] ) && $stats['compressed_bytes'] == 0 ) {
		                        $status_txt = __( 'Already Optimized', WP_SMPRO_DOMAIN );
	                        } elseif ( ! empty( $stats['compressed_percent'] ) && ! empty( $stats['compressed_human'] ) ) {
		                        $status_txt = sprintf( __( "Reduced by %01.1f%% (%s)", WP_SMPRO_DOMAIN ), $stats['compressed_percent'], $stats['compressed_human'] );
	                        }

                                // check if we need to show the resmush button
                                $show_button = $this->show_resmush_button($id);

                                // the button text
                                $button_txt = __('Re-smush', WP_SMPRO_DOMAIN);
                        } else {
                                $is_sent = get_post_meta($id, "wp-smpro-is-sent", true);

                                if (!empty($is_sent)) {
                                        // the status
                                        $status_txt = __('File is being processed by API', WP_SMPRO_DOMAIN);

                                        // we need to show the smush button
                                        $show_button = false;

                                        // the button text
                                        $button_txt = '';
                                } else {

                                        // the status
                                        $status_txt = __('Not processed', WP_SMPRO_DOMAIN);

                                        // we need to show the smush button
                                        $show_button = true;

                                        // the button text
                                        $button_txt = __('Smush.it now!', WP_SMPRO_DOMAIN);
                                }
                        }

                        $this->column_html($id, $status_txt, $button_txt, $show_button);
                }

                /**
                 * Print the column html
                 *
                 * @param string $id Media id
                 * @param string $status_txt Status text
                 * @param string $button_txt Button label
                 * @param boolean $show_button Whether to shoe the button
                 *
                 * @return null
                 */
                function column_html($id, $status_txt = "", $button_txt = "", $show_button = true) {
                        // don't proceed if attachment is not image
                        if (!wp_attachment_is_image($id)) {
                                return;
                        }
                        ?>
                        <p class="smush-status">
                        <?php echo $status_txt; ?>
                        </p>
                        <?php
                        // if we aren't showing the button
                        if (!$show_button) {
                                return;
                        }
                        ?>
                        <button class="wp-smpro-smush button">
                                <span>
                        <?php echo $button_txt; ?>
                                </span>
                        </button>
                        <?php
                }

                /**
                 * Whether to show resmush buton
                 *
                 * @param array $smush_meta_full the smush metadata for full image size
                 *
                 * @return boolean true to display the button
                 */
                function show_resmush_button($id) {
                        $button_show = false;
                        $smush_meta_full = get_post_meta($id, 'smush-meta-full', true);
                        $smush_status = get_post_meta($id, 'wp-smpro-is-smushed', true);
                        $receive_status = get_post_meta($id, 'wp-smpro-is-received', true);


                        if ($smush_status === '1') {
                                return $button_show;
                        }

                        if ($smush_status === '0' && $receive_status === '1') {
                                $button_show = true;
                        }

                        if ($smush_status === '0' && $receive_status === '0') {
                                $age = (int) time() - (int) $smush_meta_full['timestamp'];
                                if ($age >= 2 * DAY_IN_SECONDS) {

                                        $button_show = true;
                                }
                        }

                        return $button_show;
                }

                /**
                 * enqueue common script
                 */
                function admin_init() {
                        wp_enqueue_script('common');
                }

        }

}