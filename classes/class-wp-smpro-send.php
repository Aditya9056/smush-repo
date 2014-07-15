<?php

if (!class_exists('WpSmProSend')) {

    class WpSmProSend {

        function __construct() {
            add_action('admin_action_wp_smushit_manual', array(&$this, 'send_single_manual'));
            if (WP_SMPRO_AUTO) {
                add_filter('wp_generate_attachment_metadata', array(&$this, 'auto_on_upload'), 10, 2);
            }
        }

        /**
         * Manually process an image from the Media Library
         */
        function send_single() {
            if (!current_user_can('upload_files')) {
                wp_die(__("You don't have permission to work with uploaded files.", WP_SMUSHIT_PRO_DOMAIN));
            }

            if (!isset($_GET['attachment_ID'])) {
                wp_die(__('No attachment ID was provided.', WP_SMUSHIT_PRO_DOMAIN));
            }

            $attachment_ID = intval($_GET['attachment_ID']);

            $original_meta = wp_get_attachment_metadata($attachment_ID);

            $meta = $this->auto_on_upload($original_meta, $attachment_ID);

            //Update attachemnt meta data
            wp_update_attachment_metadata($attachment_ID, $meta);

            wp_redirect(preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', wp_get_referer()));
            exit();
        }

        function invalidate($img_path = '', $file_url = '') {
            if (empty($img_path)) {
                return __("File path is empty", WP_SMUSHIT_PRO_DOMAIN);
            }

            if (empty($file_url)) {
                return __("File URL is empty", WP_SMUSHIT_PRO_DOMAIN);
            }

            if (!file_exists($img_path)) {
                return __("File does not exists", WP_SMUSHIT_PRO_DOMAIN);
            }

            // check that the file exists
            if (!file_exists($img_path) || !is_file($img_path)) {
                return sprintf(__("ERROR: Could not find <span class='code'>%s</span>", WP_SMUSHIT_PRO_DOMAIN), $img_path);
            }

            // check that the file is writable
            if (!is_writable(dirname($img_path))) {
                return sprintf(__("ERROR: <span class='code'>%s</span> is not writable", WP_SMUSHIT_PRO_DOMAIN), dirname($img_path));
            }

            $file_size = filesize($img_path);
            if ($file_size > WP_SMUSHIT_PRO_MAX_BYTES) {
                return sprintf(__('ERROR: <span style="color:#FF0000;">Skipped (%s) Unable to Smush due to Yahoo 1mb size limits. See <a href="http://developer.yahoo.com/yslow/smushit/faq.html#faq_restrict">FAQ</a></span>', WP_SMUSHIT_PRO_DOMAIN), $this->format_bytes($file_size));
            }

            return false;
        }

        /**
         * Process an image with Smush.it Pro API
         *
         * @param string $img_path , Image Path
         * @param string $img_url , Image URL
         * @param $ID , Attachment ID
         * @param $size , image size, default is full
         *
         * @return string, Message containing compression details
         */
        function send($img_path = '', $img_url = '', $ID = 0, $size = 'full') {

            $invalid = $this->invalidate($img_path, $img_url);

            if ($invalid) {
                return $invalid;
            }
            //Send nonce
            $token = wp_create_nonce("smush_image_$ID" . "_$size");

            //Send file to API
            
            $requester = new WpSmProRequest();
            $data = $requester->_post($img_path, $ID, $token);

            //For testing purpose
//			error_log( json_encode( $data ) );
            if (empty($data)) {
                //Some code error
                return __("Error processing file, no data recieved", WP_SMUSHIT_PRO_DOMAIN);
            }
            //Check for error
            if ($data->status_code === 0) {
                return $data->status_message;
            }

            return $this->process_response($data, $ID);
        }

        function process_response($data, $ID) {
            //Get the returned file id and store it in meta
            $file_id = isset($data->file_id) ? $data->file_id : '';
            $status_code = isset($data->status_code) ? $data->status_code : '';
            $status_msg = isset($data->status_msg) ? $data->status_msg : '';

            //Fetch old smush meta and update with the file id returned by API
            if (empty($smush_meta)) {
                $smush_meta = wp_get_attachment_metadata($ID);
            }

            //If file id update
            if (!empty($file_id)) {
                //Add file id, Status and Message
                $smush_meta['smush_meta'][$size]['file_id'] = $file_id;
                $smush_meta['smush_meta'][$size]['status_code'] = $status_code;
                $smush_meta['smush_meta'][$size]['status_msg'] = $status_msg;
                $smush_meta['smush_meta'][$size]['token'] = $token;
            } else {
                //Return a error
                $smush_meta['smush_meta'][$size]['status_msg'] = "Unable to process the image, please try again later";
            }
        }

        /**
         * WPMUDev API Key
         * @return string
         * @Todo, fetch from dashboard plugin, or allow a input
         */
        function dev_api_key() {
            return '3f2750fe583d6909b2018462fb216a2c5d5d75a9';
        }

        /**
         * Generate a callback URL for API
         * @return mixed|void
         */
        function form_callback_url() {
            $callback_url = admin_url('admin-ajax.php');

            $callback_url = add_query_arg(
                    array(
                'action' => 'process_smushed_image'
                    ), $callback_url
            );

            return apply_filters('smushitpro_callback_url', $callback_url);
        }
        
        /**
         * Check if we need to smush the image or not
         *
         * @param $previous_status
         *
         * @return bool
         */
        function should_resend($previous_status) {
            if (!$previous_status || empty($previous_status)) {
                return true;
            }

            if (stripos($previous_status, 'no savings') !== false || stripos($previous_status, 'reduced') !== false) {
                return false;
            }

            // otherwise an error
            return true;
        }

        /**
         * Read the image paths from an attachment's meta data and process each image
         * with wp_smushit().
         *
         * This method also adds a `wp_smushit` meta key for use in the media library.
         *
         * Called after `wp_generate_attachment_metadata` is completed.
         */
        function auto_on_upload($meta, $ID = null, $force_resmush = true) {
            if ($ID && wp_attachment_is_image($ID) === false) {
                return $meta;
            }

            $attachment_file_path = get_attached_file($ID);
            $attachment_file_url = wp_get_attachment_url($ID);
            if (WP_SMUSHIT_PRO_DEBUG) {
                echo "DEBUG: attachment_file_path_size=[" . $attachment_file_path_size . "]<br />";
                echo "DEBUG: attachment_file_url_size=[" . $attachment_file_url_size . "]<br />";
            }

            //Check if the image was prviously smushed
            $previous_state = !empty($meta['smush_meta']) ? $meta['smush_meta']['full']['status_msg'] : '';

            if ($force_resmush || $this->should_resend($previous_state)) {
                $meta = $this->send($attachment_file_path, $attachment_file_url, $ID, 'full', $meta);
            }

            // no resized versions, so we can exit
            if (!isset($meta['sizes'])) {
                return $meta;
            }

            foreach ($meta['sizes'] as $size_key => $size_data) {
                if (!$force_resmush && $this->should_resend(@$meta['sizes'][$size_key]['wp_smushit']) === false) {
                    continue;
                }

                $this->send_each_size($attachment_file_path, $attachment_file_url, $ID, $size_key, $size_data);
            }

            return $meta;
        }

        function send_each_size($file_path, $file_url, $ID, $key, $meta) {

            // We take the original image. The 'sizes' will all match the same URL and
            // path. So just get the dirname and rpelace the filename.
            $attachment_file_path_size = trailingslashit(dirname($file_path)) . $meta['file'];

            $attachment_file_url_size = trailingslashit(dirname($file_url)) . $meta['file'];

            if (WP_SMUSHIT_PRO_DEBUG) {
                echo "DEBUG: attachment_file_path_size=[" . $attachment_file_path_size . "]<br />";
                echo "DEBUG: attachment_file_url_size=[" . $attachment_file_url_size . "]<br />";
            }


            $this->send($attachment_file_path_size, $attachment_file_url_size, $ID, $key);
        }



        /**
         * Return the filesize in a humanly readable format.
         * Taken from http://www.php.net/manual/en/function.filesize.php#91477
         */
        function format_bytes($bytes, $precision = 2) {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            $bytes = max($bytes, 0);
            $pow = floor(( $bytes ? log($bytes) : 0 ) / log(1024));
            $pow = min($pow, count($units) - 1);
            $bytes /= pow(1024, $pow);

            return round($bytes, $precision) . ' ' . $units[$pow];
        }

        
    }

}

