<?php

/**
 *
 * @package SmushItPro
 * @subpackage Sender
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 *
 */
if (!class_exists('WpSmProSend')) {

        /**
         * Sends Smush requests to service and processes response
         */
        class WpSmProSend {

                /**
                 * Constructor.
                 *
                 * Hooks actions for smushing through admin or ajax
                 */
                function __construct() {

                        // for ajax based smushing through bulk UI
                        add_action('wp_ajax_wp_smpro_queue', array($this, 'ajax_queue'));

                        if (WP_SMPRO_AUTO) {
                                // add automatic smushing on upload
                                add_filter('wp_generate_attachment_metadata', array($this, 'auto_smush'), 10, 2);
                        }
                }

                function auto_smush($metadata, $attachment_id) {

                        global $wp_sm_pro;

                        //Check API Status
                        if ($wp_sm_pro->admin->api_connected) {

                                //Send metadata and attachment id
                                $sent = $this->send_request($attachment_id);
                        }

                        return $metadata;
                }

                /**
                 * Processes the ajax smush request
                 *
                 * @todo Conditionally get next id, only for requests that need it
                 */
                function ajax_queue() {
                        global $wp_sm_pro;

	                    if( empty( $_REQUEST['attachment_id'] ) ) {
		                    $response['status_code'] = 404;
		                    $response['status_message'] = __("Attachment id missing", WP_SMPRO_DOMAIN);
		                    // print out the response
		                    echo json_encode($response);

		                    // wp_ajax wants us to...
		                    die();
	                    }

                        // check user permissions
                        if (!current_user_can('upload_files')) {
                                wp_die(__("You don't have permission to work with uploaded files.", WP_SMPRO_DOMAIN));
                        }
                        
                        $response = array();

                        //Check API Status
                        if (!$wp_sm_pro->admin->api_connected) {
                                $response['status_code'] = 404;
                                $response['status_message'] = __("API not accessible", WP_SMPRO_DOMAIN);
                                // print out the response
                                echo json_encode($response);

                                // wp_ajax wants us to...
                                die();
                        }
	                    $attachment_id = $_REQUEST['attachment_id'];

                        $sent = $this->send_request( $attachment_id );
                        
                        if(!$sent || is_wp_error($sent)){
                                echo 0;
                        }
                        
                        echo 1;

                        // wp_ajax wants us to...
                        die();
                }
                
                /**
                 * Send a request for smushing
                 * 
                 * @param bool|array|int $attachment_id attachment id or an array of attachment ids or false(for bulk smushing)
                 * @return bool whether request was successful
                 */
                function send_request( $attachment_id = false ){
                        /*
                         * {
                         *      'api_key': '',
                         *      'url_prefix': 'http://somesite.com/wp-content/uploads',
                         *      'token': 2wde456gd,
                         *      'progressive': 1,
                         * 	'gif_to_png': 1,
                         * 	'remove_meta': 1,
                         *      'data': {
                         *                      {
                         *                              'attachment_id': 1254,
                         *                              'path_prefix': '14/08/',
                         *                              'files': {
                         *                                              'large' : 'file-720X480.jpg',
                         *                                              'medium' : 'file-270X230.jpg',
                         *                                              'thumbnail': 'file-150X150.jpg' 
                         *                                        }
                         *                      },
                         *                      {
                         *                              'attachment_id': 1255,
                         *                              'path_prefix': '14/08/',
                         *                              'files': {
                         *                                              'full' : 'file2.jpg', // no large image
                         *                                              'medium' : 'file2-270X230.jpg',
                         *                                              'thumbnail': 'file2-150X150.jpg' 
                         *                                        }
                         *                      },
                         *                      ... and so on
                         *      }
                         * }
                         */
                        // formulate the request data as shown in the comment above
                        $request_data = $this->form_request_data($attachment_id);
	                    var_dump( $request_data );
                        
                        // get the token out
                        $token = $request_data->token;
                        
                        // post the request and get the response
                        $response = $this->_post($request_data);
                        
                        // if thre was an error, return it
                        if(is_wp_error($response)){
                                // destroy the large request_data from memory
                                unset($request_data);
                                return $response;
                        }
                        
                        // otherwise get into array
                        $response = json_decode($response);
                        
                        // destroy the large request_data from memory
                        unset($request_data);
                        
                        // get the unique request id issued by smush service
                        $request_id = $response['request_id'];
                        
                        // save it for reference
                        $updated = update_site_option(WP_SMPRO_PREFIX."request-token-$request_id", $token);
                        
                        // destroy all vars that we don't need
                        unset($request_id,$token,$response);
                        
                        // return the success status of update option
                        // otherwise all this was a waste since we won't know how to process further
                        return boolval($updated);
                        
                }
                
                /**
                 * Formulate the request data
                 * 
                 * @param int|array|bool $attachment_id The attachment id, or array of attachment ids or false
                 * @return object The request data
                 */
                private function form_request_data($attachment_id){
                        
                        // instantiate
                        $request_data = new stdClass();
                        
                        // get the upload url prefix (http://domain.com/wp-content/uploads or similar)
                        $path_base =wp_upload_dir();
                        $request_data->url_prefix = $path_base['baseurl'];
                        
                        unset($path_base);
                        
                        // add the API key
                        $request_data->api_key = get_site_option('wpmudev_apikey');
                        
                        // add a token
                        $request_data->token = wp_create_nonce(WP_SMPRO_PREFIX."request");

                        // add the smushing options
                        $request_data = $this->add_options($request_data);

                        // add data for all the attachments
                        $request_data = $this->add_attachment_data( $request_data,$attachment_id);

                        // return the formed request data
                        return $request_data;
                }


	        /**
	         * Add smush options to the request data
	         * @param object $request_data The existing request data
	         * @return object request data with options
	         */
	        private function add_options( $request ) {
		        global $wp_sm_pro;
		        $options = $wp_sm_pro->smush_settings;

		        // set values for the boolean fields
		        foreach ( $options as $key => $val ) {
			        if( $key == 'auto' ) {
				        continue;
			        }
			        $value = get_option( 'wp_smpro' . $key, '' );

			        //Check if option is not set, use the default value
			        $value = !empty( $value ) ? $value : $val;

			        $request->{$key} = !empty( $value ) ? $value : $val;
		        }

		        unset( $options );

		        return $request;
	        }
                
                /**
                 * Add data for all attachments to the request data
                 * 
                 * @param object $request_data The request data
                 * @param int|bool|array $attachment_id The attachment id, or array of attachment ids or false
                 * @return object the request data with attachment data
                 */
                private function add_attachment_data($request_data, $attachment_id){
                        
                        // get all the attachment data from the db
                        $attachments = $this->get_attachments($attachment_id);

	                    //If there are no atachments, return
	                    if ( empty( $attachments ) ) {
		                    return $request_data;
	                    }
                        // get the array of ids already sent
                        $sent_ids = get_site_option(WP_SMPRO_PREFIX.'sent-ids',array());
                        
                        // loop
                        foreach( $attachments as &$attachment){
                                // get the attachment data in the format we need
                                $attachment = $this->format_attachment_data($attachment);
                                // add this id to the list of sent ids
                                $sent_ids[] = $attachment->attachment_id;
                        }
                        
                        // update the sent ids
//                        update_site_option(WP_SMPRO_PREFIX.'sent-ids', $sent_ids);
                        unset($sent_ids);
                        
                        // add the formatted attachment data to the request data
                        $request_data->data = $attachments;
                        unset($attachments);
                        
                        return $request_data;
                }
                
                /**
                 * Get the attachments from the database for smushing
                 * 
                 * @global object $wpdb
                 * @param int|bool|array $attachment_id
                 * @return object query results
                 */
                function get_attachments($attachment_id = false){
                        
                        global $wpdb;
                        var_dump( $attachment_id );
                        // figure if we need to get data for specific ids
                        echo $where_id_clause = $this->where_id_clause($attachment_id);
                        
                        // so that we don't include the ids already sent
                        echo $existing_clause = $this->existing_clause();
                        
                        // get the attachment id, attachment metadata and full size's path
                        $sql = "SELECT p.ID as attachment_id, md.meta_value as metadata, mp.meta_value as metapath"
                                . " FROM $wpdb->posts as p"
                                // for attachment metadata
                                . " LEFT JOIN $wpdb->postmeta as md"
                                . " ON (p.ID= md.post_id AND md.meta_key='_wp_attachment_metadata')"
                                // for full size's path
                                . " LEFT JOIN $wpdb->postmeta as mp"
                                . " ON (p.ID= mp.post_id AND mp.meta_key='_wp_attached_file')"
                                // to check if attachment isn't already smushed
                                . " LEFT JOIN $wpdb->postmeta as m"
                                . " ON (p.ID= m.post_id AND m.meta_key='".WP_SMPRO_PREFIX."is-smushed')"
                                . " WHERE"
                                . " p.post_type='attachment'"
                                . " AND p.post_mime_type LIKE '%image/%'"
                                . " AND (m.meta_value='0' OR m.post_id IS NULL)"
                                . $where_id_clause
                                . $existing_clause
                                . " ORDER BY p.post_date ASC"
                                // get only 1000 at a time
                                . " LIMIT 1000";
                        $results = $wpdb->get_results( $sql );
                        unset($sql,$where_id_clause);
                        return $results;
                        
                }
                
                /**
                 * Creates an IN clause if we need to smush a single or specif ids
                 * 
                 * @param int|bool|array $id attachment id
                 * @return string|null the IN clause
                 */
                private function where_id_clause($id=false){
                        if(empty($id) || $id ===false){
                                return;
                        }
                        
                        $clause = ' AND p.ID';
                        
                        if(is_numeric($id)){
                               $clause .= '='.(int)$id;
                               return $clause;
                        }
                        
                        if(is_array($id)){
                                $id_list = implode(',',$id);
                                $clause .= " IN ($id_list)";
                                unset($id_list);
                                return $clause;
                        }
                               
                }
                
                /**
                 * Creates a NOT IN clause for ids that have already been sent
                 * 
                 * @return string|null the NOT IN clause
                 */
		        private function existing_clause() {
			        $sent_ids = get_site_option( WP_SMPRO_PREFIX . 'sent-ids', array() );

			        if ( empty( $sent_ids ) ) {
				        return;
			        }

			        $id_list = implode( ',', $sent_ids );
			        unset( $sent_ids );

			        $clause = " AND p.ID NOT IN ($id_list)";
			        unset( $id_list );

			        return $clause;
		        }
                
                /**
                 * Formats the database result for each attachment
                 * 
                 * @param object $row the databse row for each attachment
                 * @return \stdClass the formatted row
                 * @todo GIF checking
                 */
                private function format_attachment_data($row){
                        $request_item = new stdClass();
                        
                        $request_item->attachment_id = $row->attachment_id;
                        
                        $metadata = maybe_unserialize($row->metadata);
                        
                        $full_size_array = pathinfo($row->metapath);
                        
                        $request_item->path_prefix = $full_size_array['dirname'];
                        
                        $full_image = $full_size_array['basename'];
                        
                        $filenames = array();
                        // check large
                        foreach($metadata['sizes'] as $size_key => $size_data){
                                $filenames[$size_key]=$size_data['file'];
                        }
                        
                        if(!isset($filenames['large'])){
                               $filenames['full'] = $full_image; 
                        }
                        
                        $request_item->files = $filenames;
                        
                        unset($filenames,$metadata,$full_size_array,$full_image);
                        
                        return $request_item;
        
                }
                
                
                
                /**
                 * Post the request data and return the response body
                 * 
                 * @param object $request_data
                 * @return \WP_Error
                 */
                private function _post($request_data){
                        
                        // send a post request and get response
                        $response = $this->_post_request($request_data);
                        
                        // validate response
                        if (!$response || is_wp_error($response)) {
                                unset($response, $request_data);
                                return new WP_Error('failed', __('Request failed', WP_SMPRO_DOMAIN));
                        }
                        

                        // if there was an http error
                        if (empty($response['response']['code']) || $response['response']['code'] != 200) {
                                unset($response,$request_data);
                                //Give a error
                                return new WP_Error('failed', __('Service unavailable', WP_SMPRO_DOMAIN));
                        }

                        // otherwise, we received a proper response from the service
                        $data = json_decode(
                                wp_remote_retrieve_body(
                                        $response
                                )
                        );
			
                        
                        unset($response,$request_data);

			// presenting service response
			return $data;
                }
                
                /**
                 * Make a post request to smush api and return the response
                 * 
                 * @param type $request_data
                 * @return boolean|array false or the response
                 */
		        private function _post_request( $request_data ) {

			        if ( empty( $request_data ) ) {
				        return false;
			        }

			        if ( defined( WP_SMPRO_DEBUG ) && WP_SMPRO_DEBUG ) {
				        echo "DEBUG: Calling API: [" . $request_data . "]<br />";
			        }

			        $req_args = array(
				        'body'       => json_encode( $request_data ),
				        'user-agent' => WP_SMPRO_USER_AGENT,
				        'timeout'    => WP_SMUSHIT_PRO_TIMEOUT,
				        //Remove this code
				        'sslverify'  => false
			        );

			        // make the post request and return the response
			        $response = wp_remote_post( WP_SMPRO_SERVICE_URL, $req_args );

			        return $response;
		        }

                /**
                 * Gets the next id in queue
                 *
                 * @global object $wpdb WordPress database object
                 *
                 * @param int $id The current id
                 *
                 * @return int The next id
                 */
                function get_next_id($id) {
                        global $wpdb;

                        // the query folks
                        /*
                         * We need attachments that either
                         * 	have the smush status meta as false, or
                         * 	do not have the meta set, at all
                         * So we do two joins
                         */
                        $query = $wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p "
                                . "INNER JOIN {$wpdb->postmeta} pm ON "
                                . "(p.ID = pm.post_id) "
                                . "LEFT JOIN {$wpdb->postmeta} pmm ON "
                                . "(p.ID = pmm.post_id AND pmm.meta_key = 'wp-smpro-is-sent') "
                                . "WHERE p.post_type = 'attachment' "
                                . "AND ("
                                . "p.post_mime_type = 'image/jpeg' "
                                . "OR p.post_mime_type = 'image/png' "
                                . "OR p.post_mime_type = 'image/gif'"
                                . ") "
                                . "AND p.ID > %d "
                                . "AND ( "
                                . "("
                                . "pm.meta_key = 'wp-smpro-is-sent' "
                                . "AND CAST(pm.meta_value AS CHAR) = '0'"
                                . ") "
                                . "OR  pmm.post_id IS NULL"
                                . ") "
                                . "GROUP BY p.ID "
                                . "ORDER BY p.post_date ASC LIMIT 0, 1", $id);

                        // next id
                        $next_id = $wpdb->get_var($query);

                        // return it
                        return $next_id;
                }

                /**
                 * Gets the attachment meta and sends for smushing
                 *
                 * @param int $attachment_id
                 * @param array $metadata , default is empty
                 *
                 * @return bool|object True on success, WP_Error object on failure
                 */
                function add_meta_then_queue($attachment_id, $metadata = '') {

                        // check if it's an image
                        if ($attachment_id && wp_attachment_is_image($attachment_id) === false) {
                                return new WP_Error('invalid', __('Not a valid attachment', WP_SMPRO_DOMAIN));
                        }

                        // attachment path and url
                        $attachment_file_path = get_attached_file($attachment_id);
                        $attachment_file_url = wp_get_attachment_url($attachment_id);

                        // some debug info
                        if (defined(WP_SMPRO_DEBUG) && WP_SMPRO_DEBUG) {
                                echo "DEBUG: attachment_file_path=[" . $attachment_file_path . "]<br />";
                                echo "DEBUG: attachment_file_url=[" . $attachment_file_url . "]<br />";
                        }
                        // send for further processing
                        $full_state = $this->prepare_and_send($attachment_id, $attachment_file_path, $attachment_file_url);
                        if (is_wp_error($full_state)) {
                                $smushed = 0;
                        } else {
                                $smushed = 1;
                        }
                        global $wp_sm_pro;

                        $wp_sm_pro->set_status($attachment_id, 'sent', 'full', $smushed);

                        //now see if other sizes should be smushed
                        $this->check_send_sizes($attachment_id, $attachment_file_path, $attachment_file_url, '', $metadata);

                        $status = $wp_sm_pro->check_status($attachment_id, 'sent');

                        return $status;
                }

                /**
                 * Prepares the attachment and all its sizes and sends for smushing
                 *
                 * @param array $meta The attachment metadata, with sizes, etc
                 * @param int $ID The attachment id
                 * @param boolean $force_resmush Force resmushing, inspite of previous status
                 *
                 * @return bool|object True on success, WP_Error object on failure
                 */
                function prepare_and_send($ID = null, $attachment_file_path = '', $attachment_file_url = '', $force_resmush = true) {


                        // don't send if it's a static gif and user doesn't want png
                        if (!$this->send_if_gif($ID, $attachment_file_path)) {
                                return new WP_Error('invalid', __('GIFs are not allowed by your settings', WP_SMPRO_DOMAIN));
                        }
                        // smush meta
                        $smush_meta = get_post_meta($ID, 'smush_meta_full', true);

                        //Check if the image was previously smushed
                        $previous_state = !empty($smush_meta) ? $smush_meta['status_msg'] : '';

                        // if we do send it for smushing
                        if ($force_resmush || $this->should_resend($previous_state)) {
                                $full_state = $this->send($attachment_file_path, $attachment_file_url, $ID, 'full');
                        } else {
                                $full_state = new WP_Error('already_sent', __('The attachment has been smushed already'));
                        }

                        return $full_state;
                }

                /**
                 * Send smush request for all Image sizes
                 *
                 * @param $attachment_id
                 * @param string $attachment_file_path
                 * @param string $attachment_file_url
                 * @param bool $force_resmush
                 * @param string $metadata , default is null
                 */
                function check_send_sizes($attachment_id, $attachment_file_path = '', $attachment_file_url = '', $force_resmush = true, $metadata = '') {

                        // get the attachment meta data
                        $meta = !empty($metadata) ? $metadata : wp_get_attachment_metadata($attachment_id);

                        // no resized versions, so we can exit
                        if (!isset($meta['sizes'])) {
                                return;
                        }
                        
                       
                        // otherwise, send each size
                        global $wp_sm_pro;
                         
                        $smushed = 0;
                        $size_sent = false;
                        
                        foreach ($meta['sizes'] as $size_key => $size_data) {
                                if (!$force_resmush && $this->should_resend(@$meta['sizes'][$size_key]['wp_smushit']) === false) {
                                        continue;
                                }
                                // we aren't concerned with what happens to the rest of the sizes, are we?
                                $size_sent = $this->send_each_size($attachment_file_path, $attachment_file_url, $attachment_id, $size_key, $size_data['file']);
                                if (is_wp_error($size_sent)) {
                                        $smushed = 0;
                                } else {
                                        $smushed = 1;
                                }
                                $wp_sm_pro->set_status($attachment_id,'sent',$size_key, $smushed);
                                $smushed = 0;
                                $size_sent = false;
                        }


                        unset($size_sent);
                        unset($smushed);

                        return;
                }

                /**
                 * Process each image size
                 *
                 * @param string $file_path The image file path
                 * @param string $file_url The image url
                 * @param int $ID The attachment id
                 * @param string $size The size name, i.e., full, medium, thumbnail, etc
                 * @param string $file The filename
                 *
                 * @return bool|object True on success, WP_Error object on failure
                 */
                function send_each_size($file_path, $file_url, $ID, $size, $file) {

                        error_log($size);
                        // We take the original image. The 'sizes' will all match the same URL and
                        // path. So just get the dirname and rpelace the filename.
                        $attachment_file_path_size = trailingslashit(dirname($file_path)) . $file;

                        $attachment_file_url_size = trailingslashit(dirname($file_url)) . $file;

                        if (defined(WP_SMPRO_DEBUG) && WP_SMPRO_DEBUG) {
                                echo "DEBUG: attachment_file_path_size=[" . $attachment_file_path_size . "]<br />";
                                echo "DEBUG: attachment_file_url_size=[" . $attachment_file_url_size . "]<br />";
                        }

                        // send it
                        return $this->send($attachment_file_path_size, $attachment_file_url_size, $ID, $size);
                }

                /**
                 * Process an image with Smush.it Pro API
                 *
                 * @param string $img_path Image Path
                 * @param string $img_url Image URL
                 * @param int $ID Attachment id
                 * @param string $size The size name, i.e., full, medium, thumbnail, etc
                 *
                 * @return bool|object True on success, WP_Error object on failure
                 */
                function send($img_path = '', $img_url = '', $ID = 0, $size = '') {
                        global $wp_sm_pro;

                        // try and make this data invalid
                        $invalid = $this->invalidate($img_path, $img_url);

                        // it's invalid
                        if (is_wp_error($invalid)) {
                                return $invalid;
                        }
                        
                        if(intval( get_transient( "wp-smpro-smushed-{$ID}-{$size}" ) )){
                                return true;
                        }
                        // data is fine
                        // create nonce
                        $token = wp_create_nonce("smush_image_{$ID}_{$size}");

                        //instantiate the request class
                        $requester = new WpSmProRequest();
                        // send the post request and get the response data
                        $data = $requester->_post($img_path, $ID, $token, $size);

                        // response is empty
                        if (empty($data) || is_wp_error($data)) {
                                //File was never processed, return the original meta
                                return new WP_Error('response_failed', __('Response Failed', WP_SMPRO_DOMAIN));
                        }

                        $size_smush_meta = get_post_meta($ID, "smush_meta_$size", true);

                        //Check for error
                        if ($data->status_code === 0) {

                                $size_smush_meta['status_msg'] = $data->status_msg;
                                // update smush details
                                update_post_meta($ID, "smush_meta_$size", $size_smush_meta);

                                return new WP_Error('smush_failed', $data->status_msg);
                        }
                        
                        if ($data->status_code === 7) {
                                error_log('throttled');
                                update_option('wp_smpro_is_throttled', 1);
                                return new WP_Error('smush_throttled', $data->status_msg);
                                
                        }
                        
                        update_option('wp_smpro_is_throttled', 0);

                        // all's fine, send response for processing
                        return $this->process_response($data);
                }

                /**
                 * Process the response from API
                 *
                 * @param object $data The data returned from service
                 *
                 * @return bool|object True on success, WP_Error object on failure
                 */
                function process_response($data) {
                        global $wp_sm_pro;

                        $status = false;

                        //Get the returned file id and store it in meta
                        $file_id = isset($data->file_id) ? $data->file_id : '';
                        $status_code = isset($data->status_code) ? $data->status_code : '';
                        $request_err_code = isset($data->request_err_code) ? $data->request_err_code : '';
                        $attachment_id = isset($data->attachment_id) ? $data->attachment_id : '';
                        $image_size = isset($data->image_size) ? $data->image_size : '';

                        //Fetch old smush meta and update with the file id returned by API
                        $image_size_smush_meta = get_post_meta($attachment_id, "smush_meta_$image_size", true);
                        $image_size_smush_meta['timestamp'] = time();

                        $image_size_smush_meta = !empty($image_size_smush_meta) ? $image_size_smush_meta : array();

                        //If file id update
                        if (!empty($file_id)) {
                                //Add file id, Status and Message
                                $image_size_smush_meta['file_id'] = $file_id;
                                $image_size_smush_meta['status_code'] = $status_code;
                                $image_size_smush_meta['status_msg'] = $this->get_status_msg($status_code, $request_err_code);
                                $image_size_smush_meta['token'] = $data->token;

                                $status = true;
                        } else {
                                $image_size_smush_meta['status_msg'] = __('Unable to process the image, please try again later', WP_SMPRO_DOMAIN);
                                $status = new WP_Error('smush_failed', $image_size_smush_meta[$image_size]['status_msg']);
                        }



                        // update smush info
                        update_post_meta($attachment_id, "smush_meta_$image_size", $image_size_smush_meta);

                        return $status;
                }

                /**
                 * Form appropriate status message
                 *
                 * @global object $wp_sm_pro The plugin's global object
                 *
                 * @param int $status_code The status code returned from service
                 * @param int $request_err_code Additional request error code from service
                 *
                 * @return string The status message
                 */
                function get_status_msg($status_code, $request_err_code) {

                        global $wp_sm_pro;

                        $status_code = intval($status_code);

                        // get the status message for the status code
                        $msg = $wp_sm_pro->status_msgs['smush_status'][$status_code];

                        // if there was a request error, add the appropriate request error message
                        if ($status_code === 0 && $request_err_code !== '') {
                                $msg .= ': ' . $wp_sm_pro->status_msgs['request_err_msg'][intval($request_err_code)];
                        }

                        return $msg;
                }

                /**
                 * Try and see if data is invalid
                 *
                 * @param string $img_path Image's file path
                 * @param string $file_url Image's file url
                 *
                 * @return bool|object True if valid, WP_Error object if invalid
                 */
                function invalidate($img_path = '', $file_url = '') {
                        if (empty($img_path)) {
                                return new WP_Error('invalid', __("File path is empty", WP_SMPRO_DOMAIN));
                        }

                        if (empty($file_url)) {
                                return new WP_Error('invalid', __("File URL is empty", WP_SMPRO_DOMAIN));
                        }

                        if (!file_exists($img_path)) {
                                return new WP_Error('invalid', __("File does not exists", WP_SMPRO_DOMAIN));
                        }

                        // check that the file exists
                        if (!file_exists($img_path) || !is_file($img_path)) {
                                return new WP_Error('invalid', sprintf(__("ERROR: Could not find <span class='code'>%s</span>", WP_SMPRO_DOMAIN), $img_path));
                        }

                        // check that the file is writable
                        if (!is_writable(dirname($img_path))) {
                                return new WP_Error('invalid', sprintf(__("ERROR: <span class='code'>%s</span> is not writable", WP_SMPRO_DOMAIN), dirname($img_path)));
                        }

                        $file_size = filesize($img_path);
                        if ($file_size > WP_SMPRO_MAX_BYTES) {
                                return new WP_Error('invalid', sprintf(__('ERROR: <span style="color:#FF0000;">Skipped (%s) Unable to Smush due to 5mb size limits.</span>', WP_SMPRO_DOMAIN), $file_size));
                        }

                        return true;
                }

                /**
                 * WPMUDev API Key
                 *
                 * @return string
                 *
                 */
                function dev_api_key() {
                        return get_site_option('wpmudev_apikey');
                }

                /**
                 * Generate a callback URL for API
                 *
                 * @return mixed|void
                 */
                function form_callback_url() {
                        $callback_url = admin_url('admin-ajax.php');

                        $callback_url = add_query_arg(
                                array(
                            'action' => 'receive_smushed_image'
                                ), $callback_url
                        );

                        return apply_filters('smushitpro_callback_url', $callback_url);
                }

                /**
                 * Check if we need to smush the image or not
                 *
                 * @param string $previous_status Previous smush status
                 *
                 * @return boolean True, if we need to smush and false, if not
                 */
                function should_resend($previous_status) {
                        if (!$previous_status || empty($previous_status)) {
                                return true;
                        }

                        if (
                                stripos($previous_status, 'no savings') !== false || stripos($previous_status, 'reduced') !== false
                        ) {
                                return false;
                        }

                        // otherwise an error
                        return true;
                }

                /**
                 * Checks if a static gif should be sent for smushing
                 *
                 * @param int $id attachment id
                 * @param string $path the attachment file path
                 *
                 * @return boolean true, if fine to send, false, if not
                 */
                function send_if_gif($id, $path) {
                        $type = get_post_mime_type($id);

                        // not a gif, we can send
                        if ($type !== "image/gif") {
                                return true;
                        }

                        // we will convert to png, send
                        if (WP_SMPRO_GIF_TO_PNG) {
                                return true;
                        }

                        // if it is animated, we'll send
                        if ($this->is_animated($path)) {
                                return true;
                        }

                        // static gif that user doesn't want to convert to png
                        return false;
                }

                /**
                 * Checks if a gif is animated.
                 * (http://php.net/manual/en/function.imagecreatefromgif.php#104473)
                 *
                 * We don't send static gifs to service if gif_to_png is false
                 *
                 * @param string $filename full filename with path
                 *
                 * @return boolean whether the image is animated(more than 1 frame)
                 */
                function is_animated($filename) {

                        if (!( $fh = @fopen($filename, 'rb') )) {
                                return false;
                        }

                        $frames = 0;

                        /*
                         * an animated gif contains multiple "frames",
                         * each frame has a header made up of:
                         * * a static 4-byte sequence (\x00\x21\xF9\x04)
                         * * 4 variable bytes
                         * * a static 2-byte sequence (\x00\x2C)
                         * * (some variants may use \x00\x21 ?) Adobe :|
                         * We read through the file til we reach the end,
                         * or we've found at least 2 frame headers
                         * 
                         */
                        while (!feof($fh) && $frames < 2) {
                                $chunk = fread($fh, 1024 * 100); //read 100kb at a time
                                $frames += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
                        }

                        fclose($fh);

                        return $frames > 1;
                }


        }

}