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
                        
                        $insert = $this->save($attachment_data, $current_requests[$request_id]['sent_ids']);
                        
                        unset($attachment_data);
                        
                        $this->process($insert,$request_id, $current_requests);
		}
                
                
                private function save($data,$sent_ids) {
                        
                        $is_single = (count($sent_ids)>1);
                        
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
                
                private function process($insert, $request_id, $current_requests){
                        if($insert === false){
                                return $insert;
                        }
                        
                        unset($current_requests[$request_id]);
                        
                        update_option(WP_SMPRO_PREFIX . "current-requests", $current_requests);
                        
                        $updated = update_option(WP_SMPRO_PREFIX . "bulk-received",1);
                        
                        return $updated;
                }

	}

}