<?php

/**
 * @package SmushItPro
 * @subpackage Receive
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmProReceive' ) ) {

	/**
	 * Receives call backs from service
	 */
	class WpSmProReceive {

		/**
		 * Constructor, hooks callback urls
		 */
		public function __construct() {

			// process callback from smush service
			add_action( 'wp_ajax_receive_smushed_image', array( $this, 'receive' ) );
			add_action( 'wp_ajax_nopriv_receive_smushed_image', array( $this, 'receive' ) );
		}

		/**
		 * Receive the callback and send data for further processing
		 */
		function receive() {

			// get the contents of the callback
			$body = urldecode( file_get_contents( 'php://input' ) );

			// filter with default data
			$data = array();

			parse_str( $body, $data );
                        
                        $request_id = $data['request_id'];
                        
                        $current_requests = get_option(WP_SMPRO_PREFIX . "current-requests", array());
                        
                        if($data['token']!= $current_requests[$request_id]['token']){
                                unset($data);
                                return;
                        }
                        
                        $attachment_data = $data['data'];
                        $is_single = (count($current_requests[$request_id]['sent_ids'])>1);
                        
                        $insert = $this->save($attachment_data, $current_requests[$request_id]['sent_ids'], $is_single);
                        
                        unset($attachment_data);
                        unset($data);
                        
                        $updated = $this->update($insert,$request_id, $current_requests);
                        
                        $this->notify($updated);
                        
                        
		}
                
                
                private function save($data,$sent_ids, $is_single) {
                        
                        global $wpdb;
                        
                        $sql = "INSERT INTO $wpdb->post_meta (post_id,meta_key,meta_value) VALUES ";
                        foreach ($data as $key=>&$val){
                                $attachment_id = $val['attachment_id'];
                                unset($val['attachment_id']);
                                if(in_array($attachment_id, $sent_ids)){
                                        $values[] = "(".$attachment_id.", '".WP_SMPRO_PREFIX."smush-data', ".maybe_serialize($val).")";
                                }
                        }
                        
                        $sql .= implode(',', $values);
                        
                        $insert = $wpdb->query($sql);
                        
                        if($is_single){
                                WpSmProFetch::fetch($attachment_id);
                        }
                        return $insert;
                        
                }
                
                private function update($insert, $request_id, $current_requests){
                        if($insert === false){
                                return $insert;
                        }
                        
                        unset($current_requests[$request_id]);
                        
                        update_option(WP_SMPRO_PREFIX . "current-requests", $current_requests);
                        
                        $updated = update_option(WP_SMPRO_PREFIX . "bulk-received",1);
                        
                        return $updated;
                }
                
                private function notify($processed){
                        
                        if($processed===false){
                                return;
                        }
                        
                        $to = get_option('admin_email');
                        
                        $subject = sprintf(__("%s: Smush.It Pro bulk smushing completed", WP_SMPRO_DOMAIN),get_option('blogname'));
                        
                        $message = array();
                        
                        $message[] = sprintf(__('A recent bulk smushing request on your site %s has been completed!', WP_SMPRO_DOMAIN),get_option('siteurl'));
                        $message[] = sprintf(__('Visit your dashboard (%s) to download the smushed images to your site.', WP_SMPRO_DOMAIN),admin_url( 'upload.php?page=wp-smpro-admin' ));
                        
                        $body = implode("\r\n",$message);
                        return wp_mail($to, $subject, $body);
                }

	}

}