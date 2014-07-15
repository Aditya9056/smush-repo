<?php

if (class_exists('WpSmProReceive')) {

    /**
     * Receives the call back and fetches the files from the smush service
     *
     * @author Saurabh Shukla <contact.saurabhshukla@gmail.com>
     */
    class WpSmProReceive {

        public $options = array(
            'attachment_id' => '',
            'file_id' => '',
            'file_url' => '',
            'received_token' => '',
            'status_code' => '',
            'request_err_code' => ''
        );

        public function __construct() {
            // process callback from smush service
            add_action('wp_ajax_receive_smushed_image', array(&$this, 'receive'));
            add_action('wp_ajax_receive_process_smushed_image', array(&$this, 'receive'));
        }

        /**
         * Download and Update the Image from Server corresponding to file id and URL
         */
        function receive() {

            $body = @file_get_contents('php://input');
            // get the json into an array
            $response = json_decode($body, true);

            $options = wp_parse_args($response, $this->options);

            if (in_array('', $options)) {
                //Response back to API, missing parameters
                header("HTTP/1.0 406 Missing Parameters");
                exit;
            }

            //If smushing wasn't succesfull
            if ($options['status_code'] != 4) {
                //@todo update meta with suitable error
                header("HTTP/1.0 200");
                exit;
            }
            $this->process($options);
        }

        function process($options = array()) {
            //Get Image sizes detail for media
            $metadata = wp_get_attachment_metadata($options['attachment_id']);

            $smush_meta = !empty($metadata['smush_meta']) ? $metadata['smush_meta'] : '';

            //Empty smush meta, probably some error on our end
            if (empty($smush_meta)) {
                //Response back to API, missing parameters
                header("HTTP/1.0 406 No Smush Meta");
                exit;
            }
//			echo "SMush Meta done";
            //Get the media from thumbnail file id
            foreach ($smush_meta as $image_size => $image_details) {

                //Skip the loop if file id is not the same
                if (empty($image_details['file_id']) || $image_details['file_id'] != $file_id) {
                    continue;
                }

                $size = $image_size;
                $token = $image_details['token'];

                //Check for Nonce, corresponding to media id
                if ($token != $options['received_token']) {
                    error_log("Nonce Verification failed for " . $options['attachment_id']);

                    //Response back to API, missing parameters
                    header("HTTP/1.0 406 invalid token");
                    exit;
                }

                $attachment_file_path = get_attached_file($options['attachment_id']);

                //Modify path if callback is for thumbnail
                $attachment_file_path_size = trailingslashit(dirname($attachment_file_path)) . $metadata['sizes'][$image_size]['file'];

                //We are done processing, end loop
                break;
            }
            $fetched = $this->fetch($options);

            $results_msg = $this->create_stat_string(
                    $response['compression'], $response['before_smush'], $response['after_smush']
            );


            $smush_meta[$size]['status_code'] = $options['status_code'];
            $smush_meta[$size]['status_msg'] = $results_msg;

            $metadata['smush_meta'] = $smush_meta;

            wp_update_attachment_metadata($attachment_id, $metadata);
            error_log(json_encode(wp_get_attachment_metadata($attachment_id)));
            //Response back to API, missing parameters
            header("HTTP/1.0 200 file updated");
            exit;
        }

        public function fetch($options) {
            //Loop
            //@Todo: Add option for user, Strict ssl use wp_safe_remote_get or download_url
            //Copied from download_url, as it does not provice to turn off strict ssl
            $temp_file = wp_tempnam($options['file_url']);

            if (!$temp_file) {
                //For Debugging on node
                echo "<pre>";
                print_r(__('Could not create Temporary file.'));
                echo "</pre>";

                echo "Unsafe URL";

                //Response back to API, missing parameters
                header("HTTP/1.0 406 No temp file");
                exit;
            }

            $response = wp_remote_get(
                    $options['file_url'], array(
                'timeout' => 300,
                'stream' => true,
                'filename' => $temp_file,
                'sslverify' => false
                    )
            );

            if (is_wp_error($response)) {
                unlink($temp_file);

                //For Debugging on node
                echo "<pre>";
                print_r($response);
                echo "</pre>";

                echo "Unsafe URL";

                //Response back to API, missing parameters
                header("HTTP/1.0 406 Unsafe URL");
                exit;
            }

            if (200 != wp_remote_retrieve_response_code($response)) {
                echo trim(wp_remote_retrieve_response_message($response));

                unlink($temp_file);
                header("HTTP/1.0 406  " . trim(wp_remote_retrieve_response_message($response)));
            }

            $content_md5 = wp_remote_retrieve_header($response, 'content-md5');

            if ($content_md5) {
                $md5_check = verify_file_md5($temp_file, $content_md5);
                if (is_wp_error($md5_check)) {
                    unlink($temp_file);
                    echo "File check";
                    //Response back to API, missing parameters
                    header("HTTP/1.0 406 URL authentication error");
                    exit;
                }
            }

            if (is_wp_error($temp_file)) {
                @unlink($temp_file);

                echo "File path error";
                error_log(sprintf(__("Error downloading file (%s)", WP_SMUSHIT_PRO_DOMAIN), $temp_file->get_error_message()));

                header("HTTP/1.0 406 File not downloaded");
                exit;
            }

            if (!file_exists($temp_file)) {
                error_log(sprintf(__("Unable to locate downloaded file (%s)", WP_SMUSHIT_PRO_DOMAIN), $temp_file));

                echo "Local server error";
                header("HTTP/1.0 406 Downloaded file not found");
                exit;
            }

            //Unlink the old file and replace it with new one
            @unlink($attachment_file_path_size);

            $success = @rename($temp_file, $attachment_file_path_size);

            if (!$success) {
                copy($temp_file, $attachment_file_path_size);
                unlink($temp_file);
            }
        }

        public function create_stat_string($compression, $before_smush, $after_smush) {
            $savings_str = '';
            $compressed = !empty($compression) ? $compression : '';

            if (!empty($before_smush) && !empty($after_smush)) {
                $savings_str = $before_smush - $after_smush . 'Kb';
            }
            if ($compressed == 0) {
                $results_msg = __('Optimised', WP_SMUSHIT_PRO_DOMAIN);
            } else {
                $results_msg = sprintf(__("Reduced by %01.1f%% (%s)", WP_SMUSHIT_PRO_DOMAIN), $compressed, $savings_str);
            }

            return $results_msg;
        }

    }

}