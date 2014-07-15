<?php
/**
 * Description of WpSmProRequest
 *
 * @author Saurabh Shukla <contact.saurabhshukla@gmail.com>
 */
class WpSmProRequest {

    /**
     * Send a post request to the Smush service and present the response
     * 
     * @param string $img_path
     * @param int $attachment_id
     * @param string $token
     * @return bool|string, Response returned from API
     */
    public function _post($img_path = '', $attachment_id = 0, $token = false) {

        $data = false;
        
        // get the response of the post request
        $response = $this->_post_request($img_path, $attachment_id, $token);

        // if there was no error
        if ($response && !is_wp_error($response)) {
            
            // if there was an http error
            if (empty($response['response']['code']) || $response['response']['code'] != 200) {
                //Give a error
                return __('Error in processing file', WP_SMUSHIT_PRO_DOMAIN);
            }
            
            // otherwise, we received a proper response from the service
            $data = json_decode(
                    wp_remote_retrieve_body(
                            $response
                    )
            );
        }
        
        // presenting service response
        return $data;
    }

    /**
     * Prepare and present the post data for our request
     * 
     * @param int $attachment_id
     * @param string $token
     * @return array The post data for the request
     */
    
    private function _data($attachment_id = 0, $token = '') {
        
        // We can't do anything without these
        if (!$attachment_id || $token === '') {
            return array();
        }
        
        // default fields that we need to set
        $post_fields = array(
            'callback_url' => '',
            'api_key' => '',
            'token' => '',
            'attachment_id' => 0,
            'progressive' => true,
            'gif_to_png' => true,
            'remove_meta' => true
        );

        // set values for the boolean fields
        foreach ($post_fields as $key => &$val) {
            if (!is_bool($key)) {
                continue;
            }

            $newval = get_option('wp_smushit_pro_' . $key, '');
            if (empty($newval) || $newval != 'on') {
                $post_fields[$key] = 0;
            }
        }
        
        // set up remaining fields
        $post_fields['callback_url'] = $this->form_callback_url();
        $post_fields['attachment_id'] = $attachment_id;
        $post_fields['api_key'] = $this->dev_api_key();
        $post_fields['token'] = $token;
        
        // presenting, the post data
        return $post_fields;
    }

    /**
     * Create a file payload, as wp_remote_post doesn't have a file support
     *
     * @param $img_path
     * @param $ID
     * @param $boundary
     * @param $token
     *
     * @return string|boolean
     */
    private function _payload($img_path, $ID, $boundary, $token) {
        
        $payload = '';
        
        // get the post data
        $post_fields = $this->_data($ID, $token);
        
        // if the data isn't set up, we can't do anything 
        if (empty($post_fields)) {
            return $payload;
        }

        // First, add the standard POST fields:
        foreach ($post_fields as $name => $value) {
            $payload .= '--' . $boundary;
            $payload .= "\r\n";
            $payload .= 'Content-Disposition: form-data; name="' . $name .
                    '"' . "\r\n\r\n";
            $payload .= $value;
            $payload .= "\r\n";
        }
        // Upload the file
        if ($img_path) {
            $payload .= '--' . $boundary;
            $payload .= "\r\n";
            $payload .= 'Content-Disposition: form-data; name="' . 'upload' .
                    '"; filename="' . basename($img_path) . '"' . "\r\n";
            //        $payload .= 'Content-Type: image/jpeg' . "\r\n";
            $payload .= "\r\n";
            $payload .= file_get_contents($img_path);
            $payload .= "\r\n";
        }


        $payload .= '--' . $boundary . '--';

        return $payload;
    }
    
    /**
     * Make the post request and present the response
     * 
     * @param string $img_path
     * @param img $attachment_id
     * @param string $token
     * 
     * @return boolean|object either false or response 
     */
    private function _post_request($img_path = '', $attachment_id = 0, $token = false) {
        $req = SMUSHIT_PRO_SERVICE_URL;

        if (WP_SMUSHIT_PRO_DEBUG) {
            echo "DEBUG: Calling API: [" . $req . "]<br />";
        }


        $boundary = wp_generate_password(24);
        $headers = array(
            'content-type' => 'multipart/form-data; boundary=' . $boundary
        );

        $payload = $this->_payload($img_path, $attachment_id, $boundary, $token);
        if (empty($payload)) {
            return false;
        }
        return wp_remote_post($req, array(
            'headers' => $headers,
            'body' => $payload,
            'user-agent' => WP_SMUSHIT_PRO_UA,
            'timeout' => WP_SMUSHIT_PRO_TIMEOUT,
            //Remove this code
            'sslverify' => false
                )
        );
    }

}
