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
if (!class_exists('WpSmProFetch')) {

        /**
         * Fetches files from the service
         */
        class WpSmProFetch {

                /**
                 *
                 * @var string The base upload directory 
                 */
                var $basedir = '';

                /**
                 * Constructor
                 */
                function __construct() {

                        // set up the base path
                        $pathbase = wp_upload_dir();
                        $this->basedir = trailingslashit($pathbase['basedir']);

                        // hook the ajax call for fetching the attachment
                        add_action('wp_ajax_wp_smpro_fetch', WpSmProFetch::fetch());
                }

                /**
                 * 
                 * @return type
                 */
                static function fetch($attachment_id=false) {
                        
                        if(!$attachment_id){
                                $attachment_id = $_GET['attachment_id'];
                        }

                        if (!$attachment_id) {
                                return;
                        }
                        $smush_data = get_post_meta($attachment_id, WP_SMPRO_PREFIX.'smush-data', true);

                        $zip = $this->fetch_file($smush_data['download_url']);

                        $filename = $this->basedir . WP_SMPRO_PREFIX . 'fetched/' . $attachment_id . '.zip';

                        $fp = fopen($filename, 'w');
                        fwrite($fp, $zip);
                        fclose($fp);

                        $result = $this->replace($filename);
                        
                        if($result){
                                unset($smush_data['download_url']);
                                update_post_meta($attachment_id,WP_SMPRO_PREFIX.'is-smushed',1);
                                $current_requests = get_option(WP_SMPRO_PREFIX . "current-requests", array());
                                $remove_request = false;
                                foreach($current_requests as $request_id=>&$data){
                                        if(in_array($attachment_id,$data['sent_ids'])){
                                                $sent_ids= array_diff($data['sent_ids'], array($attachment_id));
                                                if($request_id === get_option(WP_SMPRO_PREFIX . "bulk-sent",0)
                                                        && empty($sent_ids)){
                                                        delete_option(WP_SMPRO_PREFIX . "bulk-sent");
                                                        delete_option(WP_SMPRO_PREFIX . "bulk-received");
                                                        $remove_request = $request_id;
                                                }
                                                exit();
                                        }
                                
                                }
                                if($remove_request){
                                       unset($current_requests[$remove_request]);
                                       update_option(WP_SMPRO_PREFIX . "current-requests",$current_requests);
                                }
                                
                        }

                        echo boolval($result);
                        die();
                }

                /**
                 * 
                 * @param type $zip
                 * @return boolean
                 */
                private function replace($zip) {
                        WP_Filesystem();
                        if (unzip_file($zip, $this->basedir)) {
                                // Now that the zip file has been used, destroy it
                                unlink($zip);
                                return true;
                        }
                        return false;
                }

                /**
                 * 
                 * @param type $url
                 * @return type
                 */
                private function fetch_file($url) {
                        $response = wp_remote_get($url);
                        $zip = wp_remote_retrieve_body($response);
                        return $zip;
                }

        }

}