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

		public function __construct() {

			$wpmudev_apikey = get_site_option( 'wpmudev_apikey' );

			// add the admin option screens
			add_action( 'admin_init', array( $this, 'admin_init' ) );

			if ( ! empty( $wpmudev_apikey ) ) {
				// add extra columns for smushing to media lists
				add_filter( 'manage_media_columns', array( $this, 'columns' ) );
				add_action( 'manage_media_custom_column', array( $this, 'custom_column' ), 10, 2 );
			}
			
		}
		
				/**
		 * Print column header for Smush.it results in the media library
		 * 
		 * @param array $defaults The default columns
		 * @return array columns with our header added
		 */
		function columns( $defaults ) {
			$defaults['smushit'] = 'Smush.it';

			return $defaults;
		}

		/**
		 * Show our custom smush data for each attachment
		 * 
		 * @param string $column_name The name of the column
		 * @param int $id The attachment id
		 */
		function custom_column( $column_name, $id ) {

			// if it isn't our column, get out
			if ( 'smushit' != $column_name ) {
				return;
			}

			$attachment_file_path = get_attached_file($id);

			// otherwise, get the smush meta
			$smush_meta_full = get_post_meta( $id, 'smush_meta_full', true );
			
			// if there's smush details, show it
			if ( ! empty( $smush_meta_full ) ) {
				?>
				<p class="smush-status">
					<?php echo $smush_meta_full['status_msg']; ?>
				</p>
                                <?php
                                $button_show=$this->re_smush_button($smush_meta_full);
                                if($button_show===true){
                                ?>
                                        <button class="wp-smpro-smush button">
                                                <span>
                                                        <?php _e('Re-smush', WP_SMPRO_DOMAIN); ?>
                                                </span>
                                        </button>
                                <?php
                                }
			} else {
				// not smushed yet, check if attachment is image
				if ( wp_attachment_is_image( $id ) ) {
					?>
					<p class="smush-status">
						<?php _e( 'Not processed', WP_SMPRO_DOMAIN ); ?>
					</p>
					<button class="wp-smpro-smush button">
						<span>
							<?php _e('Smush.it now!', WP_SMPRO_DOMAIN); ?>
						</span>
					</button>
				<?php
				}
			}
			
		}
                
                function re_smush_button($smush_meta_full){
                        $button_show = false;
                        
                        $status = (int)$smush_meta_full['status_code'];
                        
                        if($status !=0 && $status!=5 && $status !=1){
                                return $button_show;
                        }
                        
                        if ($status===0 ||  $status===5){
                                $button_show = true;
                        }

                        if($status===1){
                                $age = (int)time() - (int)$smush_meta_full['timestamp'];
                                if($age >= 2*DAY_IN_SECONDS){

                                        $button_show = true;
                                }

                        }
                        return $button_show;
                }
		
		/**
		 * enqueue common script
		 */
		function admin_init() {
			wp_enqueue_script( 'common' );
		}
		
		


	}

}